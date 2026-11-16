<?php
/**
 * File Manager plugin — request handling.
 *
 * GET  requests only read data (pages, preview stream, biblio lookup JSON).
 * POST requests perform every change and always require write privilege and
 * a valid CSRF token. Responses to POST are JSON.
 */

namespace SlimsFileManager;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

final class Controller
{
    private const PER_PAGE = 20;
    private const BULK_LIMIT = 100;
    private const CLEANUP_LIMIT = 500;

    private FileRepository $repo;
    private bool $canWrite;

    public function __construct(FileRepository $repo, bool $canWrite)
    {
        $this->repo = $repo;
        $this->canWrite = $canWrite;
    }

    public function handle(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->handlePost();
            return;
        }

        $view = (string)($_GET['fm'] ?? 'list');
        try {
            switch ($view) {
                case 'stream':
                    $this->stream();
                    return;
                case 'biblio_search':
                    $this->biblioSearch();
                    return;
                case 'detail':
                    $this->page('detail', $this->detailData());
                    return;
                case 'edit':
                    $this->requireWritePage();
                    $this->page('edit', ['file' => $this->mustFind((int)($_GET['fid'] ?? 0))]);
                    return;
                case 'upload':
                    $this->requireWritePage();
                    $this->page('upload', [
                        'dirs' => Disk::directories(),
                        'extensions' => Uploader::allowedExtensions(),
                        'maxBytes' => Uploader::maxBytes(),
                    ]);
                    return;
                case 'replace':
                    $this->requireWritePage();
                    $file = $this->mustFind((int)($_GET['fid'] ?? 0));
                    if (self::isUrl($file)) throw new NotFound(t('URL attachments have no physical file to replace.'));
                    $this->page('replace', [
                        'file' => $file,
                        'exists' => Disk::existingPath($file['file_dir'], $file['file_name']) !== null,
                        'extensions' => Uploader::allowedExtensions(),
                        'maxBytes' => Uploader::maxBytes(),
                    ]);
                    return;
                case 'relation':
                    $this->requireWritePage();
                    $file = $this->mustFind((int)($_GET['fid'] ?? 0));
                    $relation = $this->repo->relation((int)$file['file_id'], (int)($_GET['bid'] ?? 0));
                    if (!$relation) throw new NotFound(t('Relation not found.'));
                    $this->page('relation', [
                        'file' => $file,
                        'relation' => $relation,
                        'memberTypes' => $this->repo->memberTypes(),
                    ]);
                    return;
                case 'cleanup':
                    $this->page('cleanup', $this->cleanupData());
                    return;
                case 'list':
                default:
                    $this->page('list', $this->listData());
                    return;
            }
        } catch (NotFound $e) {
            $this->page('message', ['title' => t('Not found'), 'message' => $e->getMessage(), 'variant' => 'warning']);
        } catch (Forbidden $e) {
            $this->page('message', ['title' => t('Access denied'), 'message' => $e->getMessage(), 'variant' => 'danger']);
        } catch (\Throwable $e) {
            error_log('[file_manager] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->page('message', ['title' => t('Something went wrong'), 'message' => t('The request could not be processed. Please try again.'), 'variant' => 'danger']);
        }
    }

    /* ------------------------------------------------------------------ */
    /* GET pages                                                           */
    /* ------------------------------------------------------------------ */

    private function page(string $template, array $data): void
    {
        $data['canWrite'] = $this->canWrite;
        $data['view'] = $template;
        View::render('layout', ['template' => $template, 'data' => $data]);
    }

    private function requireWritePage(): void
    {
        if (!$this->canWrite) throw new Forbidden(t('You don\'t have enough privileges to change data in this area.'));
    }

    private function listData(): array
    {
        $mimes = $this->repo->distinctMimes();
        $dirs = Disk::directories();

        $filters = [
            'q' => mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100),
            'mime' => in_array($_GET['mime'] ?? '', $mimes, true) ? (string)$_GET['mime'] : '',
            'type' => in_array($_GET['type'] ?? '', ['file', 'url'], true) ? (string)$_GET['type'] : '',
            'status' => in_array($_GET['status'] ?? '', ['attached', 'orphan'], true) ? (string)$_GET['status'] : '',
            'dir' => null,
        ];
        $dirParam = (string)($_GET['folder'] ?? '__all');
        if ($dirParam === '/') $filters['dir'] = '';
        elseif ($dirParam !== '__all' && in_array($dirParam, $dirs, true)) $filters['dir'] = $dirParam;

        $sort = array_key_exists($_GET['sort'] ?? '', FileRepository::SORTS) ? (string)$_GET['sort'] : 'updated';
        $direction = ($_GET['dir'] ?? '') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int)($_GET['page'] ?? 1));

        $result = $this->repo->paginate($filters, $sort, $direction, $page, self::PER_PAGE);
        $pages = max(1, (int)ceil($result['total'] / self::PER_PAGE));
        if ($page > $pages && $result['total'] > 0) {
            $page = $pages;
            $result = $this->repo->paginate($filters, $sort, $direction, $page, self::PER_PAGE);
        }

        foreach ($result['rows'] as &$row) {
            $row['is_url'] = self::isUrl($row);
            $row['size'] = $row['is_url'] ? null : Disk::size($row['file_dir'], $row['file_name']);
            $row['missing'] = !$row['is_url'] && $row['size'] === null;
        }
        unset($row);

        return [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => $pages,
            'perPage' => self::PER_PAGE,
            'filters' => $filters,
            'folder' => $filters['dir'] === null ? '__all' : ($filters['dir'] === '' ? '/' : $filters['dir']),
            'sort' => $sort,
            'direction' => $direction,
            'mimes' => $mimes,
            'dirs' => $dirs,
            'stats' => $this->repo->stats(),
        ];
    }

    private function detailData(): array
    {
        $file = $this->mustFind((int)($_GET['fid'] ?? 0));
        $isUrl = self::isUrl($file);
        $path = $isUrl ? null : Disk::existingPath($file['file_dir'], $file['file_name']);
        $relations = $this->repo->relations((int)$file['file_id']);
        $memberTypes = $this->repo->memberTypes();

        foreach ($relations as &$relation) {
            $relation['limit_names'] = [];
            foreach (self::decodeLimit($relation['access_limit']) as $typeId) {
                if (isset($memberTypes[$typeId])) $relation['limit_names'][] = $memberTypes[$typeId];
            }
        }
        unset($relation);

        return [
            'file' => $file,
            'isUrl' => $isUrl,
            'exists' => $path !== null,
            'size' => $path !== null ? (int)@filesize($path) : null,
            'mime' => $isUrl ? FileRepository::URL_MIME : Uploader::mimeForName((string)$file['file_name']),
            'shared' => $isUrl ? 0 : $this->repo->countSharing($file['file_dir'], (string)$file['file_name'], (int)$file['file_id']),
            'relations' => $relations,
            'memberTypes' => $memberTypes,
            'safeUrl' => $isUrl ? self::safeUrl((string)$file['file_url'], false) : null,
        ];
    }

    private function cleanupData(): array
    {
        $tab = in_array($_GET['tab'] ?? '', ['missing', 'orphan', 'untracked'], true) ? (string)$_GET['tab'] : 'missing';
        $items = [];
        $truncated = false;

        if ($tab === 'missing') {
            foreach ($this->repo->physicalRows() as $row) {
                if (Disk::existingPath($row['file_dir'], $row['file_name']) !== null) continue;
                if (count($items) >= self::CLEANUP_LIMIT) {
                    $truncated = true;
                    break;
                }
                $items[] = $row;
            }
        } elseif ($tab === 'orphan') {
            $items = $this->repo->orphans(self::CLEANUP_LIMIT + 1);
            if (count($items) > self::CLEANUP_LIMIT) {
                $truncated = true;
                $items = array_slice($items, 0, self::CLEANUP_LIMIT);
            }
            foreach ($items as &$row) {
                $row['is_url'] = self::isUrl($row);
                $row['exists'] = $row['is_url'] || Disk::existingPath($row['file_dir'], $row['file_name']) !== null;
            }
            unset($row);
        } else {
            $tracked = $this->repo->trackedKeys();
            foreach (Disk::scanFiles(5000) as $entry) {
                if (isset($tracked[Disk::key($entry['dir'], $entry['name'])])) continue;
                if (count($items) >= self::CLEANUP_LIMIT) {
                    $truncated = true;
                    break;
                }
                $entry['path'] = Disk::key($entry['dir'], $entry['name']);
                $items[] = $entry;
            }
        }

        return ['tab' => $tab, 'items' => $items, 'truncated' => $truncated, 'limit' => self::CLEANUP_LIMIT];
    }

    /**
     * Stream a stored file for preview/download (read privilege).
     */
    private function stream(): void
    {
        $file = $this->repo->find((int)($_GET['fid'] ?? 0));
        $path = ($file && !self::isUrl($file)) ? Disk::existingPath($file['file_dir'], $file['file_name']) : null;

        self::cleanBuffers();
        if ($path === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            echo 'File not found';
            exit;
        }

        $mime = Uploader::mimeForName((string)$file['file_name']);
        $inline = ($_GET['mode'] ?? '') !== 'download' && Uploader::isInline($mime);

        $ext = strtolower(pathinfo((string)$file['file_name'], PATHINFO_EXTENSION));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)$file['file_title']) ?: 'file';
        $base = trim(mb_substr($base, 0, 80), '._') ?: 'file';
        $downloadName = $base . ($ext !== '' ? '.' . $ext : '');
        $utf8Name = rawurlencode(mb_substr((string)$file['file_title'], 0, 80) . ($ext !== '' ? '.' . $ext : ''));

        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        @set_time_limit(0);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . $utf8Name);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Referrer-Policy: no-referrer');
        // Sandbox everything except PDFs (browser PDF viewers break under sandbox).
        if ($mime !== 'application/pdf') {
            header("Content-Security-Policy: default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox");
        }

        $handle = fopen($path, 'rb');
        if ($handle !== false) {
            while (!feof($handle)) {
                echo fread($handle, 65536);
                flush();
            }
            fclose($handle);
        }
        exit;
    }

    private function biblioSearch(): void
    {
        $fileId = (int)($_GET['fid'] ?? 0);
        $term = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
        $rows = $this->repo->find($fileId) ? $this->repo->searchBiblio($term, $fileId) : [];
        self::json([
            'ok' => true,
            'results' => array_map(fn($r) => [
                'id' => (int)$r['biblio_id'],
                'title' => (string)$r['title'],
                'isbn' => (string)($r['isbn_issn'] ?? ''),
                'year' => (string)($r['publish_year'] ?? ''),
            ], $rows),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* POST actions                                                        */
    /* ------------------------------------------------------------------ */

    private function handlePost(): void
    {
        if (!$this->canWrite) {
            self::json(['ok' => false, 'message' => t('You don\'t have enough privileges to change data in this area.')], 403);
        }
        if (!Csrf::verify()) {
            self::json(['ok' => false, 'message' => t('Your form session has expired. Please reload the page and try again.')], 419);
        }

        $action = (string)($_POST['fm_action'] ?? '');
        $handlers = [
            'update' => 'actionUpdate',
            'upload' => 'actionUpload',
            'replace' => 'actionReplace',
            'delete' => 'actionDelete',
            'bulk_delete' => 'actionBulkDelete',
            'delete_untracked' => 'actionDeleteUntracked',
            'attach' => 'actionAttach',
            'relation_update' => 'actionRelationUpdate',
            'detach' => 'actionDetach',
        ];

        try {
            if (!isset($handlers[$action])) throw new ValidationException(t('Unknown action.'));
            $result = $this->{$handlers[$action]}();
            self::json(['ok' => true] + $result);
        } catch (ValidationException $e) {
            self::json(['ok' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (NotFound $e) {
            self::json(['ok' => false, 'message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            error_log('[file_manager] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            self::json(['ok' => false, 'message' => t('The request could not be processed. Please try again.')], 500);
        }
    }

    private function actionUpdate(): array
    {
        $file = $this->mustFind(self::postInt('fid'));
        $data = [
            'file_title' => self::postText('fileTitle', 500, true, t('Title')),
            'file_desc' => self::postText('fileDesc', 5000) ?: null,
        ];

        if (self::postBool('removeKey')) {
            $data['file_key'] = null;
        } else {
            $key = self::postText('fileKey', 100, false, '', false);
            if ($key !== '') $data['file_key'] = $key;
        }

        if (self::isUrl($file)) {
            $url = self::safeUrl(self::postText('fileURL', 2000, true, 'URL', false));
            $data['file_url'] = $url;
            $data['file_name'] = $url;
        }

        $this->repo->update((int)$file['file_id'], $data);
        $this->log('Update', sprintf('updated attachment metadata #%d (%s)', $file['file_id'], $data['file_title']));

        return [
            'message' => t('File metadata saved.'),
            'redirect' => View::url(['fm' => 'detail', 'fid' => (int)$file['file_id']]),
        ];
    }

    private function actionUpload(): array
    {
        $source = ($_POST['source'] ?? '') === 'url' ? 'url' : 'file';
        $desc = self::postText('fileDesc', 5000) ?: null;
        $key = self::postText('fileKey', 100, false, '', false) ?: null;

        if ($source === 'url') {
            $url = self::safeUrl(self::postText('fileURL', 2000, true, 'URL', false));
            $title = self::postText('fileTitle', 500) ?: (string)(parse_url($url, PHP_URL_HOST) ?: $url);
            $id = $this->repo->insert([
                'file_title' => $title,
                'file_name' => $url,
                'file_url' => $url,
                'file_dir' => null,
                'mime_type' => FileRepository::URL_MIME,
                'file_desc' => $desc,
                'file_key' => null,
                'uploader_id' => (int)($_SESSION['uid'] ?? 0),
            ]);
            $this->log('Add', sprintf('added URL attachment #%d (%s)', $id, $title));
            return ['message' => t('URL attachment added.'), 'redirect' => View::url(['fm' => 'detail', 'fid' => $id])];
        }

        $dir = (string)($_POST['fileDir'] ?? '');
        if (!in_array($dir, Disk::directories(), true)) {
            throw new ValidationException(t('The destination folder is not valid.'), ['fileDir' => t('The destination folder is not valid.')]);
        }

        $upload = Uploader::validate('file2attach');
        $title = self::postText('fileTitle', 500) ?: (pathinfo($upload['original'], PATHINFO_FILENAME) ?: $upload['original']);
        $stored = Uploader::store($upload, $dir);

        try {
            $id = $this->repo->insert([
                'file_title' => $title,
                'file_name' => $stored,
                'file_url' => null,
                'file_dir' => $dir,
                'mime_type' => $upload['mime'],
                'file_desc' => $desc,
                'file_key' => $key,
                'uploader_id' => (int)($_SESSION['uid'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            Disk::delete($dir, $stored);
            throw $e;
        }

        $this->log('Add', sprintf('uploaded attachment #%d (%s) to %s', $id, $title, Disk::key($dir, $stored)));
        return ['message' => t('File uploaded.'), 'redirect' => View::url(['fm' => 'detail', 'fid' => $id])];
    }

    private function actionReplace(): array
    {
        $file = $this->mustFind(self::postInt('fid'));
        if (self::isUrl($file)) throw new ValidationException(t('URL attachments have no physical file to replace.'));

        $dir = Disk::normalizeDir($file['file_dir']);
        if ($dir === null || Disk::directoryPath($dir) === null) {
            throw new ValidationException(t('The folder of this file no longer exists in the repository.'));
        }

        $upload = Uploader::validate('file2attach');
        $stored = Uploader::store($upload, $dir);

        try {
            $this->repo->update((int)$file['file_id'], ['file_name' => $stored, 'mime_type' => $upload['mime']]);
        } catch (\Throwable $e) {
            Disk::delete($dir, $stored);
            throw $e;
        }

        $oldRemoved = false;
        if ($this->repo->countSharing($file['file_dir'], (string)$file['file_name'], (int)$file['file_id']) === 0) {
            $oldRemoved = Disk::delete($file['file_dir'], (string)$file['file_name']);
        }

        $this->log('Update', sprintf('replaced physical file of attachment #%d (%s -> %s)', $file['file_id'], $file['file_name'], $stored));
        return [
            'message' => $oldRemoved || Disk::existingPath($file['file_dir'], (string)$file['file_name']) === null
                ? t('File replaced.')
                : t('File replaced. The old file was kept because another record still uses it.'),
            'redirect' => View::url(['fm' => 'detail', 'fid' => (int)$file['file_id']]),
        ];
    }

    private function actionDelete(): array
    {
        $file = $this->mustFind(self::postInt('fid'));
        $relations = $this->repo->relations((int)$file['file_id']);
        if ($relations && !self::postBool('confirmDetach')) {
            throw new ValidationException(
                sprintf(t('This file is still attached to %d bibliographic record(s). Confirm detaching it first.'), count($relations)),
                ['confirmDetach' => t('Please confirm.')]
            );
        }

        $physical = $this->deleteRecord($file, self::postBool('deletePhysical'));
        $this->log('Delete', sprintf('deleted attachment #%d (%s), detached from %d record(s)%s', $file['file_id'], $file['file_title'], count($relations), $physical ? ', physical file removed' : ''));

        return [
            'message' => t('File deleted.'),
            'redirect' => View::url(['fm' => (string)($_POST['returnTo'] ?? '') === 'cleanup' ? 'cleanup' : 'list']),
        ];
    }

    private function actionBulkDelete(): array
    {
        $ids = self::postIds('ids');
        $deletePhysical = self::postBool('deletePhysical');
        $deleted = 0;
        foreach ($ids as $id) {
            $file = $this->repo->find($id);
            if (!$file) continue;
            $this->deleteRecord($file, $deletePhysical);
            $deleted++;
        }
        $this->log('Delete', sprintf('bulk deleted %d attachment record(s)%s', $deleted, $deletePhysical ? ' with physical files' : ''));

        $tab = in_array($_POST['tab'] ?? '', ['missing', 'orphan'], true) ? (string)$_POST['tab'] : 'missing';
        return [
            'message' => sprintf(t('%d record(s) deleted.'), $deleted),
            'redirect' => View::url(['fm' => 'cleanup', 'tab' => $tab]),
        ];
    }

    private function actionDeleteUntracked(): array
    {
        $paths = $_POST['paths'] ?? [];
        if (!is_array($paths) || !$paths) throw new ValidationException(t('Select at least one item.'));
        if (count($paths) > self::BULK_LIMIT) throw new ValidationException(sprintf(t('You can process at most %d items at once.'), self::BULK_LIMIT));

        $deleted = 0;
        foreach (array_unique($paths) as $relative) {
            if (!is_string($relative)) continue;
            $relative = str_replace('\\', '/', $relative);
            $pos = strrpos($relative, '/');
            $dir = $pos === false ? '' : substr($relative, 0, $pos);
            $name = $pos === false ? $relative : substr($relative, $pos + 1);

            // Must still exist, stay inside the repository and NOT be referenced in the database.
            if (Disk::existingPath($dir, $name) === null) continue;
            $normalizedDir = (string)Disk::normalizeDir($dir);
            if ($this->repo->isTracked($normalizedDir, $name)) continue;
            if (Disk::delete($normalizedDir, $name)) $deleted++;
        }
        $this->log('Delete', sprintf('deleted %d untracked repository file(s)', $deleted));

        return [
            'message' => sprintf(t('%d file(s) deleted from the repository.'), $deleted),
            'redirect' => View::url(['fm' => 'cleanup', 'tab' => 'untracked']),
        ];
    }

    private function actionAttach(): array
    {
        $file = $this->mustFind(self::postInt('fid'));
        $biblio = $this->repo->biblio(self::postInt('bid'));
        if (!$biblio) throw new ValidationException(t('Choose a bibliographic record first.'), ['bid' => t('Choose a bibliographic record first.')]);
        if ($this->repo->relation((int)$file['file_id'], (int)$biblio['biblio_id'])) {
            throw new ValidationException(t('This file is already attached to that record.'));
        }

        [$placement, $access, $limit] = $this->relationInput();
        $this->repo->attach((int)$file['file_id'], (int)$biblio['biblio_id'], $placement, $access, $limit);
        $this->log('Add', sprintf('attached file #%d to biblio #%d (%s)', $file['file_id'], $biblio['biblio_id'], $biblio['title']));

        return [
            'message' => t('File attached to the bibliographic record.'),
            'redirect' => View::url(['fm' => 'detail', 'fid' => (int)$file['file_id']]),
        ];
    }

    private function actionRelationUpdate(): array
    {
        $file = $this->mustFind(self::postInt('fid'));
        $relation = $this->repo->relation((int)$file['file_id'], self::postInt('bid'));
        if (!$relation) throw new NotFound(t('Relation not found.'));

        [$placement, $access, $limit] = $this->relationInput();
        $this->repo->updateRelation((int)$file['file_id'], (int)$relation['biblio_id'], $placement, $access, $limit);
        $this->log('Update', sprintf('updated attachment access of file #%d on biblio #%d', $file['file_id'], $relation['biblio_id']));

        return [
            'message' => t('Attachment settings saved.'),
            'redirect' => View::url(['fm' => 'detail', 'fid' => (int)$file['file_id']]),
        ];
    }

    private function actionDetach(): array
    {
        $file = $this->mustFind(self::postInt('fid'));
        $relation = $this->repo->relation((int)$file['file_id'], self::postInt('bid'));
        if (!$relation) throw new NotFound(t('Relation not found.'));

        $this->repo->detach((int)$file['file_id'], (int)$relation['biblio_id']);
        $this->log('Delete', sprintf('detached file #%d from biblio #%d', $file['file_id'], $relation['biblio_id']));

        return [
            'message' => t('File detached from the bibliographic record.'),
            'redirect' => View::url(['fm' => 'detail', 'fid' => (int)$file['file_id']]),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Delete the row (+ relations) and optionally its physical file when no
     * other row shares it. Returns true when the physical file was removed.
     */
    private function deleteRecord(array $file, bool $deletePhysical): bool
    {
        $id = (int)$file['file_id'];
        $isUrl = self::isUrl($file);
        $shared = $isUrl ? 0 : $this->repo->countSharing($file['file_dir'], (string)$file['file_name'], $id);

        $this->repo->delete($id);

        if ($deletePhysical && !$isUrl && $shared === 0) {
            return Disk::delete($file['file_dir'], (string)$file['file_name']);
        }
        return false;
    }

    /** @return array{0:string,1:string,2:?string} */
    private function relationInput(): array
    {
        $placement = (string)($_POST['placement'] ?? 'link');
        if (!in_array($placement, FileRepository::PLACEMENTS, true)) {
            throw new ValidationException(t('Invalid placement.'), ['placement' => t('Invalid placement.')]);
        }
        $access = (string)($_POST['accessType'] ?? 'public');
        if (!in_array($access, FileRepository::ACCESS_TYPES, true)) {
            throw new ValidationException(t('Invalid access type.'), ['accessType' => t('Invalid access type.')]);
        }

        $limit = null;
        if ($access === 'public') {
            $valid = array_keys($this->repo->memberTypes());
            $selected = [];
            foreach ((array)($_POST['accLimit'] ?? []) as $value) {
                $value = filter_var($value, FILTER_VALIDATE_INT);
                if ($value !== false && in_array($value, $valid, true)) $selected[$value] = (string)$value;
            }
            // Same storage format as admin/modules/bibliography/pop_attach.php
            $limit = $selected ? serialize(array_values($selected)) : null;
        }
        return [$placement, $access, $limit];
    }

    /**
     * Decode access_limit safely (no object instantiation).
     *
     * @return int[]
     */
    public static function decodeLimit(?string $raw): array
    {
        if ($raw === null || $raw === '') return [];
        $value = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($value)) return [];
        return array_values(array_filter(array_map('intval', $value), fn($v) => $v > 0));
    }

    private function mustFind(int $id): array
    {
        $file = $this->repo->find($id);
        if (!$file) throw new NotFound(t('File not found.'));
        return $file;
    }

    public static function isUrl(array $file): bool
    {
        return ($file['mime_type'] ?? '') === FileRepository::URL_MIME;
    }

    /**
     * Accept only absolute http(s) URLs without credentials that do not point
     * to private/reserved networks (same policy as pop_attach.php cleanUrl()).
     *
     * @return string|null  validated URL ($throw=false) — throws otherwise
     */
    public static function safeUrl(string $url, bool $throw = true): ?string
    {
        $fail = function () use ($throw) {
            if ($throw) throw new ValidationException(t('Enter a valid public http(s) URL.'), ['fileURL' => t('Enter a valid public http(s) URL.')]);
            return null;
        };

        $url = trim($url);
        if ($url === '' || strlen($url) > 2000 || preg_match('/[\x00-\x20\x7F]/', $url)) return $fail();
        if (filter_var($url, FILTER_VALIDATE_URL) === false) return $fail();

        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower(trim((string)($parts['host'] ?? ''), '[]'));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') return $fail();
        if (isset($parts['user']) || isset($parts['pass'])) return $fail();
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) return $fail();

        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, $flags) === false) return $fail();
        } elseif ($throw) {
            // Only resolve DNS when saving, not on every page render.
            $ip = @gethostbyname($host);
            if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP) && filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) return $fail();
        }
        return $url;
    }

    private static function postInt(string $key): int
    {
        $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $value === false ? 0 : (int)$value;
    }

    private static function postBool(string $key): bool
    {
        return ($_POST[$key] ?? '') === '1';
    }

    /** @return int[] */
    private static function postIds(string $key): array
    {
        $raw = $_POST[$key] ?? [];
        if (!is_array($raw) || !$raw) throw new ValidationException(t('Select at least one item.'));
        $ids = [];
        foreach ($raw as $value) {
            $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($value !== false) $ids[$value] = $value;
        }
        if (!$ids) throw new ValidationException(t('Select at least one item.'));
        if (count($ids) > self::BULK_LIMIT) throw new ValidationException(sprintf(t('You can process at most %d items at once.'), self::BULK_LIMIT));
        return array_values($ids);
    }

    /**
     * Read a text field from POST with a length limit.
     */
    private static function postText(string $key, int $max, bool $required = false, string $label = '', bool $collapse = true): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_string($value)) $value = '';
        $value = str_replace("\0", '', $value);
        $value = trim($collapse ? (preg_replace('/[^\S\n]+/u', ' ', $value) ?? '') : $value);

        if ($required && $value === '') {
            $msg = sprintf(t('%s is required.'), $label ?: $key);
            throw new ValidationException($msg, [$key => $msg]);
        }
        if (mb_strlen($value) > $max) {
            $msg = sprintf(t('%s is too long (max %d characters).'), $label ?: $key, $max);
            throw new ValidationException($msg, [$key => $msg]);
        }
        return $value;
    }

    private function log(string $action, string $message): void
    {
        try {
            writeLog('staff', (string)($_SESSION['uid'] ?? '0'), 'master_file', ($_SESSION['realname'] ?? 'User') . ' ' . $message, 'FileManager', $action);
        } catch (\Throwable $e) {
            error_log('[file_manager] log failed: ' . $e->getMessage());
        }
    }

    private static function cleanBuffers(): void
    {
        while (ob_get_level() > 0) ob_end_clean();
    }

    /**
     * Send a JSON response and stop.
     */
    public static function json(array $payload, int $status = 200): void
    {
        self::cleanBuffers();
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        echo View::json($payload);
        exit;
    }
}

final class NotFound extends \RuntimeException {}
final class Forbidden extends \RuntimeException {}

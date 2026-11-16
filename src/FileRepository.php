<?php
/**
 * File Manager plugin — database access for `files` and `biblio_attachment`.
 *
 * All queries use PDO prepared statements. Identifiers that cannot be bound
 * (ORDER BY column/direction) come from a fixed whitelist only; LIMIT/OFFSET
 * are cast to int.
 */

namespace SlimsFileManager;

use PDO;
use PDOStatement;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

final class FileRepository
{
    public const URL_MIME = 'text/uri-list';

    /** sort key => SQL expression */
    public const SORTS = [
        'title' => 'f.file_title',
        'name' => 'f.file_name',
        'mime' => 'f.mime_type',
        'links' => 'link_count',
        'created' => 'f.input_date',
        'updated' => 'f.last_update',
    ];

    public const PLACEMENTS = ['link', 'popup', 'embed'];
    public const ACCESS_TYPES = ['public', 'private'];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Prepare + execute, failing loudly even when PDO runs in silent mode.
     */
    private function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new \RuntimeException('Database prepare failed');
        }
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : ':' . ltrim($key, ':');
            $type = is_int($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue($name, $value, $type);
        }
        if ($stmt->execute() === false) {
            $info = $stmt->errorInfo();
            throw new \RuntimeException('Database query failed: ' . ($info[2] ?? 'unknown'));
        }
        return $stmt;
    }

    private static function like(string $term): string
    {
        return '%' . addcslashes($term, '\\%_') . '%';
    }

    /**
     * @param array{q?:string,mime?:string,dir?:string,type?:string,status?:string} $filters
     * @return array{rows: array<int,array>, total: int}
     */
    public function paginate(array $filters, string $sort, string $direction, int $page, int $perPage): array
    {
        $where = [];
        $params = [];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(f.file_title LIKE :q1 OR f.file_name LIKE :q2 OR f.file_desc LIKE :q3)';
            $params['q1'] = $params['q2'] = $params['q3'] = self::like($q);
        }

        if (($filters['mime'] ?? '') !== '') {
            $where[] = 'f.mime_type = :mime';
            $params['mime'] = (string)$filters['mime'];
        }

        if (isset($filters['dir']) && $filters['dir'] !== null && $filters['dir'] !== '__all') {
            if ($filters['dir'] === '') {
                $where[] = "(f.file_dir IS NULL OR f.file_dir = '' OR f.file_dir = '/')";
            } else {
                $where[] = 'TRIM(BOTH \'/\' FROM REPLACE(f.file_dir, \'\\\\\', \'/\')) = :dir';
                $params['dir'] = (string)$filters['dir'];
            }
        }

        switch ($filters['type'] ?? '') {
            case 'url':
                $where[] = 'f.mime_type = :urlmime';
                $params['urlmime'] = self::URL_MIME;
                break;
            case 'file':
                $where[] = '(f.mime_type IS NULL OR f.mime_type <> :urlmime)';
                $params['urlmime'] = self::URL_MIME;
                break;
        }

        switch ($filters['status'] ?? '') {
            case 'attached':
                $where[] = 'EXISTS (SELECT 1 FROM biblio_attachment ba2 WHERE ba2.file_id = f.file_id)';
                break;
            case 'orphan':
                $where[] = 'NOT EXISTS (SELECT 1 FROM biblio_attachment ba2 WHERE ba2.file_id = f.file_id)';
                break;
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $total = (int)$this->run('SELECT COUNT(*) FROM files f' . $whereSql, $params)->fetchColumn();

        $orderBy = self::SORTS[$sort] ?? self::SORTS['updated'];
        $dir = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
        $perPage = max(1, min(100, $perPage));
        $offset = max(0, ($page - 1) * $perPage);

        $sql = 'SELECT f.file_id, f.file_title, f.file_name, f.file_dir, f.file_url, f.mime_type,
                       f.uploader_id, f.input_date, f.last_update,
                       (f.file_key IS NOT NULL AND f.file_key <> \'\') AS has_key,
                       u.realname AS uploader,
                       (SELECT COUNT(*) FROM biblio_attachment ba WHERE ba.file_id = f.file_id) AS link_count
                FROM files f
                LEFT JOIN user u ON u.user_id = f.uploader_id'
            . $whereSql
            . " ORDER BY {$orderBy} {$dir}, f.file_id DESC"
            . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

        $rows = $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        return ['rows' => $rows, 'total' => $total];
    }

    public function find(int $id): ?array
    {
        if ($id <= 0) return null;
        $row = $this->run(
            'SELECT f.*, u.realname AS uploader
             FROM files f LEFT JOIN user u ON u.user_id = f.uploader_id
             WHERE f.file_id = :id',
            ['id' => $id]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function stats(): array
    {
        $row = $this->run(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN f.mime_type = :u1 THEN 1 ELSE 0 END) AS urls,
                    SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM biblio_attachment ba WHERE ba.file_id = f.file_id) THEN 1 ELSE 0 END) AS orphans
             FROM files f',
            ['u1' => self::URL_MIME]
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int)($row['total'] ?? 0),
            'urls' => (int)($row['urls'] ?? 0),
            'orphans' => (int)($row['orphans'] ?? 0),
        ];
    }

    /** @return string[] */
    public function distinctMimes(): array
    {
        return array_values(array_filter(
            $this->run("SELECT DISTINCT mime_type FROM files WHERE mime_type IS NOT NULL AND mime_type <> '' ORDER BY mime_type")
                ->fetchAll(PDO::FETCH_COLUMN)
        ));
    }

    /** @return array<int,array> */
    public function relations(int $fileId): array
    {
        return $this->run(
            'SELECT ba.biblio_id, ba.placement, ba.access_type, ba.access_limit, b.title, b.isbn_issn, b.publish_year
             FROM biblio_attachment ba
             LEFT JOIN biblio b ON b.biblio_id = ba.biblio_id
             WHERE ba.file_id = :id
             ORDER BY b.title',
            ['id' => $fileId]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function relation(int $fileId, int $biblioId): ?array
    {
        $row = $this->run(
            'SELECT ba.biblio_id, ba.file_id, ba.placement, ba.access_type, ba.access_limit, b.title
             FROM biblio_attachment ba
             LEFT JOIN biblio b ON b.biblio_id = ba.biblio_id
             WHERE ba.file_id = :f AND ba.biblio_id = :b
             LIMIT 1',
            ['f' => $fileId, 'b' => $biblioId]
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insert(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $this->run(
            'INSERT INTO files (file_title, file_name, file_url, file_dir, mime_type, file_desc, file_key, uploader_id, input_date, last_update)
             VALUES (:title, :name, :url, :dir, :mime, :descr, :fkey, :uploader, :created, :updated)',
            [
                'title' => $data['file_title'],
                'name' => $data['file_name'],
                'url' => $data['file_url'] ?? null,
                'dir' => $data['file_dir'] ?? null,
                'mime' => $data['mime_type'] ?? null,
                'descr' => $data['file_desc'] ?? null,
                'fkey' => $data['file_key'] ?? null,
                'uploader' => (int)$data['uploader_id'],
                'created' => $now,
                'updated' => $now,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    /**
     * Update whitelisted columns only.
     */
    public function update(int $id, array $data): void
    {
        $allowed = ['file_title', 'file_desc', 'file_key', 'file_url', 'file_name', 'mime_type'];
        $sets = [];
        $params = ['id' => $id, 'updated' => date('Y-m-d H:i:s')];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $sets[] = "{$column} = :{$column}";
                $params[$column] = $data[$column];
            }
        }
        if (!$sets) return;
        $this->run('UPDATE files SET ' . implode(', ', $sets) . ', last_update = :updated WHERE file_id = :id', $params);
    }

    /**
     * Delete a file row together with all its biblio relations.
     */
    public function delete(int $id): void
    {
        $this->run('DELETE FROM biblio_attachment WHERE file_id = :id', ['id' => $id]);
        $this->run('DELETE FROM files WHERE file_id = :id', ['id' => $id]);
    }

    public function biblio(int $biblioId): ?array
    {
        if ($biblioId <= 0) return null;
        $row = $this->run('SELECT biblio_id, title FROM biblio WHERE biblio_id = :id', ['id' => $biblioId])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function attach(int $fileId, int $biblioId, string $placement, string $accessType, ?string $accessLimit): void
    {
        $this->run(
            'INSERT INTO biblio_attachment (biblio_id, file_id, placement, access_type, access_limit)
             VALUES (:b, :f, :p, :a, :l)',
            ['b' => $biblioId, 'f' => $fileId, 'p' => $placement, 'a' => $accessType, 'l' => $accessLimit]
        );
        $this->touchBiblio($biblioId);
    }

    public function updateRelation(int $fileId, int $biblioId, string $placement, string $accessType, ?string $accessLimit): void
    {
        $this->run(
            'UPDATE biblio_attachment SET placement = :p, access_type = :a, access_limit = :l
             WHERE file_id = :f AND biblio_id = :b',
            ['p' => $placement, 'a' => $accessType, 'l' => $accessLimit, 'f' => $fileId, 'b' => $biblioId]
        );
        $this->touchBiblio($biblioId);
    }

    public function detach(int $fileId, int $biblioId): void
    {
        $this->run('DELETE FROM biblio_attachment WHERE file_id = :f AND biblio_id = :b', ['f' => $fileId, 'b' => $biblioId]);
        $this->touchBiblio($biblioId);
    }

    /**
     * Mark the bibliographic record as changed so caches/indexes refresh.
     */
    private function touchBiblio(int $biblioId): void
    {
        $this->run('UPDATE biblio SET last_update = :now WHERE biblio_id = :id', ['now' => date('Y-m-d H:i:s'), 'id' => $biblioId]);
    }

    /**
     * Search bibliographic records to attach (title / ISBN), excluding the
     * records already attached to $fileId.
     */
    public function searchBiblio(string $term, int $fileId, int $limit = 20): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) return [];
        return $this->run(
            'SELECT b.biblio_id, b.title, b.isbn_issn, b.publish_year
             FROM biblio b
             WHERE (b.title LIKE :t OR b.isbn_issn LIKE :i)
               AND NOT EXISTS (SELECT 1 FROM biblio_attachment ba WHERE ba.biblio_id = b.biblio_id AND ba.file_id = :f)
             ORDER BY b.title
             LIMIT ' . max(1, min(50, $limit)),
            ['t' => self::like($term), 'i' => self::like($term), 'f' => $fileId]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,string> member_type_id => name */
    public function memberTypes(): array
    {
        $out = [];
        foreach ($this->run('SELECT member_type_id, member_type_name FROM mst_member_type ORDER BY member_type_name')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['member_type_id']] = (string)$row['member_type_name'];
        }
        return $out;
    }

    /**
     * Number of OTHER rows that point to the same physical file.
     */
    public function countSharing(?string $dir, string $name, int $excludeId): int
    {
        $rows = $this->run(
            'SELECT file_id, file_dir FROM files WHERE file_name = :n AND file_id <> :id',
            ['n' => $name, 'id' => $excludeId]
        )->fetchAll(PDO::FETCH_ASSOC);
        $key = Disk::key($dir, $name);
        $count = 0;
        foreach ($rows as $row) {
            if (Disk::key($row['file_dir'], $name) === $key) $count++;
        }
        return $count;
    }

    /**
     * Rows that point to a physical file (i.e. not URL attachments), in chunks.
     *
     * @return \Generator<array>
     */
    public function physicalRows(): \Generator
    {
        $last = 0;
        do {
            $rows = $this->run(
                'SELECT f.file_id, f.file_title, f.file_name, f.file_dir, f.mime_type, f.last_update,
                        (SELECT COUNT(*) FROM biblio_attachment ba WHERE ba.file_id = f.file_id) AS link_count
                 FROM files f
                 WHERE f.file_id > :last AND (f.mime_type IS NULL OR f.mime_type <> :u)
                 ORDER BY f.file_id
                 LIMIT 1000',
                ['last' => $last, 'u' => self::URL_MIME]
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $last = (int)$row['file_id'];
                yield $row;
            }
        } while (count($rows) === 1000);
    }

    public function orphans(int $limit = 500): array
    {
        return $this->run(
            'SELECT f.file_id, f.file_title, f.file_name, f.file_dir, f.mime_type, f.last_update
             FROM files f
             WHERE NOT EXISTS (SELECT 1 FROM biblio_attachment ba WHERE ba.file_id = f.file_id)
             ORDER BY f.last_update DESC
             LIMIT ' . max(1, min(5000, $limit))
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isTracked(string $dir, string $name): bool
    {
        return $this->countSharing($dir, $name, 0) > 0;
    }

    /** @return array<string,true> */
    public function trackedKeys(): array
    {
        $keys = [];
        $stmt = $this->run('SELECT file_dir, file_name FROM files WHERE mime_type IS NULL OR mime_type <> :u', ['u' => self::URL_MIME]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $keys[Disk::key($row['file_dir'], (string)$row['file_name'])] = true;
        }
        return $keys;
    }
}

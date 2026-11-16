<?php
/**
 * File list page.
 *
 * @var array  $rows
 * @var int    $total, $page, $pages, $perPage
 * @var array  $filters, $mimes, $dirs, $stats
 * @var string $folder, $sort, $direction
 * @var bool   $canWrite
 */
use SlimsFileManager\View as V;
use function SlimsFileManager\t;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

$query = array_filter([
    'q' => $filters['q'],
    'type' => $filters['type'],
    'status' => $filters['status'],
    'mime' => $filters['mime'],
    'folder' => $folder !== '__all' ? $folder : '',
    'sort' => $sort !== 'updated' ? $sort : '',
    'dir' => $direction !== 'desc' ? $direction : '',
]);
$hasFilter = $filters['q'] !== '' || $filters['type'] !== '' || $filters['status'] !== '' || $filters['mime'] !== '' || $folder !== '__all';

$sortLink = function (string $key, string $label) use ($query, $sort, $direction) {
    $next = ($sort === $key && $direction === 'asc') ? 'desc' : 'asc';
    $url = V::url(array_merge($query, ['sort' => $key, 'dir' => $next, 'page' => null]));
    $state = $sort === $key ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none';
    $arrow = $sort === $key ? ($direction === 'asc' ? ' ↑' : ' ↓') : '';
    return '<a class="fm-sort" href="' . V::e($url) . '" aria-sort="' . $state . '">' . V::e($label) . $arrow . '</a>';
};

$actions = '';
if ($canWrite) {
    $actions .= '<a class="fm-btn fm-btn--default" href="' . V::e(V::url(['fm' => 'upload'])) . '">' . V::label('upload', t('Upload File')) . '</a>';
}
$actions .= '<a class="fm-btn fm-btn--outline" href="' . V::e(V::url(['fm' => 'cleanup'])) . '">' . V::label('eraser', t('Cleanup')) . '</a>';

V::render('header', [
    'title' => t('File Manager'),
    'subtitle' => t('Browse, edit and clean up bibliography file attachments.'),
    'icon' => 'folder-open',
    'actions' => $actions,
]);
?>

<section class="fm-stats">
  <a class="fm-stat" href="<?= V::e(V::url()) ?>">
    <span class="fm-stat-label"><?= V::e(t('Total records')) ?></span>
    <span class="fm-stat-value"><?= number_format($stats['total']) ?></span>
  </a>
  <a class="fm-stat" href="<?= V::e(V::url(['type' => 'url'])) ?>">
    <span class="fm-stat-label"><?= V::e(t('URL attachments')) ?></span>
    <span class="fm-stat-value"><?= number_format($stats['urls']) ?></span>
  </a>
  <a class="fm-stat" href="<?= V::e(V::url(['status' => 'orphan'])) ?>">
    <span class="fm-stat-label"><?= V::e(t('Not attached')) ?></span>
    <span class="fm-stat-value"><?= number_format($stats['orphans']) ?></span>
  </a>
</section>

<section class="fm-card">
  <form class="fm-toolbar submitViaAJAX" method="get" action="<?= V::e(V::url()) ?>" role="search">
    <div class="fm-input-icon fm-grow">
      <?= V::icon('search') ?>
      <input class="fm-input" type="search" name="q" maxlength="100" value="<?= V::e($filters['q']) ?>"
             placeholder="<?= V::e(t('Search title, file name or description…')) ?>" aria-label="<?= V::e(t('Search')) ?>">
    </div>
    <select class="fm-select" name="type" aria-label="<?= V::e(t('Type')) ?>">
      <option value=""><?= V::e(t('All types')) ?></option>
      <option value="file" <?= $filters['type'] === 'file' ? 'selected' : '' ?>><?= V::e(t('Files')) ?></option>
      <option value="url" <?= $filters['type'] === 'url' ? 'selected' : '' ?>><?= V::e(t('URLs')) ?></option>
    </select>
    <select class="fm-select" name="status" aria-label="<?= V::e(t('Status')) ?>">
      <option value=""><?= V::e(t('All statuses')) ?></option>
      <option value="attached" <?= $filters['status'] === 'attached' ? 'selected' : '' ?>><?= V::e(t('Attached')) ?></option>
      <option value="orphan" <?= $filters['status'] === 'orphan' ? 'selected' : '' ?>><?= V::e(t('Not attached')) ?></option>
    </select>
    <select class="fm-select" name="mime" aria-label="<?= V::e(t('MIME type')) ?>">
      <option value=""><?= V::e(t('All MIME types')) ?></option>
      <?php foreach ($mimes as $mime): ?>
        <option value="<?= V::e($mime) ?>" <?= $filters['mime'] === $mime ? 'selected' : '' ?>><?= V::e($mime) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="fm-select" name="folder" aria-label="<?= V::e(t('Folder')) ?>">
      <option value="__all"><?= V::e(t('All folders')) ?></option>
      <?php foreach ($dirs as $d): $value = $d === '' ? '/' : $d; ?>
        <option value="<?= V::e($value) ?>" <?= $folder === $value ? 'selected' : '' ?>><?= V::e($d === '' ? t('(repository root)') : $d) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="fm-btn fm-btn--secondary" type="submit"><?= V::label('search', t('Search')) ?></button>
    <?php if ($hasFilter): ?>
      <a class="fm-btn fm-btn--ghost" href="<?= V::e(V::url()) ?>"><?= V::label('rotate-ccw', t('Reset')) ?></a>
    <?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="fm-empty">
      <span class="fm-empty-icon"><?= V::icon('inbox') ?></span>
      <p class="fm-empty-title"><?= V::e($hasFilter ? t('No files match your filters.') : t('No attachment files yet.')) ?></p>
      <p class="fm-muted"><?= V::e($hasFilter ? t('Try another keyword or reset the filters.') : t('Uploaded attachments will appear here.')) ?></p>
      <?php if ($canWrite && !$hasFilter): ?>
        <a class="fm-btn fm-btn--default" href="<?= V::e(V::url(['fm' => 'upload'])) ?>"><?= V::label('upload', t('Upload File')) ?></a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="fm-table-wrap">
      <table class="fm-table">
        <thead>
          <tr>
            <th scope="col"><?= $sortLink('title', t('File')) ?></th>
            <th scope="col"><?= $sortLink('mime', t('Type')) ?></th>
            <th scope="col" class="fm-num"><?= V::e(t('Size')) ?></th>
            <th scope="col" class="fm-num"><?= $sortLink('links', t('Records')) ?></th>
            <th scope="col"><?= $sortLink('updated', t('Last updated')) ?></th>
            <th scope="col" class="fm-right"><?= V::e(t('Actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row):
            $detail = V::url(['fm' => 'detail', 'fid' => (int)$row['file_id']]);
          ?>
          <tr>
            <td class="fm-cell-file">
              <span class="fm-file-icon <?= $row['missing'] ? 'is-danger' : '' ?>"><?= V::icon($row['is_url'] ? 'globe' : ($row['missing'] ? 'file-x' : 'file-text')) ?></span>
              <div class="fm-file-meta">
                <a class="fm-link fm-strong" href="<?= V::e($detail) ?>"><?= V::e($row['file_title'] !== '' ? $row['file_title'] : t('(untitled)')) ?></a>
                <span class="fm-muted fm-truncate" title="<?= V::e($row['is_url'] ? $row['file_url'] : \SlimsFileManager\Disk::key($row['file_dir'], $row['file_name'])) ?>">
                  <?= V::e($row['is_url'] ? $row['file_url'] : \SlimsFileManager\Disk::key($row['file_dir'], $row['file_name'])) ?>
                </span>
                <span class="fm-badges">
                  <?php if ($row['missing']): ?><span class="fm-badge fm-badge--destructive"><?= V::e(t('File missing')) ?></span><?php endif; ?>
                  <?php if ((int)$row['link_count'] === 0): ?><span class="fm-badge fm-badge--warning"><?= V::e(t('Not attached')) ?></span><?php endif; ?>
                  <?php if ((int)$row['has_key'] === 1): ?><span class="fm-badge fm-badge--outline"><?= V::icon('key') ?><?= V::e(t('Password')) ?></span><?php endif; ?>
                </span>
              </div>
            </td>
            <td><span class="fm-badge fm-badge--secondary fm-mono"><?= V::e($row['mime_type'] ?: '—') ?></span></td>
            <td class="fm-num fm-nowrap"><?= V::e($row['is_url'] ? '—' : V::bytes($row['size'])) ?></td>
            <td class="fm-num"><?= (int)$row['link_count'] ?></td>
            <td class="fm-nowrap">
              <?= V::e(V::date($row['last_update'])) ?>
              <?php if (!empty($row['uploader'])): ?><span class="fm-muted fm-block"><?= V::e($row['uploader']) ?></span><?php endif; ?>
            </td>
            <td class="fm-right fm-nowrap">
              <a class="fm-btn fm-btn--outline fm-btn--sm" href="<?= V::e($detail) ?>"><?= V::label('eye', t('Detail')) ?></a>
              <?php if ($canWrite): ?>
                <a class="fm-btn fm-btn--ghost fm-btn--sm" href="<?= V::e(V::url(['fm' => 'edit', 'fid' => (int)$row['file_id']])) ?>"><?= V::label('pencil', t('Edit')) ?></a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php
    $from = ($page - 1) * $perPage + 1;
    $to = min($total, $page * $perPage);
    $window = 2;
    $start = max(1, $page - $window);
    $end = min($pages, $page + $window);
    $pageUrl = fn(int $p) => V::url(array_merge($query, ['page' => $p > 1 ? $p : null]));
    ?>
    <footer class="fm-pagination">
      <span class="fm-muted"><?= V::e(t('Showing %d–%d of %d', $from, $to, $total)) ?></span>
      <?php if ($pages > 1): ?>
      <nav class="fm-pages" aria-label="<?= V::e(t('Pagination')) ?>">
        <?php if ($page > 1): ?>
          <a class="fm-btn fm-btn--ghost fm-btn--sm" href="<?= V::e($pageUrl($page - 1)) ?>"><?= V::label('chevron-left', t('Previous')) ?></a>
        <?php endif; ?>
        <?php if ($start > 1): ?>
          <a class="fm-page" href="<?= V::e($pageUrl(1)) ?>">1</a>
          <?php if ($start > 2): ?><span class="fm-page-gap">…</span><?php endif; ?>
        <?php endif; ?>
        <?php for ($p = $start; $p <= $end; $p++): ?>
          <a class="fm-page <?= $p === $page ? 'is-active' : '' ?>" href="<?= V::e($pageUrl($p)) ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($end < $pages): ?>
          <?php if ($end < $pages - 1): ?><span class="fm-page-gap">…</span><?php endif; ?>
          <a class="fm-page" href="<?= V::e($pageUrl($pages)) ?>"><?= $pages ?></a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
          <a class="fm-btn fm-btn--ghost fm-btn--sm" href="<?= V::e($pageUrl($page + 1)) ?>"><span><?= V::e(t('Next')) ?></span><?= V::icon('chevron-right') ?></a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>
    </footer>
  <?php endif; ?>
</section>

<?php
/**
 * Cleanup tools page.
 *
 * @var string $tab        missing|orphan|untracked
 * @var array  $items
 * @var bool   $truncated
 * @var int    $limit
 * @var bool   $canWrite
 */
use SlimsFileManager\{Csrf, Disk, View as V};
use function SlimsFileManager\t;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

V::render('header', [
    'title' => t('Cleanup'),
    'subtitle' => t('Find broken, unused and unregistered attachment files.'),
    'icon' => 'eraser',
    'back' => V::url(),
]);

$tabs = [
    'missing' => ['file-x', t('Missing files'), t('Records whose physical file no longer exists in the repository.')],
    'orphan' => ['unlink', t('Not attached'), t('Records that are not attached to any bibliographic record.')],
    'untracked' => ['hard-drive', t('Unregistered files'), t('Files in the repository folder that no record points to.')],
];
?>
<nav class="fm-tabs" aria-label="<?= V::e(t('Cleanup')) ?>">
  <?php foreach ($tabs as $key => [$icon, $label]): ?>
    <a class="fm-tab <?= $tab === $key ? 'is-active' : '' ?>" href="<?= V::e(V::url(['fm' => 'cleanup', 'tab' => $key])) ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>>
      <?= V::label($icon, $label) ?>
    </a>
  <?php endforeach; ?>
</nav>

<section class="fm-card">
  <div class="fm-card-header">
    <h3 class="fm-card-title"><?= V::e($tabs[$tab][1]) ?> <span class="fm-badge fm-badge--secondary"><?= count($items) ?><?= $truncated ? '+' : '' ?></span></h3>
    <p class="fm-card-desc"><?= V::e($tabs[$tab][2]) ?></p>
  </div>

  <?php if ($truncated): ?>
    <div class="fm-card-content">
      <div class="fm-alert fm-alert--warning"><?= V::icon('info') ?><p><?= V::e(t('Only the first %d items are shown. Clean them up and reload to see more.', $limit)) ?></p></div>
    </div>
  <?php endif; ?>

  <?php if (!$items): ?>
    <div class="fm-empty">
      <span class="fm-empty-icon is-success"><?= V::icon('circle-check') ?></span>
      <p class="fm-empty-title"><?= V::e(t('All clean')) ?></p>
      <p class="fm-muted"><?= V::e(t('Nothing to clean up here.')) ?></p>
    </div>
  <?php else: ?>
    <form class="notAJAX" method="post" action="<?= V::e(V::url()) ?>" data-fm-form data-fm-max-select="100"
          data-fm-max-message="<?= V::e(t('You can process at most %d items at once.', 100)) ?>"
          data-fm-confirm="<?= V::e($tab === 'untracked' ? t('Delete the selected files from the repository? This cannot be undone.') : t('Delete the selected records? Their links to bibliographic records are removed too. This cannot be undone.')) ?>"
          data-fm-confirm-ok="<?= V::e(t('Delete')) ?>">
      <?= Csrf::field() ?>
      <?php if ($tab === 'untracked'): ?>
        <input type="hidden" name="fm_action" value="delete_untracked">
      <?php else: ?>
        <input type="hidden" name="fm_action" value="bulk_delete">
        <input type="hidden" name="tab" value="<?= V::e($tab) ?>">
      <?php endif; ?>

      <?php if ($canWrite): ?>
      <div class="fm-bulkbar" data-fm-bulkbar>
        <label class="fm-check">
          <input class="fm-checkbox" type="checkbox" data-fm-check-all aria-label="<?= V::e(t('Select all')) ?>">
          <span data-fm-selected-count><?= V::e(t('Select all')) ?></span>
        </label>
        <div class="fm-actions">
          <?php if ($tab === 'orphan'): ?>
            <label class="fm-check">
              <input class="fm-checkbox" type="checkbox" name="deletePhysical" value="1" checked>
              <span><?= V::e(t('Also delete physical files')) ?></span>
            </label>
          <?php endif; ?>
          <button class="fm-btn fm-btn--destructive fm-btn--sm" type="submit" data-fm-bulk-submit disabled>
            <?= V::label('trash', $tab === 'untracked' ? t('Delete selected files') : t('Delete selected records')) ?>
          </button>
        </div>
      </div>
      <p class="fm-hint fm-px"><?= V::e(t('Up to 100 items per action.')) ?></p>
      <?php endif; ?>

      <div class="fm-table-wrap">
        <table class="fm-table">
          <thead>
            <tr>
              <?php if ($canWrite): ?><th scope="col" class="fm-col-check"><span class="fm-sr-only"><?= V::e(t('Select')) ?></span></th><?php endif; ?>
              <?php if ($tab === 'untracked'): ?>
                <th scope="col"><?= V::e(t('File')) ?></th>
                <th scope="col" class="fm-num"><?= V::e(t('Size')) ?></th>
                <th scope="col"><?= V::e(t('Modified')) ?></th>
              <?php else: ?>
                <th scope="col"><?= V::e(t('Record')) ?></th>
                <th scope="col"><?= V::e(t('Stored file')) ?></th>
                <?php if ($tab === 'missing'): ?><th scope="col" class="fm-num"><?= V::e(t('Records')) ?></th><?php endif; ?>
                <th scope="col"><?= V::e(t('Last updated')) ?></th>
                <th scope="col" class="fm-right"><?= V::e(t('Actions')) ?></th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $item): ?>
            <?php if ($tab === 'untracked'): ?>
            <tr>
              <?php if ($canWrite): ?>
                <td class="fm-col-check"><input class="fm-checkbox" type="checkbox" name="paths[]" value="<?= V::e($item['path']) ?>" data-fm-check aria-label="<?= V::e($item['path']) ?>"></td>
              <?php endif; ?>
              <td><span class="fm-mono fm-break fm-block"><?= V::e($item['path']) ?></span></td>
              <td class="fm-num fm-nowrap"><?= V::e(V::bytes($item['size'])) ?></td>
              <td class="fm-nowrap"><?= V::e($item['mtime'] ? date('d M Y H:i', $item['mtime']) : '—') ?></td>
            </tr>
            <?php else: ?>
            <tr>
              <?php if ($canWrite): ?>
                <td class="fm-col-check"><input class="fm-checkbox" type="checkbox" name="ids[]" value="<?= (int)$item['file_id'] ?>" data-fm-check aria-label="<?= V::e($item['file_title']) ?>"></td>
              <?php endif; ?>
              <td>
                <span class="fm-strong fm-clamp-2" title="<?= V::e($item['file_title']) ?>"><?= V::e($item['file_title'] !== '' ? $item['file_title'] : t('(untitled)')) ?></span>
                <span class="fm-muted fm-block">#<?= (int)$item['file_id'] ?></span>
              </td>
              <?php $stored = !empty($item['is_url']) ? $item['file_name'] : Disk::key($item['file_dir'], $item['file_name']); ?>
              <td class="fm-col-path">
                <span class="fm-mono fm-truncate" title="<?= V::e($stored) ?>"><?= V::e($stored) ?></span>
                <?php if ($tab === 'orphan' && empty($item['exists'])): ?><span class="fm-badges"><span class="fm-badge fm-badge--destructive"><?= V::e(t('File missing')) ?></span></span><?php endif; ?>
              </td>
              <?php if ($tab === 'missing'): ?><td class="fm-num"><?= V::e(V::number((int)$item['link_count'])) ?></td><?php endif; ?>
              <td class="fm-nowrap"><?= V::e(V::date($item['last_update'])) ?></td>
              <td class="fm-right"><a class="fm-btn fm-btn--ghost fm-btn--sm" href="<?= V::e(V::url(['fm' => 'detail', 'fid' => (int)$item['file_id']])) ?>"><?= V::label('eye', t('Detail')) ?></a></td>
            </tr>
            <?php endif; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </form>
  <?php endif; ?>
</section>

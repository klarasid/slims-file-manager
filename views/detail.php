<?php
/**
 * File detail page.
 *
 * @var array       $file
 * @var bool        $isUrl, $exists, $canWrite
 * @var int|null    $size
 * @var string      $mime
 * @var int         $shared
 * @var array       $relations
 * @var array       $memberTypes
 * @var string|null $safeUrl
 */
use SlimsFileManager\{Csrf, Disk, View as V};
use function SlimsFileManager\t;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

$fid = (int)$file['file_id'];
$title = $file['file_title'] !== '' ? $file['file_title'] : t('(untitled)');
$placementLabels = ['link' => t('Link'), 'popup' => t('Popup'), 'embed' => t('Embed')];

$actions = '';
if (!$isUrl && $exists) {
    $actions .= '<a class="fm-btn fm-btn--outline notAJAX" target="_blank" rel="noopener" href="' . V::e(V::url(['fm' => 'stream', 'fid' => $fid])) . '">' . V::label('eye', t('Preview')) . '</a>';
    $actions .= '<a class="fm-btn fm-btn--outline notAJAX" target="_blank" rel="noopener" href="' . V::e(V::url(['fm' => 'stream', 'fid' => $fid, 'mode' => 'download'])) . '">' . V::label('download', t('Download')) . '</a>';
}
if ($isUrl && $safeUrl) {
    $actions .= '<a class="fm-btn fm-btn--outline notAJAX" target="_blank" rel="noopener noreferrer" href="' . V::e($safeUrl) . '">' . V::label('external-link', t('Open URL')) . '</a>';
}
if ($canWrite) {
    $actions .= '<a class="fm-btn fm-btn--secondary" href="' . V::e(V::url(['fm' => 'edit', 'fid' => $fid])) . '">' . V::label('pencil', t('Edit')) . '</a>';
    if (!$isUrl) {
        $actions .= '<a class="fm-btn fm-btn--secondary" href="' . V::e(V::url(['fm' => 'replace', 'fid' => $fid])) . '">' . V::label('refresh', t('Replace File')) . '</a>';
    }
    $actions .= '<button type="button" class="fm-btn fm-btn--destructive" data-fm-open="fm-delete-dialog">' . V::label('trash', t('Delete')) . '</button>';
}

V::render('header', [
    'title' => $title,
    'subtitle' => t('Attachment #%d', $fid),
    'icon' => $isUrl ? 'globe' : 'file-text',
    'back' => V::url(),
    'actions' => $actions,
]);
?>

<?php if (!$isUrl && !$exists): ?>
  <div class="fm-alert fm-alert--destructive" role="alert">
    <?= V::icon('alert') ?>
    <div>
      <p class="fm-alert-title"><?= V::e(t('Physical file not found')) ?></p>
      <p><?= V::e(t('The record exists but the file is missing from the repository. Replace the file or delete this record.')) ?></p>
    </div>
  </div>
<?php endif; ?>

<div class="fm-grid fm-grid--detail">
  <section class="fm-card">
    <div class="fm-card-header">
      <h3 class="fm-card-title"><?= V::e(t('File information')) ?></h3>
    </div>
    <div class="fm-card-content">
      <dl class="fm-dl">
        <dt><?= V::e(t('Title')) ?></dt>
        <dd><?= V::e($title) ?></dd>

        <?php if ($isUrl): ?>
          <dt><?= V::e(t('URL')) ?></dt>
          <dd class="fm-break"><?= V::e($file['file_url']) ?></dd>
        <?php else: ?>
          <dt><?= V::e(t('Stored file')) ?></dt>
          <dd class="fm-mono fm-break"><?= V::e($file['file_name']) ?></dd>
          <dt><?= V::e(t('Folder')) ?></dt>
          <dd class="fm-mono"><?= V::e((Disk::normalizeDir($file['file_dir']) ?? '') === '' ? t('(repository root)') : Disk::normalizeDir($file['file_dir'])) ?></dd>
          <dt><?= V::e(t('Size')) ?></dt>
          <dd><?= V::e(V::bytes($size)) ?></dd>
        <?php endif; ?>

        <dt><?= V::e(t('MIME type')) ?></dt>
        <dd>
          <span class="fm-badge fm-badge--secondary fm-mono"><?= V::e($file['mime_type'] ?: '—') ?></span>
          <?php if (!$isUrl && $file['mime_type'] !== $mime): ?>
            <span class="fm-muted"><?= V::e(t('served as %s', $mime)) ?></span>
          <?php endif; ?>
        </dd>

        <dt><?= V::e(t('File password')) ?></dt>
        <dd><?= !empty($file['file_key'])
              ? '<span class="fm-badge fm-badge--outline">' . V::icon('key') . V::e(t('Set')) . '</span>'
              : '<span class="fm-muted">' . V::e(t('None')) . '</span>' ?></dd>

        <dt><?= V::e(t('Uploaded by')) ?></dt>
        <dd><?= V::e($file['uploader'] ?: '—') ?></dd>

        <dt><?= V::e(t('Created')) ?></dt>
        <dd><?= V::e(V::date($file['input_date'])) ?></dd>

        <dt><?= V::e(t('Last updated')) ?></dt>
        <dd><?= V::e(V::date($file['last_update'])) ?></dd>

        <dt><?= V::e(t('Description')) ?></dt>
        <dd class="fm-prewrap"><?= $file['file_desc'] !== null && $file['file_desc'] !== '' ? V::e($file['file_desc']) : '<span class="fm-muted">—</span>' ?></dd>
      </dl>
      <?php if ($shared > 0): ?>
        <p class="fm-hint fm-mt"><?= V::icon('info') ?> <?= V::e(t('%d other record(s) point to the same physical file.', $shared)) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <section class="fm-card">
    <div class="fm-card-header">
      <h3 class="fm-card-title"><?= V::e(t('Bibliographic records')) ?> <span class="fm-badge fm-badge--secondary"><?= count($relations) ?></span></h3>
      <p class="fm-card-desc"><?= V::e(t('Records that use this file as an attachment.')) ?></p>
    </div>
    <div class="fm-card-content fm-p0">
      <?php if (!$relations): ?>
        <div class="fm-empty fm-empty--sm">
          <span class="fm-empty-icon"><?= V::icon('book') ?></span>
          <p class="fm-muted"><?= V::e(t('This file is not attached to any record.')) ?></p>
        </div>
      <?php else: ?>
        <div class="fm-table-wrap">
          <table class="fm-table">
            <thead>
              <tr>
                <th scope="col"><?= V::e(t('Title')) ?></th>
                <th scope="col"><?= V::e(t('Placement')) ?></th>
                <th scope="col"><?= V::e(t('Access')) ?></th>
                <?php if ($canWrite): ?><th scope="col" class="fm-right"><?= V::e(t('Actions')) ?></th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($relations as $rel): $bid = (int)$rel['biblio_id']; ?>
              <tr>
                <td>
                  <span class="fm-strong"><?= V::e($rel['title'] ?? t('(record #%d not found)', $bid)) ?></span>
                  <span class="fm-muted fm-block">#<?= $bid ?><?= !empty($rel['isbn_issn']) ? ' · ' . V::e($rel['isbn_issn']) : '' ?><?= !empty($rel['publish_year']) ? ' · ' . V::e($rel['publish_year']) : '' ?></span>
                </td>
                <td><span class="fm-badge fm-badge--outline"><?= V::e($placementLabels[$rel['placement']] ?? ($rel['placement'] ?: '—')) ?></span></td>
                <td>
                  <?php if ($rel['access_type'] === 'private'): ?>
                    <span class="fm-badge fm-badge--warning"><?= V::icon('shield') ?><?= V::e(t('Private')) ?></span>
                  <?php else: ?>
                    <span class="fm-badge fm-badge--success"><?= V::icon('globe') ?><?= V::e(t('Public')) ?></span>
                  <?php endif; ?>
                  <?php if ($rel['limit_names']): ?>
                    <span class="fm-muted fm-block"><?= V::e(implode(', ', $rel['limit_names'])) ?></span>
                  <?php endif; ?>
                </td>
                <?php if ($canWrite): ?>
                <td class="fm-right fm-nowrap">
                  <a class="fm-btn fm-btn--ghost fm-btn--sm" href="<?= V::e(V::url(['fm' => 'relation', 'fid' => $fid, 'bid' => $bid])) ?>"><?= V::label('shield', t('Access')) ?></a>
                  <form class="fm-inline notAJAX" method="post" action="<?= V::e(V::url()) ?>" data-fm-form
                        data-fm-confirm="<?= V::e(t('Detach this file from “%s”?', $rel['title'] ?? '#' . $bid)) ?>"
                        data-fm-confirm-ok="<?= V::e(t('Detach')) ?>">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="fm_action" value="detach">
                    <input type="hidden" name="fid" value="<?= $fid ?>">
                    <input type="hidden" name="bid" value="<?= $bid ?>">
                    <button class="fm-btn fm-btn--ghost fm-btn--sm fm-text-danger" type="submit"><?= V::label('unlink', t('Detach')) ?></button>
                  </form>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($canWrite): ?>
    <form class="fm-card-footer fm-stack notAJAX" method="post" action="<?= V::e(V::url()) ?>" data-fm-form autocomplete="off">
      <?= Csrf::field() ?>
      <input type="hidden" name="fm_action" value="attach">
      <input type="hidden" name="fid" value="<?= $fid ?>">
      <h4 class="fm-section-title"><?= V::label('link', t('Attach to a record')) ?></h4>

      <div class="fm-field fm-combobox" data-fm-biblio-search data-url="<?= V::e(V::url(['fm' => 'biblio_search', 'fid' => $fid])) ?>">
        <label class="fm-label" for="fm-biblio-q"><?= V::e(t('Bibliographic record')) ?></label>
        <div class="fm-input-icon">
          <?= V::icon('search') ?>
          <input class="fm-input" id="fm-biblio-q" type="search" maxlength="100" role="combobox" aria-expanded="false" aria-controls="fm-biblio-results"
                 placeholder="<?= V::e(t('Type a title or ISBN (min. 2 characters)…')) ?>" data-fm-biblio-input>
        </div>
        <input type="hidden" name="bid" value="" data-fm-biblio-id>
        <ul class="fm-combobox-list" id="fm-biblio-results" role="listbox" hidden data-fm-biblio-results></ul>
        <p class="fm-selected" hidden data-fm-biblio-selected></p>
        <p class="fm-error" data-error-for="bid"></p>
      </div>

      <?php V::render('access_fields', [
          'prefix' => 'fm-attach',
          'placement' => 'link',
          'accessType' => 'public',
          'limit' => [],
          'memberTypes' => $memberTypes,
      ]); ?>

      <div>
        <button class="fm-btn fm-btn--default" type="submit"><?= V::label('link', t('Attach')) ?></button>
      </div>
    </form>
    <?php endif; ?>
  </section>
</div>

<?php if ($canWrite): ?>
<dialog class="fm-dialog" id="fm-delete-dialog" aria-labelledby="fm-delete-title">
  <form class="notAJAX" method="post" action="<?= V::e(V::url()) ?>" data-fm-form>
    <?= Csrf::field() ?>
    <input type="hidden" name="fm_action" value="delete">
    <input type="hidden" name="fid" value="<?= $fid ?>">
    <div class="fm-dialog-header">
      <h3 class="fm-dialog-title" id="fm-delete-title"><?= V::e(t('Delete this file?')) ?></h3>
      <p class="fm-dialog-desc"><?= V::e(t('The record “%s” will be removed permanently.', $title)) ?></p>
    </div>

    <?php if ($relations): ?>
      <div class="fm-alert fm-alert--warning">
        <?= V::icon('alert') ?>
        <div>
          <p class="fm-alert-title"><?= V::e(t('Still attached to %d record(s)', count($relations))) ?></p>
          <ul class="fm-list">
            <?php foreach (array_slice($relations, 0, 8) as $rel): ?>
              <li><?= V::e($rel['title'] ?? '#' . (int)$rel['biblio_id']) ?></li>
            <?php endforeach; ?>
            <?php if (count($relations) > 8): ?><li><?= V::e(t('and %d more…', count($relations) - 8)) ?></li><?php endif; ?>
          </ul>
        </div>
      </div>
      <label class="fm-check">
        <input class="fm-checkbox" type="checkbox" name="confirmDetach" value="1" required>
        <span><?= V::e(t('Detach it from all these records and delete it')) ?></span>
      </label>
      <p class="fm-error" data-error-for="confirmDetach"></p>
    <?php endif; ?>

    <?php if (!$isUrl): ?>
      <label class="fm-check <?= $shared > 0 || !$exists ? 'is-disabled' : '' ?>">
        <input class="fm-checkbox" type="checkbox" name="deletePhysical" value="1" <?= $shared > 0 || !$exists ? 'disabled' : 'checked' ?>>
        <span>
          <?= V::e(t('Also delete the physical file from the repository')) ?>
          <?php if ($shared > 0): ?><span class="fm-hint fm-block"><?= V::e(t('Disabled: another record uses the same file.')) ?></span><?php endif; ?>
          <?php if (!$exists): ?><span class="fm-hint fm-block"><?= V::e(t('The file is already missing.')) ?></span><?php endif; ?>
        </span>
      </label>
    <?php endif; ?>

    <div class="fm-dialog-footer">
      <button class="fm-btn fm-btn--outline" type="button" data-fm-close><?= V::label('x', t('Cancel')) ?></button>
      <button class="fm-btn fm-btn--destructive" type="submit"><?= V::label('trash', t('Delete')) ?></button>
    </div>
  </form>
</dialog>
<?php endif; ?>

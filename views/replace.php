<?php
/**
 * Replace physical file page.
 *
 * @var array    $file
 * @var bool     $exists
 * @var string[] $extensions
 * @var int      $maxBytes
 */
use SlimsFileManager\{Csrf, Disk, View as V};
use function SlimsFileManager\t;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

$fid = (int)$file['file_id'];
$detail = V::url(['fm' => 'detail', 'fid' => $fid]);

V::render('header', [
    'title' => t('Replace File'),
    'subtitle' => $file['file_title'],
    'icon' => 'refresh',
    'back' => $detail,
]);
?>
<div class="fm-alert">
  <?= V::icon('info') ?>
  <div>
    <p class="fm-alert-title"><?= V::e(t('Links stay intact')) ?></p>
    <p><?= V::e(t('The record keeps its ID, title and attachments; only the stored file is swapped. The old file is removed unless another record uses it.')) ?></p>
    <p class="fm-mono fm-muted"><?= V::e(Disk::key($file['file_dir'], $file['file_name'])) ?><?= $exists ? '' : ' — ' . V::e(t('missing')) ?></p>
  </div>
</div>

<form class="fm-card fm-form notAJAX" method="post" action="<?= V::e(V::url()) ?>" enctype="multipart/form-data" data-fm-form>
  <?= Csrf::field() ?>
  <input type="hidden" name="fm_action" value="replace">
  <input type="hidden" name="fid" value="<?= $fid ?>">
  <div class="fm-card-content">
    <?php V::render('dropzone', ['extensions' => $extensions, 'maxBytes' => $maxBytes]); ?>
  </div>
  <div class="fm-card-footer fm-justify-end">
    <a class="fm-btn fm-btn--outline" href="<?= V::e($detail) ?>"><?= V::label('x', t('Cancel')) ?></a>
    <button class="fm-btn fm-btn--default" type="submit"><?= V::label('refresh', t('Replace File')) ?></button>
  </div>
</form>

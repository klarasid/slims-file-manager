<?php
/**
 * Edit metadata page.
 *
 * @var array $file
 */
use SlimsFileManager\{Controller, Csrf, View as V};
use function SlimsFileManager\t;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

$fid = (int)$file['file_id'];
$isUrl = Controller::isUrl($file);
$detail = V::url(['fm' => 'detail', 'fid' => $fid]);

V::render('header', [
    'title' => t('Edit File'),
    'subtitle' => $file['file_title'],
    'icon' => 'pencil',
    'back' => $detail,
]);
?>
<form class="fm-card fm-form notAJAX" method="post" action="<?= V::e(V::url()) ?>" data-fm-form autocomplete="off">
  <?= Csrf::field() ?>
  <input type="hidden" name="fm_action" value="update">
  <input type="hidden" name="fid" value="<?= $fid ?>">

  <div class="fm-card-content fm-stack">
    <div class="fm-field">
      <label class="fm-label" for="fm-title"><?= V::e(t('Title')) ?> <span class="fm-required">*</span></label>
      <input class="fm-input" id="fm-title" name="fileTitle" maxlength="500" required value="<?= V::e($file['file_title']) ?>">
      <p class="fm-error" data-error-for="fileTitle"></p>
    </div>

    <?php if ($isUrl): ?>
    <div class="fm-field">
      <label class="fm-label" for="fm-url"><?= V::e(t('URL')) ?> <span class="fm-required">*</span></label>
      <input class="fm-input" id="fm-url" name="fileURL" type="url" maxlength="2000" required value="<?= V::e($file['file_url']) ?>" placeholder="https://">
      <p class="fm-hint"><?= V::e(t('Only public http:// or https:// addresses are accepted.')) ?></p>
      <p class="fm-error" data-error-for="fileURL"></p>
    </div>
    <?php endif; ?>

    <div class="fm-field">
      <label class="fm-label" for="fm-desc"><?= V::e(t('Description')) ?></label>
      <textarea class="fm-textarea" id="fm-desc" name="fileDesc" rows="4" maxlength="5000"><?= V::e($file['file_desc']) ?></textarea>
      <p class="fm-error" data-error-for="fileDesc"></p>
    </div>

    <?php if (!$isUrl): ?>
    <div class="fm-field">
      <label class="fm-label" for="fm-key"><?= V::e(t('File password')) ?></label>
      <div class="fm-input-group">
        <input class="fm-input" id="fm-key" name="fileKey" type="password" maxlength="100" autocomplete="new-password"
               placeholder="<?= V::e(!empty($file['file_key']) ? t('•••••• (leave empty to keep the current password)') : t('No password')) ?>">
        <button class="fm-btn fm-btn--outline" type="button" data-fm-toggle-password="fm-key"
                data-label-show="<?= V::e(t('Show')) ?>" data-label-hide="<?= V::e(t('Hide')) ?>"><?= V::label('eye', t('Show')) ?></button>
      </div>
      <p class="fm-hint"><?= V::e(t('Used by the PDF viewer to open protected documents.')) ?></p>
      <?php if (!empty($file['file_key'])): ?>
        <label class="fm-check">
          <input class="fm-checkbox" type="checkbox" name="removeKey" value="1">
          <span><?= V::e(t('Remove the current password')) ?></span>
        </label>
      <?php endif; ?>
      <p class="fm-error" data-error-for="fileKey"></p>
    </div>
    <?php endif; ?>
  </div>

  <div class="fm-card-footer fm-justify-end">
    <a class="fm-btn fm-btn--outline" href="<?= V::e($detail) ?>"><?= V::label('x', t('Cancel')) ?></a>
    <button class="fm-btn fm-btn--default" type="submit"><?= V::label('save', t('Save Changes')) ?></button>
  </div>
</form>

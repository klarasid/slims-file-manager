<?php
/**
 * Upload page (file or URL attachment).
 *
 * @var string[] $dirs
 * @var string[] $extensions
 * @var int      $maxBytes
 */
use SlimsFileManager\{Csrf, View as V};
use function SlimsFileManager\t;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

V::render('header', [
    'title' => t('Upload File'),
    'subtitle' => t('Add a new attachment to the repository. Attach it to records from its detail page.'),
    'icon' => 'upload',
    'back' => V::url(),
]);
?>
<div class="fm-tabs" role="tablist" data-fm-tabs>
  <button class="fm-tab is-active" type="button" role="tab" aria-selected="true" data-fm-tab="file"><?= V::label('file', t('File')) ?></button>
  <button class="fm-tab" type="button" role="tab" aria-selected="false" data-fm-tab="url"><?= V::label('globe', t('URL')) ?></button>
</div>

<form class="fm-card fm-form notAJAX" method="post" action="<?= V::e(V::url()) ?>" enctype="multipart/form-data" data-fm-form data-fm-panel="file" autocomplete="off">
  <?= Csrf::field() ?>
  <input type="hidden" name="fm_action" value="upload">
  <input type="hidden" name="source" value="file">
  <div class="fm-card-content fm-stack">
    <?php V::render('dropzone', ['extensions' => $extensions, 'maxBytes' => $maxBytes]); ?>

    <div class="fm-grid fm-grid--2">
      <div class="fm-field">
        <label class="fm-label" for="fm-up-title"><?= V::e(t('Title')) ?></label>
        <input class="fm-input" id="fm-up-title" name="fileTitle" maxlength="500" placeholder="<?= V::e(t('Defaults to the original file name')) ?>">
        <p class="fm-error" data-error-for="fileTitle"></p>
      </div>
      <div class="fm-field">
        <label class="fm-label" for="fm-up-dir"><?= V::e(t('Destination folder')) ?></label>
        <select class="fm-select fm-w-full" id="fm-up-dir" name="fileDir">
          <?php foreach ($dirs as $d): ?>
            <option value="<?= V::e($d) ?>"><?= V::e($d === '' ? t('(repository root)') : $d) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="fm-error" data-error-for="fileDir"></p>
      </div>
    </div>

    <div class="fm-field">
      <label class="fm-label" for="fm-up-desc"><?= V::e(t('Description')) ?></label>
      <textarea class="fm-textarea" id="fm-up-desc" name="fileDesc" rows="3" maxlength="5000"></textarea>
    </div>

    <div class="fm-field">
      <label class="fm-label" for="fm-up-key"><?= V::e(t('File password')) ?></label>
      <div class="fm-input-group">
        <input class="fm-input" id="fm-up-key" name="fileKey" type="password" maxlength="100" autocomplete="new-password" placeholder="<?= V::e(t('Optional')) ?>">
        <button class="fm-btn fm-btn--outline" type="button" data-fm-toggle-password="fm-up-key"
                data-label-show="<?= V::e(t('Show')) ?>" data-label-hide="<?= V::e(t('Hide')) ?>"><?= V::label('eye', t('Show')) ?></button>
      </div>
      <p class="fm-hint"><?= V::e(t('Used by the PDF viewer to open protected documents.')) ?></p>
    </div>
  </div>
  <div class="fm-card-footer fm-justify-end">
    <a class="fm-btn fm-btn--outline" href="<?= V::e(V::url()) ?>"><?= V::label('x', t('Cancel')) ?></a>
    <button class="fm-btn fm-btn--default" type="submit"><?= V::label('upload', t('Upload')) ?></button>
  </div>
</form>

<form class="fm-card fm-form notAJAX" method="post" action="<?= V::e(V::url()) ?>" data-fm-form data-fm-panel="url" hidden autocomplete="off">
  <?= Csrf::field() ?>
  <input type="hidden" name="fm_action" value="upload">
  <input type="hidden" name="source" value="url">
  <div class="fm-card-content fm-stack">
    <div class="fm-field">
      <label class="fm-label" for="fm-url-url"><?= V::e(t('URL')) ?> <span class="fm-required">*</span></label>
      <input class="fm-input" id="fm-url-url" name="fileURL" type="url" maxlength="2000" required placeholder="https://">
      <p class="fm-hint"><?= V::e(t('Only public http:// or https:// addresses are accepted.')) ?></p>
      <p class="fm-error" data-error-for="fileURL"></p>
    </div>
    <div class="fm-field">
      <label class="fm-label" for="fm-url-title"><?= V::e(t('Title')) ?></label>
      <input class="fm-input" id="fm-url-title" name="fileTitle" maxlength="500" placeholder="<?= V::e(t('Defaults to the host name')) ?>">
      <p class="fm-error" data-error-for="fileTitle"></p>
    </div>
    <div class="fm-field">
      <label class="fm-label" for="fm-url-desc"><?= V::e(t('Description')) ?></label>
      <textarea class="fm-textarea" id="fm-url-desc" name="fileDesc" rows="3" maxlength="5000"></textarea>
    </div>
  </div>
  <div class="fm-card-footer fm-justify-end">
    <a class="fm-btn fm-btn--outline" href="<?= V::e(V::url()) ?>"><?= V::label('x', t('Cancel')) ?></a>
    <button class="fm-btn fm-btn--default" type="submit"><?= V::label('plus', t('Add URL')) ?></button>
  </div>
</form>

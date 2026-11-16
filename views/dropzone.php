<?php
/**
 * File input styled as a drop zone.
 *
 * @var string[] $extensions
 * @var int      $maxBytes
 */
use SlimsFileManager\View as V;
use function SlimsFileManager\t;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

$accept = implode(',', array_map(fn($e) => '.' . $e, $extensions));
?>
<div class="fm-field">
  <label class="fm-dropzone" data-fm-dropzone>
    <input class="fm-sr-only" type="file" name="file2attach" required accept="<?= V::e($accept) ?>" data-max-bytes="<?= (int)$maxBytes ?>"
           data-too-large="<?= V::e(t('The file is larger than the maximum allowed size (%s).', V::bytes($maxBytes))) ?>">
    <span class="fm-dropzone-icon"><?= V::icon('upload') ?></span>
    <span class="fm-strong" data-fm-dropzone-label><?= V::e(t('Click to choose a file or drag it here')) ?></span>
    <span class="fm-muted"><?= V::e(t('Max. %s · %s', V::bytes($maxBytes), implode(', ', $extensions))) ?></span>
  </label>
  <p class="fm-error" data-error-for="file2attach"></p>
</div>

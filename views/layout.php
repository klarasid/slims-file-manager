<?php
/**
 * @var string $template
 * @var array  $data
 */
use SlimsFileManager\View as V;
use function SlimsFileManager\t;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');
?>
<link rel="stylesheet" href="<?= V::e(V::asset('fm.css')) ?>">
<div class="fm-root" data-fm-root data-fm-view="<?= V::e($template) ?>"
     data-fm-i18n="<?= V::e(V::json([
         'error' => t('The request could not be processed. Please try again.'),
         'session' => t('Your session may have expired. Please reload the page.'),
         'searching' => t('Searching…'),
         'noResults' => t('No matching records.'),
         'selected' => t('%d selected'),
         'choose' => t('Choose'),
         'cancel' => t('Cancel'),
         'confirm' => t('Continue'),
         'confirmTitle' => t('Are you sure?'),
         'clear' => t('Change'),
         'selectAll' => t('Select all'),
         'selectFirst' => t('Select at least one item.'),
     ])) ?>">
  <?php V::render($template, $data); ?>
</div>
<script src="<?= V::e(V::asset('fm.js')) ?>"></script>

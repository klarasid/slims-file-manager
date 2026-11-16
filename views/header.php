<?php
/**
 * Page header partial.
 *
 * @var string      $title
 * @var string      $subtitle
 * @var string|null $back     URL of the previous page
 * @var string      $actions  pre-escaped HTML of action buttons
 */
use SlimsFileManager\View as V;
use function SlimsFileManager\t;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');
?>
<header class="fm-page-header">
  <div class="fm-page-heading">
    <?php if (!empty($back)): ?>
      <a class="fm-btn fm-btn--ghost fm-btn--sm fm-back" href="<?= V::e($back) ?>"><?= V::label('arrow-left', t('Back')) ?></a>
    <?php endif; ?>
    <div class="fm-page-title-row">
      <span class="fm-page-icon"><?= V::icon($icon ?? 'folder-open') ?></span>
      <div class="fm-min-0">
        <h2 class="fm-title fm-clamp-2" title="<?= V::e($title) ?>"><?= V::e($title) ?></h2>
        <?php if (!empty($subtitle)): ?><p class="fm-subtitle fm-clamp-2" title="<?= V::e($subtitle) ?>"><?= V::e($subtitle) ?></p><?php endif; ?>
      </div>
    </div>
  </div>
  <?php if (!empty($actions)): ?><div class="fm-actions"><?= $actions ?></div><?php endif; ?>
</header>

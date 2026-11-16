<?php
/**
 * Generic message page.
 *
 * @var string $title
 * @var string $message
 * @var string $variant  warning|danger
 */
use SlimsFileManager\View as V;
use function SlimsFileManager\t;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');
?>
<div class="fm-card">
  <div class="fm-empty">
    <span class="fm-empty-icon <?= $variant === 'danger' ? 'is-danger' : '' ?>"><?= V::icon($variant === 'danger' ? 'circle-x' : 'alert') ?></span>
    <p class="fm-empty-title"><?= V::e($title) ?></p>
    <p class="fm-muted"><?= V::e($message) ?></p>
    <a class="fm-btn fm-btn--outline" href="<?= V::e(V::url()) ?>"><?= V::label('arrow-left', t('Back to File Manager')) ?></a>
  </div>
</div>

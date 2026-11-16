<?php
/**
 * Placement + access fields for a biblio attachment.
 *
 * @var string           $prefix       unique id prefix
 * @var string           $placement
 * @var string           $accessType
 * @var int[]            $limit
 * @var array<int,string> $memberTypes
 */
use SlimsFileManager\View as V;
use function SlimsFileManager\t;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

$placements = ['link' => t('Link'), 'popup' => t('Popup'), 'embed' => t('Embed')];
?>
<div class="fm-grid fm-grid--2">
  <div class="fm-field">
    <label class="fm-label" for="<?= V::e($prefix) ?>-placement"><?= V::e(t('Placement')) ?></label>
    <select class="fm-select fm-w-full" id="<?= V::e($prefix) ?>-placement" name="placement">
      <?php foreach ($placements as $value => $label): ?>
        <option value="<?= V::e($value) ?>" <?= $placement === $value ? 'selected' : '' ?>><?= V::e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="fm-hint"><?= V::e(t('How the attachment is shown on the OPAC detail page.')) ?></p>
  </div>

  <fieldset class="fm-field">
    <legend class="fm-label"><?= V::e(t('Access')) ?></legend>
    <div class="fm-segmented" role="radiogroup">
      <label class="fm-segment">
        <input type="radio" name="accessType" value="public" <?= $accessType !== 'private' ? 'checked' : '' ?>>
        <span><?= V::icon('globe') ?><?= V::e(t('Public')) ?></span>
      </label>
      <label class="fm-segment">
        <input type="radio" name="accessType" value="private" <?= $accessType === 'private' ? 'checked' : '' ?>>
        <span><?= V::icon('shield') ?><?= V::e(t('Private')) ?></span>
      </label>
    </div>
    <p class="fm-hint"><?= V::e(t('Private attachments are hidden from the OPAC.')) ?></p>
  </fieldset>
</div>

<?php if ($memberTypes): ?>
<fieldset class="fm-field" data-fm-show-if="accessType=public">
  <legend class="fm-label"><?= V::e(t('Limit to member types')) ?></legend>
  <div class="fm-check-grid">
    <?php foreach ($memberTypes as $typeId => $typeName): ?>
      <label class="fm-check">
        <input class="fm-checkbox" type="checkbox" name="accLimit[]" value="<?= (int)$typeId ?>" <?= in_array((int)$typeId, $limit, true) ? 'checked' : '' ?>>
        <span><?= V::e($typeName) ?></span>
      </label>
    <?php endforeach; ?>
  </div>
  <p class="fm-hint"><?= V::e(t('Leave everything unchecked to allow all visitors.')) ?></p>
</fieldset>
<?php endif; ?>

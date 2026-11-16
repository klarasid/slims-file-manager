<?php
/**
 * Edit attachment (relation) settings.
 *
 * @var array $file
 * @var array $relation
 * @var array $memberTypes
 */
use SlimsFileManager\{Controller, Csrf, View as V};
use function SlimsFileManager\t;

defined('INDEX_AUTH') or exit('Direct access is not allowed!');

$fid = (int)$file['file_id'];
$bid = (int)$relation['biblio_id'];
$detail = V::url(['fm' => 'detail', 'fid' => $fid]);

V::render('header', [
    'title' => t('Attachment Access'),
    'subtitle' => t('%s → %s', $file['file_title'], $relation['title'] ?? '#' . $bid),
    'icon' => 'shield',
    'back' => $detail,
]);
?>
<form class="fm-card fm-form notAJAX" method="post" action="<?= V::e(V::url()) ?>" data-fm-form>
  <?= Csrf::field() ?>
  <input type="hidden" name="fm_action" value="relation_update">
  <input type="hidden" name="fid" value="<?= $fid ?>">
  <input type="hidden" name="bid" value="<?= $bid ?>">
  <div class="fm-card-content fm-stack">
    <?php V::render('access_fields', [
        'prefix' => 'fm-rel',
        'placement' => (string)$relation['placement'],
        'accessType' => (string)$relation['access_type'],
        'limit' => Controller::decodeLimit($relation['access_limit']),
        'memberTypes' => $memberTypes,
    ]); ?>
  </div>
  <div class="fm-card-footer fm-justify-end">
    <a class="fm-btn fm-btn--outline" href="<?= V::e($detail) ?>"><?= V::label('x', t('Cancel')) ?></a>
    <button class="fm-btn fm-btn--default" type="submit"><?= V::label('save', t('Save Changes')) ?></button>
  </div>
</form>

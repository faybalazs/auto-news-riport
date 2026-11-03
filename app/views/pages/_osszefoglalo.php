<?php
/**
 * Vezetői összefoglaló kártya (Havi cikkek és Áttekintés). Új formában: bevezető + tagolt pontok; a régi
 * (Sheetből átvett vagy korábbi) hónapoknál a „Címke: érték” sorok és a Részletek bekezdés.
 *
 * @var \Xperion\Auth\Access $access @var callable $e @var callable $ic @var array|null $rep @var string $month @var string $monthLabel
 */
$canRegen = $access->can('decide');
$when = $rep ? ($rep['updated_at'] !== '' ? $rep['updated_at'] : $rep['created_at']) : '';
?>
<section class="card widget sum-card" aria-labelledby="w-sum" id="sumCard">
  <div class="widget-head">
    <div class="wh"><span class="ic-tile t-purple" aria-hidden="true"><?= $ic('sparkle', 'icon') ?></span><div><h2 id="w-sum">Vezetői összefoglaló</h2><p class="meta"><?= $e($monthLabel) ?><?= $when !== '' ? ' · ' . $e(\Xperion\Web\View::dateHu($when)) . ($rep['updated_at'] !== '' ? ' (újragenerálva)' : '') : '' ?></p></div></div>
    <div class="head-actions">
      <?php if ($rep): ?><span class="badge b-ai"><?= $ic('sparkle', 'icon') ?>AI által generált</span><?php endif; ?>
      <?php if ($canRegen): ?><button class="btn btn-secondary sm" type="button" id="sumRegen" data-month="<?= $e($month) ?>"><?= $ic('refresh') ?><?= $rep ? 'Újragenerálás' : 'Összefoglaló készítése' ?></button><?php endif; ?>
    </div>
  </div>
  <?php if ($rep && !empty($rep['sections'])): ?>
  <div class="sum-text">
    <?php if ($rep['details'] !== ''): ?><p class="sum-lead"><?= $e($rep['details']) ?></p><?php endif; ?>
    <div class="sum-groups">
      <?php foreach ($rep['sections'] as $s): ?>
      <div class="sum-group"><h3><?= $e($s['title']) ?></h3><ul><?php foreach ($s['points'] as $pt): ?><li><?= $e($pt) ?></li><?php endforeach; ?></ul></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php elseif ($rep): ?>
  <?php if ($rep['summary_rows']): ?><dl class="kv"><?php foreach ($rep['summary_rows'] as $r): ?><div><dt><?= $e($r['label'] ?? '') ?></dt><dd><?= $e($r['value'] ?? '') ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
  <?php if (!empty($rep['details'])): ?><div class="sum-text"><h3>Részletek</h3><p><?= $e($rep['details']) ?></p></div><?php endif; ?>
  <?php if ($canRegen): ?><p class="help">Ez a korábbi formájú összefoglaló. Az <b>Újragenerálás</b> gombbal tagolt változat készül (Újdonságok, Tények, számok, Trendek), a hónap cikkeiből; néhány cent.</p><?php endif; ?>
  <?php else: ?>
  <div class="empty-state"><b>Ehhez a hónaphoz nincs összefoglaló</b><span><?= $canRegen ? 'Az Összefoglaló készítése gombbal a hónap cikkeiből elkészíthető (néhány cent).' : 'A Google Sheetből átvett hónapokhoz nem készült; az új hónapokét a havi futás írja.' ?></span></div>
  <?php endif; ?>
</section>

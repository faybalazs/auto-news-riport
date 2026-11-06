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
<section class="panel sum-panel" aria-labelledby="w-sum" id="sumCard">
  <div class="panel-head">
    <div><span class="kicker">VEZETŐI ÖSSZEFOGLALÓ</span><h2 id="w-sum">A hónap összefoglalója</h2><p><?= $e($monthLabel) ?><?= $when !== '' ? ' · ' . $e(\Xperion\Web\View::dateHu($when)) . ($rep['updated_at'] !== '' ? ' (újragenerálva)' : '') : '' ?></p></div>
    <div class="head-actions">
      <?php if ($rep): ?><span class="badge b-ai"><?= $ic('sparkle', 'icon') ?>AI által generált</span><?php endif; ?>
      <?php if ($rep): ?><a class="btn btn-ghost sm" href="index.php?p=osszefoglalo&amp;honap=<?= $e($month) ?>" target="_blank" rel="noopener" title="Nyomtatható nézet új ablakban; onnan menthető PDF-be"><?= $ic('download') ?>PDF</a><?php endif; ?>
      <?php if ($rep && $canRegen): ?><button class="btn btn-ghost sm" type="button" id="sumSend" data-month="<?= $e($month) ?>"><?= $ic('send') ?>Küldés</button><?php endif; ?>
      <?php if ($canRegen): ?><?= $tip('t-sumregen', 'újragenerálás', 'A Claude a hónap cikkeiből és a mostani döntésekből írja meg: rövid bevezető, majd Újdonságok, Tények, számok és Trendek, konkrét forrásokkal. A mostani szöveg felülíródik. Néhány cent, 20–40 másodperc.') ?><button class="btn btn-secondary sm" type="button" id="sumRegen" data-month="<?= $e($month) ?>"><?= $ic('refresh') ?><?= $rep ? 'Újragenerálás' : 'Összefoglaló készítése' ?></button><?php endif; ?>
    </div>
  </div>
  <?php if ($rep && !empty($rep['sections'])): ?>
  <div class="sum-text">
    <?php if ($rep['details'] !== ''): ?><p class="sum-lead"><?= $e($rep['details']) ?></p><?php endif; ?>
    <div class="sum-groups">
      <?php foreach ($rep['sections'] as $sumGroup): [$sumIc, $sumTone] = \Xperion\Monthly\Summarizer::ICONS[$sumGroup['title']] ?? ['info', 'blue']; ?>
      <div class="sum-group"><h3><span class="ic-tile t-<?= $sumTone ?>" aria-hidden="true"><?= $ic($sumIc, 'icon') ?></span><?= $e($sumGroup['title']) ?></h3><ul><?php foreach ($sumGroup['points'] as $sumPoint): ?><li><?= $e($sumPoint) ?></li><?php endforeach; ?></ul></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php elseif ($rep): ?>
  <?php if ($rep['summary_rows']): ?><dl class="kv"><?php foreach ($rep['summary_rows'] as $sumRow): ?><div><dt><?= $e($sumRow['label'] ?? '') ?></dt><dd><?= $e($sumRow['value'] ?? '') ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
  <?php if (!empty($rep['details'])): ?><div class="sum-text"><h3>Részletek</h3><p><?= $e($rep['details']) ?></p></div><?php endif; ?>
  <?php else: ?>
  <div class="empty-state"><b>Ehhez a hónaphoz nincs összefoglaló</b><span><?= $canRegen ? 'Az Összefoglaló készítése gombbal a hónap cikkeiből elkészíthető (néhány cent).' : 'A Google Sheetből átvett hónapokhoz nem készült; az új hónapokét a havi futás írja.' ?></span></div>
  <?php endif; ?>
</section>

<?php
/** @var \Xperion\Auth\Access $access @var array $data */
$sub = 'A „LinkedIn poszt” jelölésű cikkekből és a posztötletekből készülnek, a Posztszabályok szerint. Útjuk: Tervezet → Ügyfélnél (jóváhagyásra) → Jóváhagyva → Megjelent.';
require __DIR__ . '/_head.php';
$open = $data['openMonths'] ?? [];
?>
<?php if ($access->can('ideas') && $open): ?>
<section class="card widget" aria-labelledby="h-gen">
  <div class="widget-head"><div><h2 id="h-gen">Poszt a hónap LinkedIn-cikkeiből</h2><p class="meta">Egy havi poszt az összes, még poszt nélküli „LinkedIn poszt” cikkből. Egy cikkből külön poszt a Havi cikkek oldalon kérhető.</p></div></div>
  <div class="collect-bar">
    <div class="field"><label for="genMonth">Hónap</label><select class="select" id="genMonth"><?php foreach ($open as $m): ?><option value="<?= $e($m['month']) ?>"><?= $e($m['label']) ?> · <?= (int) $m['n'] ?> cikk</option><?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="button" id="genMonthly"><?= $ic('sparkle') ?>Poszt készítése</button>
  </div>
  <p class="help">Fél–egy perc (Claude Opus, kb. 15–25 cent). A poszt Tervezet állapotba kerül.</p>
</section>
<?php endif; ?>
<?php foreach ($data['jobs'] ?? [] as $j): ?>
<div class="banner <?= $j['attempts'] >= \Xperion\Posts\PostService::MAX_ATTEMPTS ? 'bn-error' : 'bn-info' ?>" role="status"><?= $ic('clock', 'icon') ?><div class="b-body"><span><b>Generálásra vár</b> (<?= $e(['cikkek' => 'havi poszt', 'cikk' => 'cikkből', 'otlet' => 'ötletből', 'ujra' => 'újragenerálás'][$j['kind']] ?? $j['kind']) ?>: <?= $e($j['ref']) ?>, <?= (int) $j['attempts'] ?>. próba): <?= $e($j['error']) ?></span></div></div>
<?php endforeach; ?>
<div class="seg" role="group" aria-label="Állapot" id="postFilter">
  <button type="button" data-pf="" aria-pressed="true">Mind</button>
  <?php foreach (\Xperion\Posts\PostService::STATUS as $k => $l): ?><button type="button" data-pf="<?= $e($k) ?>" aria-pressed="false"><?= $e($l) ?></button><?php endforeach; ?>
</div>
<div class="post-grid" id="postGrid"></div>
<div class="empty-state card widget" id="postEmpty" hidden><b>Nincs ilyen poszt</b><span>A posztok a „LinkedIn poszt” jelölésű cikkekből és a posztötletekből készülnek.</span></div>

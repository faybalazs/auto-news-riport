<?php
/** @var \Xperion\Auth\Access $access @var array $data */
$sub = 'A „LinkedIn poszt” jelölésű cikkekből és a posztötletekből készülnek, a Posztszabályok szerint. Útjuk: Tervezet → Ügyfélnél (jóváhagyásra) → Jóváhagyva → Megjelent.';
require __DIR__ . '/_head.php';
$open = $data['openMonths'] ?? [];
?>
<?php if ($open): $wait = array_sum(array_column($open, 'n')); ?>
<div class="banner bn-info" role="status"><?= $ic('linkedin', 'icon') ?><div class="b-body"><span><b><?= (int) $wait ?> „LinkedIn poszt” cikk vár posztra</b> (<?= $e(implode(', ', array_map(static fn ($m) => $m['label'] . ': ' . $m['n'], $open))) ?>). A poszt akkor készül, amikor a Havi cikkek oldalon kijelöli őket, és megnyomja a <b>Poszt készítése a kijelöltekből</b> gombot. <a href="index.php?p=cikkek&amp;honap=<?= $e($open[0]['month']) ?>">Havi cikkek</a></span></div></div>
<?php endif; ?>
<?php foreach ($data['jobs'] ?? [] as $j): ?>
<div class="banner <?= $j['attempts'] >= \Xperion\Posts\PostService::MAX_ATTEMPTS ? 'bn-error' : 'bn-info' ?>" role="status"><?= $ic('clock', 'icon') ?><div class="b-body"><span><b>Generálásra vár</b> (<?= $e(['cikkek' => 'havi poszt', 'cikk' => 'cikkből', 'otlet' => 'ötletből', 'ujra' => 'újragenerálás'][$j['kind']] ?? $j['kind']) ?>: <?= $e($j['ref']) ?>, <?= (int) $j['attempts'] ?>. próba): <?= $e($j['error']) ?></span></div></div>
<?php endforeach; ?>
<div class="seg" role="group" aria-label="Állapot" id="postFilter">
  <button type="button" data-pf="" aria-pressed="true">Mind</button>
  <?php foreach (\Xperion\Posts\PostService::STATUS as $k => $l): ?><button type="button" data-pf="<?= $e($k) ?>" aria-pressed="false"><?= $e($l) ?></button><?php endforeach; ?>
</div>
<div class="post-grid" id="postGrid"></div>
<nav class="pager card post-pager" id="postPager" aria-label="Posztok lapozása" hidden></nav>
<div class="empty-state card widget" id="postEmpty" hidden><b>Nincs ilyen poszt</b><span>A posztok a „LinkedIn poszt” jelölésű cikkekből és a posztötletekből készülnek.</span></div>

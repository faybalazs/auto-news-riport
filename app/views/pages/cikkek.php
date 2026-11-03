<?php
/** @var \Xperion\Auth\Access $access @var array $data */
$months = $data['months'] ?? [];
$month = $data['month'] ?? '';
$label = static fn (string $m): string => \Xperion\Web\View::monthHu(new DateTimeImmutable(sprintf('20%s-%s-01', substr($m, 0, 2), substr($m, 2, 2))));
$sub = $access->can('decide')
    ? 'Jelölje meg, melyik cikk kerüljön fel a weboldalra (Mehet), melyikből készüljön LinkedIn-poszt, és melyik nem kell. A Mehet cikkeket a következő óránkénti futás tölti fel, magyarul és angolul.'
    : 'A hónap cikkei és a döntések. A cikkekről az ügyfél-adminisztrátor dönt.';
$actions = $months ? '<label class="sr-only" for="monthSel">Hónap</label><select class="select" id="monthSel" style="width:auto">' . implode('', array_map(static fn ($m) => '<option value="' . $e($m['month']) . '"' . ($m['month'] === $month ? ' selected' : '') . '>' . $e($label($m['month'])) . ($m['open'] ? ' · ' . $m['open'] . ' döntésre vár' : '') . '</option>', $months)) . '</select>' : '';
require __DIR__ . '/_head.php';
$rep = $data['report'] ?? null;
?>
<?php if (!$months): ?>
<section class="card widget"><div class="empty-state"><b>Még nincs havi lista</b><span>Az első havi futás (minden hónap 1-jén, vagy a Vezérlőpultról kézzel) után itt jelennek meg a hónap cikkei a javaslatokkal. A Google Sheetből átvett korábbi hónapok is itt látszanak.</span></div></section>
<?php else: ?>
<?php if ($rep): ?>
<section class="card widget" aria-labelledby="w-sum">
  <div class="widget-head">
    <div class="wh"><span class="ic-tile t-purple" aria-hidden="true"><?= $ic('sparkle', 'icon') ?></span><div><h2 id="w-sum">Vezetői összefoglaló</h2><p class="meta"><?= $e($label($month)) ?> · <?= $e(\Xperion\Web\View::dateHu($rep['created_at'])) ?></p></div></div>
    <div class="head-actions"><span class="badge b-ai"><?= $ic('sparkle', 'icon') ?>AI által generált</span></div>
  </div>
  <dl class="kv"><?php foreach ($rep['summary_rows'] as $r): ?><div><dt><?= $e($r['label'] ?? '') ?></dt><dd><?= $e($r['value'] ?? '') ?></dd></div><?php endforeach; ?></dl>
  <?php if (!empty($rep['details'])): ?><div class="sum-text"><h3>Részletek</h3><p><?= $e($rep['details']) ?></p></div><?php endif; ?>
</section>
<?php endif; ?>
<?php if (isset($data['upWait'])): $wpReady = !empty($data['wpReady']); $why = $wpReady ? '' : ' disabled title="A weboldal-kapcsolat még nincs beállítva; az üzemeltető állítja be."'; ?>
<section class="card widget" aria-labelledby="w-up" id="artUpload">
  <div class="widget-head">
    <div class="wh"><span class="ic-tile t-green" aria-hidden="true"><?= $ic('upload', 'icon') ?></span><div><h2 id="w-up">Feltöltés a weboldalra</h2><p class="meta"><span id="upWait"><?= (int) $data['upWait'] ?></span> Mehet cikk vár feltöltésre (minden hónapból) · futásonként legfeljebb <?= \Xperion\Press\UploadService::PER_RUN ?> cikk · az óránkénti futás magától is feltölti</p></div></div>
  </div>
  <?php if (!$wpReady): ?><div class="banner bn-info" role="status"><?= $ic('info', 'icon') ?><div class="b-body"><span>A weboldal-kapcsolat még nincs beállítva ennél az ügyfélnél. Az üzemeltető állítja be; addig a Mehet cikkek várakoznak.</span></div></div><?php endif; ?>
  <div class="acts-row">
    <button class="btn btn-secondary" type="button" id="upDry"<?= $why ?>><?= $ic('flask') ?>Szövegek elkészítése (szárazon)</button>
    <button class="btn btn-primary" type="button" id="upLive"<?= $why ?>><?= $ic('upload') ?>Feltöltés élesben</button>
  </div>
  <p class="help"><b>Szövegek elkészítése:</b> a Claude megírja a Mehet cikkek magyar és angol címét és ajánlóját (cikkenként 2–3 cent); a weboldalra nem kerül semmi, a szöveg a cikk sorában átnézhető. <b>Feltöltés élesben:</b> a Mehet cikkek magyarul és angolul kikerülnek a weboldalra (a kész szöveggel, újra nem fizet érte), és „Publikálva” állapotba kerülnek.</p>
  <div id="upOut" hidden>
    <div class="result-meta" id="upMeta"></div>
    <ol class="run-lines" id="upLines"></ol>
    <div class="table-wrap mini-table" id="upItems" style="border:1px solid var(--border);border-radius:var(--r-lg);margin-top:12px"></div>
  </div>
</section>
<?php endif; ?>
<div class="seg" role="group" aria-label="Nézetek" id="artViews">
  <button type="button" data-v="todo" aria-pressed="true">Döntésre vár <span class="cnt-badge" id="cntTodo">0</span></button>
  <button type="button" data-v="all" aria-pressed="false">Összes</button>
  <button type="button" data-v="mehet" aria-pressed="false">Mehet</button>
  <button type="button" data-v="publikalva" aria-pressed="false">Publikálva</button>
  <button type="button" data-v="linkedin" aria-pressed="false">LinkedIn poszt</button>
  <button type="button" data-v="nemkell" aria-pressed="false">Nem kell</button>
  <button type="button" data-v="new" aria-pressed="false">Új források</button>
</div>
<div class="card table-card">
  <div class="banner bn-info" id="liHint" role="status" hidden><?= $ic('linkedin', 'icon') ?><div class="b-body"><span><b><span id="liWait">0</span> LinkedIn-cikk vár posztra.</b> Jelöljön ki egyet vagy többet (bármikor kiegészítheti a jelöléseket), majd: <b>Poszt készítése a kijelöltekből</b>. Egy cikkből egy poszt, több cikkből egy közös poszt készül, és magától jóváhagyásra kerül.</span></div></div>
  <div class="bulkbar" id="bulk" hidden>
    <b id="bulkCount">0 kiválasztva</b>
    <button type="button" class="btn btn-secondary sm" data-bulk="mehet"><?= $ic('upload') ?>Mehet</button>
    <button type="button" class="btn btn-secondary sm" data-bulk="linkedin"><?= $ic('linkedin') ?>LinkedIn poszt</button>
    <button type="button" class="btn btn-secondary sm" data-bulk="nemkell"><?= $ic('x') ?>Nem kell</button>
    <button type="button" class="btn btn-ghost sm" data-bulk="">Döntés törlése</button>
    <button type="button" class="btn btn-primary sm" id="bulkPost" hidden><?= $ic('sparkle') ?>Poszt készítése a kijelöltekből</button>
    <button type="button" class="btn btn-ghost sm" id="bulkClear">Kijelölés megszüntetése</button>
  </div>
  <div class="toolbar" id="artToolbar" role="search" aria-label="Cikkek szűrése">
    <div class="search"><?= $ic('search', 'icon') ?><label class="sr-only" for="artQ">Keresés a cikkek között</label><input class="input" id="artQ" type="search" placeholder="Keresés címben, leírásban…" autocomplete="off"></div>
    <label class="sr-only" for="artRec">Javaslat</label>
    <select class="select" id="artRec"><option value="">Minden javaslat</option><option>Erősen javasolt</option><option>Javasolt</option><option>Nem javasolt</option><option>Nem javasolt (már fent)</option></select>
  </div>
  <div class="table-wrap" id="artTable"></div>
  <div class="pager" id="artPager"></div>
</div>
<?php endif; ?>

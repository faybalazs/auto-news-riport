<?php
/** @var \Xperion\Auth\Access $access @var array $data */
$months = $data['months'] ?? [];
$month = $data['month'] ?? '';
$label = static fn (string $m): string => \Xperion\Web\View::monthHu(new DateTimeImmutable(sprintf('20%s-%s-01', substr($m, 0, 2), substr($m, 2, 2))));
$sub = $access->can('decide')
    ? 'Jelölje meg, melyik cikk kerüljön fel a weboldalra (Mehet), melyikből készüljön LinkedIn-poszt, és melyik nem kell. A Mehet cikkeket a következő óránkénti futás tölti fel, magyarul és angolul.'
    : 'A hónap cikkei és a döntések. A cikkekről az ügyfél-adminisztrátor dönt.';
$actions = $months ? '<label class="sr-only" for="monthSel">Hónap</label><select class="select month-select" id="monthSel">' . implode('', array_map(static fn ($m) => '<option value="' . $e($m['month']) . '"' . ($m['month'] === $month ? ' selected' : '') . '>' . $e($label($m['month'])) . ($m['open'] ? ' · ' . $m['open'] . ' döntésre vár' : '') . '</option>', $months)) . '</select>' : '';
require __DIR__ . '/_head.php';
$rep = $data['report'] ?? null;
?>
<?php if (!$months): ?>
<section class="card widget"><div class="empty-state"><b>Még nincs havi lista</b><span>Az első havi futás (minden hónap 1-jén, vagy a Vezérlőpultról kézzel) után itt jelennek meg a hónap cikkei a javaslatokkal. A Google Sheetből átvett korábbi hónapok is itt látszanak.</span></div></section>
<?php else: ?>
<?php
$lead = '';
if ($rep) {
    $lead = trim((string) ($rep['details'] ?? ''));
    if ($lead === '' && !empty($rep['summary_rows'])) {
        $lead = (string) ($rep['summary_rows'][0]['value'] ?? '');
    }
    if (preg_match('/^(.{20,}?[.!?])\s/u', $lead . ' ', $mm)) {
        $lead = $mm[1];
    }
}
$repWhen = $rep ? ($rep['updated_at'] !== '' ? $rep['updated_at'] : $rep['created_at']) : '';
$hasUp = isset($data['upWait']);
?>
<div class="work-cards<?= $hasUp ? '' : ' one' ?>">
  <section class="summary-card" aria-labelledby="w-sumc">
    <span class="icon-tile violet" aria-hidden="true"><?= $ic('sparkle', 'icon') ?></span>
    <div>
      <span class="kicker">VEZETŐI ÖSSZEFOGLALÓ</span>
      <h3 id="w-sumc"><?= $rep ? $e($lead !== '' ? $lead : 'A hónap összefoglalója elkészült.') : 'Ehhez a hónaphoz még nincs összefoglaló.' ?></h3>
      <p><?= $rep ? $e(\Xperion\Web\View::dateHu($repWhen)) . ' · AI által generált' : $e($label($month)) ?></p>
    </div>
    <button class="btn btn-secondary sm" type="button" id="sumToggle" aria-expanded="false" aria-controls="sumWrap"><?= $rep ? 'Megnyitás' : 'Részletek' ?></button>
  </section>
  <?php if ($hasUp): $wpReady = !empty($data['wpReady']); $why = $wpReady ? '' : ' disabled title="A weboldal-kapcsolat még nincs beállítva; az üzemeltető állítja be."'; ?>
  <section class="upload-card" aria-labelledby="w-up" id="artUpload">
    <span class="icon-tile green" aria-hidden="true"><?= $ic('upload', 'icon') ?></span>
    <div>
      <span class="kicker">FELTÖLTÉS A WEBOLDALRA</span>
      <h3 id="w-up"><span id="upWait"><?= (int) $data['upWait'] ?></span> Mehet cikk vár feltöltésre</h3>
      <p><?= $wpReady ? 'Futásonként legfeljebb ' . \Xperion\Press\UploadService::PER_RUN . ' cikk · óránként automatikus' : 'A weboldal-kapcsolat még nincs beállítva; az üzemeltető állítja be.' ?></p>
    </div>
    <button class="btn btn-secondary sm" type="button" id="upDry"<?= $why ?>><?= $ic('flask') ?>Szövegek elkészítése</button>
    <button class="btn btn-primary sm" type="button" id="upLive"<?= $why ?>><?= $ic('upload') ?>Feltöltés élesben</button>
    <?= $tip('t-upload', 'feltöltés a weboldalra', '<b>Szövegek elkészítése:</b> a Claude megírja a Mehet cikkek magyar és angol címét és ajánlóját (cikkenként 2–3 cent); a weboldalra nem kerül semmi, a szöveg a cikk sorában átnézhető.<br><b>Feltöltés élesben:</b> a Mehet cikkek magyarul és angolul kikerülnek a weboldalra (a kész szöveggel, újra nem fizet érte), és „Publikálva” állapotba kerülnek.') ?>
  </section>
  <?php endif; ?>
</div>
<div id="sumWrap" hidden><?php $monthLabel = $label($month); require __DIR__ . '/_osszefoglalo.php'; ?></div>
<?php if ($hasUp): ?>
<section class="panel up-result" id="upOut" hidden aria-label="A feltöltés eredménye">
  <div class="panel-head"><div><span class="kicker">FELTÖLTÉS</span><h2>Az utolsó futás eredménye</h2><p class="result-meta" id="upMeta"></p></div></div>
  <ol class="run-lines" id="upLines"></ol>
  <div class="table-wrap mini" id="upItems"></div>
</section>
<?php endif; ?>
<div class="card table-card">
  <div class="card-views" role="group" aria-label="Nézetek" id="artViews">
    <button type="button" data-v="todo" aria-pressed="true">Döntésre vár <span id="cntTodo">0</span></button>
    <button type="button" data-v="all" aria-pressed="false">Összes</button>
    <button type="button" data-v="mehet" aria-pressed="false">Mehet</button>
    <button type="button" data-v="publikalva" aria-pressed="false">Publikálva</button>
    <button type="button" data-v="linkedin" aria-pressed="false">LinkedIn poszt</button>
    <button type="button" data-v="nemkell" aria-pressed="false">Nem kell</button>
    <button type="button" data-v="new" aria-pressed="false">Új források</button>
  </div>
  <div class="banner bn-info" id="liHint" role="status" hidden><?= $ic('linkedin', 'icon') ?><div><b><span id="liWait">0</span> LinkedIn-cikk vár posztra.</b><span>Jelöljön ki egyet vagy többet a LinkedIn poszt nézetben, majd készítsen posztot a kijelöltekből.</span></div></div>
  <div class="bulkbar" id="bulk" hidden>
    <b id="bulkCount"><span>0</span> kiválasztva</b>
    <button type="button" data-bulk="mehet">Mehet</button>
    <button type="button" data-bulk="linkedin">LinkedIn poszt</button>
    <button type="button" data-bulk="nemkell">Nem kell</button>
    <button type="button" class="muted" data-bulk="">Döntés törlése</button>
    <button type="button" class="primary" id="bulkPost" hidden><?= $ic('sparkle') ?><span>Poszt készítése</span></button>
    <button type="button" class="close" id="bulkClear" aria-label="Kijelölés megszüntetése" title="Kijelölés megszüntetése"><?= $ic('x') ?></button>
  </div>
  <div class="toolbar" id="artToolbar" role="search" aria-label="Cikkek szűrése">
    <div class="search"><?= $ic('search', 'icon') ?><label class="sr-only" for="artQ">Keresés a cikkek között</label><input class="input" id="artQ" type="search" placeholder="Keresés címben, leírásban…" autocomplete="off"></div>
    <label class="sr-only" for="artRec">Javaslat</label>
    <select class="select" id="artRec"><option value="">Minden javaslat</option><option>Erősen javasolt</option><option>Javasolt</option><option>Nem javasolt</option><option>Nem javasolt (már fent)</option></select>
    <span id="artFound" aria-live="polite"></span>
  </div>
  <div class="table-wrap" id="artTable"></div>
  <div class="pager" id="artPager"></div>
</div>
<?php endif; ?>

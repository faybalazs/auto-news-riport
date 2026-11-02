<?php
/** @var \Xperion\Auth\Access $access */
$sub = 'Minden ügyfélnek saját forrásai, kulcsszavai, posztszabályai, felhasználói és weboldal-kapcsolata van; az adatok nem keverednek.';
$actions = '<button class="btn btn-primary" type="button" id="clientAdd">' . $ic('plus') . 'Új ügyfél</button>';
require __DIR__ . '/_head.php';
$cur = $access->client();
?>
<div class="layout">
  <div class="card table-card">
    <div class="table-wrap" id="clientTable"></div>
    <div class="pager" id="clientPager"></div>
  </div>
  <aside class="stack">
    <section class="card widget" aria-labelledby="h-imp" id="impCard">
      <div class="widget-head"><div><h2 id="h-imp">Adatátvétel a Google Sheetből</h2><p class="meta" id="impMeta"><?= $e($cur['name'] ?? 'Nincs kiválasztott ügyfél') ?></p></div></div>
      <?php if (empty($data['googleReady'])): ?>
      <div class="banner bn-info" role="note"><?= $ic('info', 'icon') ?><div class="b-body"><span>A config.php-ban nincs Google-szolgáltatásfiók; az adatátvételhez az kell (README).</span></div></div>
      <?php else: ?>
      <p class="help">A táblát meg kell osztani ezzel a címmel (olvasóként elég):</p>
      <div class="copy-box"><code id="svcEmail"><?= $e($data['serviceEmail'] ?? '') ?></code><button class="btn btn-ghost sm" type="button" data-copy="<?= $e($data['serviceEmail'] ?? '') ?>"><?= $ic('copy') ?>Másolás</button></div>
      <?php endif; ?>
      <dl class="kv" id="impKv"></dl>
      <div class="acts-row">
        <button class="btn btn-secondary sm" type="button" id="impDry"><?= $ic('flask') ?>Szárazfuttatás</button>
        <button class="btn btn-primary sm" type="button" id="impLive"><?= $ic('download') ?>Adatok átvétele</button>
      </div>
      <ol class="run-lines" id="impLines" hidden></ol>
    </section>
    <section class="card widget" aria-labelledby="h-onb">
      <div class="widget-head"><div><h2 id="h-onb">Beállítási lépések</h2><p class="meta"><?= $e($cur['name'] ?? 'Nincs kiválasztott ügyfél') ?></p></div></div>
      <ol class="checklist" id="onbList"></ol>
    </section>
  </aside>
</div>

<dialog id="clientDlg" aria-labelledby="clientDlgTitle">
  <form id="clientForm" novalidate>
    <div class="dlg-body">
      <h2 id="clientDlgTitle">Új ügyfél</h2>
      <div class="field"><label for="c-name">Ügyfél neve</label><input class="input" id="c-name" required autocomplete="off"></div>
      <div class="field"><label for="c-site">Weboldal</label><input class="input" id="c-site" placeholder="pelda.hu" autocomplete="off"></div>
      <div class="field"><label for="c-sheet">Google Sheet (link vagy azonosító) <span class="opt">(nem kötelező)</span></label><input class="input" id="c-sheet" placeholder="https://docs.google.com/spreadsheets/d/…" autocomplete="off" aria-describedby="c-sheet-h"><span class="help" id="c-sheet-h">A korábbi adatok átvételéhez. Másolja be a tábla linkjét a böngésző címsorából.</span></div>
      <div class="field" id="c-status-f" hidden><label for="c-status">Állapot</label><select class="select" id="c-status"><option value="beallitas">Beállítás alatt</option><option value="aktiv">Aktív</option><option value="szunetel">Szüneteltetve (a futások szünetelnek)</option></select></div>
      <p class="error" id="clientErr" role="alert" hidden></p>
    </div>
    <div class="dlg-foot"><button class="btn btn-secondary" type="button" id="clientCancel">Mégse</button><button class="btn btn-primary" type="submit" id="clientSubmit">Ügyfél létrehozása</button></div>
  </form>
</dialog>

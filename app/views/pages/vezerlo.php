<?php
/** @var \Xperion\App $app @var \Xperion\Auth\Access $access */
$sub = 'Futások kézi indítása, a beállítások állapota és az időzítés. A szárazfuttatás semmit nem ír.';
require __DIR__ . '/_head.php';
$log = $app->log();
$desc = [
    'havi' => 'Minden hónap 1-jén 06:00-kor, minden Aktív ügyfélre: gyűjtés, Claude-értékelés, a havi lista és az összefoglaló mentése, majd a havi e-mail megírása (jóváhagyásra vár, értesítés csak az üzemeltetőnek).',
    'orankenti' => 'Minden órában: a Mehet cikkek feltöltése a weboldalra, magyarul és angolul (minden Aktív ügyfélre), és a sikertelen posztgenerálási kérések pótlása.',
];
$cronKey = $app->config->string('cron_key');
$base = \Xperion\Web\Http::baseUrl() . 'cron.php?k=' . rawurlencode($cronKey !== '' ? $cronKey : '<cron_key>');
$pending = $app->migrator()->pending();
?>
<section class="card widget" aria-labelledby="h-run" id="runState">
  <div class="widget-head">
    <div class="wh"><span class="ic-tile t-blue" aria-hidden="true"><?= $ic('clock', 'icon') ?></span><div><h2 id="h-run">Futások állapota</h2><p class="meta" id="rsMeta">Mi fut most, és a legutóbbi eredmények (akkor is, ha közben máshová navigált)</p></div></div>
    <div class="head-actions" id="rsBadge"></div>
  </div>
  <div class="rs-now" id="rsNow" hidden role="status" aria-live="polite"></div>
  <ul class="rs-list" id="rsList"></ul>
</section>
<?php
$months = [];
for ($i = 1; $i <= 6; $i++) {
    $d = new DateTimeImmutable("first day of -$i month");
    $months[$d->format('ym')] = \Xperion\Web\View::monthHu($d);
}
?>
<section class="card widget" aria-labelledby="h-collect">
  <div class="widget-head">
    <div class="wh"><span class="ic-tile t-blue" aria-hidden="true"><?= $ic('list', 'icon') ?></span><div><h2 id="h-collect">Havi futás és cikkgyűjtés</h2><p class="meta"><?= $e($access->client()['name'] ?? 'Nincs kiválasztott ügyfél') ?> · a cron minden hónap 1-jén élesben futtatja az Aktív ügyfelekre</p></div></div>
  </div>
  <div class="collect-bar">
    <div class="field"><label for="colMonth">Hónap</label><select class="select" id="colMonth"><?php foreach ($months as $k => $l): ?><option value="<?= $e($k) ?>"><?= $e($l) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-secondary" type="button" id="colRun"<?= $access->client() ? '' : ' disabled' ?>><?= $ic('flask') ?>Csak gyűjtés</button>
    <button class="btn btn-secondary" type="button" id="monDry"<?= $access->client() ? '' : ' disabled' ?>><?= $ic('sparkle') ?>Havi futás szárazon</button>
    <button class="btn btn-primary" type="button" id="monLive"<?= $access->client() ? '' : ' disabled' ?>><?= $ic('play') ?>Havi futás élesben</button>
    <?= $tip('t-col', 'havi futás', '<b>Csak gyűjtés:</b> a források eredménye Claude nélkül.<br><b>Havi futás szárazon:</b> gyűjtés és Claude-értékelés, mentés nélkül (néhány tized cent).<br><b>Élesben:</b> a hónap listája és összefoglalója bekerül a Havi cikkek közé; egy hónapra egyszer. Egy futás 1–3 perc lehet.') ?>
  </div>

  <div id="colOut" hidden>
    <div class="result-meta" id="colMeta"></div>
    <div class="table-wrap mini-table" style="border:1px solid var(--border);border-radius:var(--r-lg);margin-top:12px" id="colSources"></div>
    <div id="monSum" hidden></div>
    <h3 class="sub-h">Talált új cikkek</h3>
    <div class="card table-card" style="box-shadow:none"><div class="table-wrap" id="colItems"></div><div class="pager" id="colPager"></div></div>
  </div>
</section>
<?php if ($access->client()): $wpSite = $app->config->wordpressSite((string) ($access->client()['website'] ?? '')); $waitUp = (int) $app->db()->value("SELECT COUNT(*) FROM articles WHERE client_id = ? AND decision = 'mehet' AND uploaded = 0", [$access->clientId()]); ?>
<section class="card widget" aria-labelledby="h-up" id="upCard">
  <div class="widget-head">
    <div class="wh"><span class="ic-tile t-green" aria-hidden="true"><?= $ic('upload', 'icon') ?></span><div><h2 id="h-up">Feltöltés a weboldalra</h2><p class="meta"><?= $e($access->client()['name']) ?> · <?= $wpSite ? $e($wpSite['base_url']) : 'nincs WordPress-beállítás' ?> · <?= $waitUp ?> Mehet cikk vár · az óránkénti futás automatikusan feltölti</p></div></div>
  </div>
  <?php if (!$wpSite): ?>
  <div class="banner bn-info" role="status"><?= $ic('info', 'icon') ?><div class="b-body"><span><?php if (trim((string) ($access->client()['website'] ?? '')) === ''): ?><b>A(z) <?= $e($access->client()['name'] ?? '') ?> ügyfélnél nincs megadva a weboldal.</b> Az <a href="index.php?p=ugyfelek">Ügyfelek</a> oldalon a sor ⋯ menüjében válassza a Szerkesztést, és írja be a weboldalt (pl. xperion.hu). Ha nem ez az ügyfél kell, a bal felső ügyfélváltóval váltson.<?php else: ?><b>Nincs WordPress-beállítás ehhez az ügyfélhez.</b> A config.php <code>wordpress</code> részébe kell az ügyfél weboldala (<?= $e($access->client()['website']) ?>) szerint: felhasználó és alkalmazásjelszó.<?php endif; ?></span></div></div>
  <?php endif; ?>
  <div class="collect-bar">
    <button class="btn btn-secondary" type="button" id="wpCheck"><?= $ic('globe') ?>Kapcsolat ellenőrzése</button>
    <button class="btn btn-secondary" type="button" id="upDry"><?= $ic('flask') ?>Feltöltés szárazon</button>
    <button class="btn btn-primary" type="button" id="upLive"><?= $ic('upload') ?>Feltöltés élesben</button>
    <?= $tip('t-wp', 'feltöltés a weboldalra', '<b>Kapcsolat ellenőrzése:</b> a bővítmény verziója, jogok, alapkategóriák; semmit nem ír.<br><b>Szárazon:</b> a Claude megírja a magyar és angol címet és ajánlót (cikkenként 2–3 cent), a WordPressbe nem ír.<br><b>Élesben:</b> a Mehet cikkek kikerülnek a weboldalra, magyarul és angolul; futásonként legfeljebb ' . \Xperion\Press\UploadService::PER_RUN . ' cikk.') ?>
  </div>

  <div class="banner bn-error" role="alert" id="wpErr" hidden><?= $ic('x-circle', 'icon') ?><div class="b-body"><span id="wpErrText" class="pre-line"></span><div id="wpErrCopy"></div><div class="acts-row" id="wpErrHelper" hidden><a class="btn btn-secondary sm" href="index.php?p=wpkiegeszito" download><?= $ic('download') ?>Belépési kiegészítő letöltése (xperion-auth-fejlec.php)</a></div></div></div>
  <dl class="kv" id="wpKv" hidden></dl>
  <div id="upOut" hidden>
    <div class="result-meta" id="upMeta"></div>
    <ol class="run-lines" id="upLines"></ol>
    <div class="table-wrap mini-table" id="upItems" style="border:1px solid var(--border);border-radius:var(--r-lg);margin-top:12px"></div>
  </div>
</section>
<?php endif; ?>
<?php if ($access->client()): $mail = $data['mail'] ?? []; ?>
<section class="card widget" aria-labelledby="h-mail" id="mailCard">
  <div class="widget-head">
    <div class="wh"><span class="ic-tile t-orange" aria-hidden="true"><?= $ic('send', 'icon') ?></span><div><h2 id="h-mail">Havi e-mail az ügyfélnek</h2><p class="meta" id="mailMeta"></p></div></div>
    <div class="head-actions" id="mailStatus"></div>
  </div>
  <?php if (empty($mail['ready'])): ?>
  <div class="banner bn-info" role="status"><?= $ic('info', 'icon') ?><div class="b-body"><span><b>Az e-mail-küldés nincs beállítva:</b> <?= $e($mail['problem'] ?? '') ?>. Betöltött konfiguráció: <code><?= $e(basename($app->config->source()) ?: 'nincs') ?></code>. Generálni és szerkeszteni lehet; a küldés gombjai megmondják, mi hiányzik.</span></div></div>
  <?php endif; ?>
  <div class="collect-bar">
    <div class="field"><label for="mailMonth">Melyik hónapból?</label><select class="select" id="mailMonth"></select></div>
    <button class="btn btn-secondary" type="button" id="mailGen"><?= $ic('sparkle') ?>E-mail generálása</button>
  </div>
  <div id="mailEmpty" class="empty-state" hidden><b>Ehhez a hónaphoz még nincs e-mail</b><span>Generálja le: a szöveg a hónap listájából, a vezetői összefoglalóból és a beállított stílusból készül (kb. 1–2 cent). Generáláskor az ügyfél még semmit nem kap.</span></div>
  <div id="mailNone" class="empty-state" hidden><b>Még nincs havi lista</b><span>A levél a havi futás után írható meg (vagy az átvett hónapokból).</span></div>
  <div class="mail-prev" id="mailPrev" hidden>
    <div class="mail-subj"><span class="meta">Tárgy</span><b id="mailSubj"></b><span class="badge b-ai"><?= $ic('sparkle', 'icon') ?>AI által generált</span></div>
    <div class="mail-body" id="mailBody"></div>
    <div id="mailEditWrap" hidden class="mail-edit">
      <div class="field"><label for="mailEditSubj">Tárgy</label><input class="input" id="mailEditSubj" maxlength="255"></div>
      <div class="field"><label for="mailEdit">Az e-mail szövege</label><textarea class="input" id="mailEdit" rows="16"></textarea><span class="help">Sima szöveg. A „- ” kezdetű sorokból felsorolás lesz, a linkek kattinthatók.</span></div>
    </div>
  </div>
  <div class="banner bn-error" role="alert" id="mailErr" hidden><?= $ic('x-circle', 'icon') ?><div class="b-body"><span id="mailErrText"></span></div></div>
  <div class="acts-row" id="mailActs" hidden>
    <button class="btn btn-primary" type="button" id="mailApprove"><?= $ic('send') ?>Jóváhagyom, mehet</button>
    <button class="btn btn-secondary" type="button" id="mailEditBtn"><?= $ic('edit') ?>Szöveg szerkesztése</button>
    <button class="btn btn-secondary" type="button" id="mailSave" hidden><?= $ic('check') ?>Mentés</button>
    <button class="btn btn-ghost" type="button" id="mailCancel" hidden>Mégse</button>
    <button class="btn btn-ghost" type="button" id="mailTest"><?= $ic('send') ?>Teszt e-mail nekem</button>
  </div>
  <div class="smtp-check">
    <button class="btn btn-secondary sm" type="button" id="smtpProbe"><?= $ic('globe') ?>SMTP-kapcsolat ellenőrzése</button>
    <?= $tip('t-smtp', 'SMTP-kapcsolat ellenőrzése', 'Végigpróbálja a szokásos beállításokat (465/SSL, 587/STARTTLS, 25, localhost) belépésig, levél nélkül, és megmutatja, melyik működik a tárhelyen. Fél–egy perc.') ?>
    <div id="smtpOut" hidden></div>
  </div>
</section>
<?php endif; ?>
<div class="layout">
  <div class="stack">
    <div class="task-grid" role="list" aria-label="Feladatok">
      <?php foreach ($app->runner()->tasks() as $t): $last = $log->last($t->id()); ?>
      <section class="card widget task" role="listitem" aria-labelledby="t-<?= $e($t->id()) ?>" data-task="<?= $e($t->id()) ?>" data-label="<?= $e($t->label()) ?>">
        <div class="widget-head"><div class="wh"><span class="ic-tile t-blue" aria-hidden="true"><?= $ic('play', 'icon') ?></span><div><h2 id="t-<?= $e($t->id()) ?>"><?= $e($t->label()) ?></h2><p class="meta"><?= $e($desc[$t->id()] ?? '') ?></p></div></div></div>
        <div class="last" data-last><?php if ($last): ?><span>Legutóbb: <?= $e($last->timeHu()) ?> · <?= $last->dry ? 'száraz' : 'éles' ?> · <?= $e(\Xperion\Lock::duration((int) round($last->durationMs / 1000))) ?></span><?= $last->ok ? '<span class="badge b-success">' . $ic('check', 'icon') . 'OK</span>' : '<span class="badge b-danger">' . $ic('x-circle', 'icon') . 'Hiba</span>' ?><?php else: ?><span>Még nem futott</span><?php endif; ?></div>
        <div class="acts"><button class="btn btn-secondary sm" type="button" data-run="<?= $e($t->id()) ?>" data-dry="1"><?= $ic('flask') ?>Szárazfuttatás</button></div>
      </section>
      <?php endforeach; ?>
    </div>
    <section class="card widget" id="resultCard" aria-labelledby="h-result" hidden tabindex="-1">
      <div class="widget-head"><div class="wh"><span class="ic-tile t-blue" aria-hidden="true"><?= $ic('list', 'icon') ?></span><div><h2 id="h-result">Eredmény</h2><p class="meta result-meta" id="resultMeta"></p></div></div><div class="head-actions"><button class="btn btn-ghost sm" type="button" id="resultClose"><?= $ic('x') ?>Bezárás</button></div></div>
      <ol class="run-lines" id="resultLines"></ol>
    </section>
  </div>
  <aside class="stack">
    <section class="card widget" aria-labelledby="h-sys">
      <div class="widget-head"><div class="wh"><span class="ic-tile t-blue" aria-hidden="true"><?= $ic('settings', 'icon') ?></span><div><h2 id="h-sys">Beállítások állapota</h2><p class="meta">config.php · értékek nélkül</p></div></div></div>
      <dl class="kv">
        <?php foreach ($app->config->checklist() as $c): ?>
        <div><dt><?= $e($c['label']) ?></dt><dd><span class="badge <?= $c['ok'] ? 'b-success' : 'b-warning' ?>"><?= $ic($c['ok'] ? 'check' : 'alert', 'icon') ?><?= $e($c['note']) ?></span></dd></div>
        <?php endforeach; ?>
        <div><dt>Adatbázis-séma</dt><dd><span class="badge <?= $pending ? 'b-warning' : 'b-success' ?>"><?= $ic($pending ? 'alert' : 'check', 'icon') ?><?= $pending ? count($pending) . ' frissítés vár' : 'naprakész' ?></span></dd></div>
        <div><dt>PHP-verzió</dt><dd><?= $e(PHP_VERSION) ?></dd></div>
        <div><dt>Alkalmazás</dt><dd><?= $e($version) ?></dd></div>
      </dl>
    </section>
    <section class="card widget" aria-labelledby="h-cron">
      <div class="widget-head"><div class="wh"><span class="ic-tile t-blue" aria-hidden="true"><?= $ic('calendar', 'icon') ?></span><div><h2 id="h-cron">Időzítés (cron)</h2><p class="meta">A tárhely vezérlőpultjában kell beállítani</p></div></div></div>
      <p class="cron-hint">A címben a titkos cron-kulcs van: ne oszd meg.</p>
      <?php foreach (['Havi futás – minden hónap 1-jén 06:00' => $base . '&feladat=havi', 'Óránkénti futás – minden órában' => $base . '&feladat=orankenti'] as $name => $url): $id = 'cl-' . substr(md5($name), 0, 6); ?>
      <span class="copy-label" id="<?= $id ?>"><?= $e($name) ?></span>
      <div class="copy-box"><code title="<?= $e($url) ?>"><?= $e($url) ?></code><button class="btn btn-ghost sm" type="button" data-copy="<?= $e($url) ?>" aria-describedby="<?= $id ?>"><?= $ic('copy') ?>Másolás</button></div>
      <?php endforeach; ?>
    </section>
  </aside>
</div>

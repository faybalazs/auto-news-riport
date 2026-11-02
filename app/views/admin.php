<?php
/**
 * Admin felület: Vezérlőpult (#vezerlo) és Napló (#naplo).
 * Az Auris admin sablon app shelljére, kártyáira és DataTable-jére épül.
 *
 * @var string $csrf
 * @var string $version
 * @var list<array{label:string, ok:bool, note:string}> $checklist
 * @var list<array{id:string, label:string, desc:string, readOnly:bool, last:?\Xperion\Log\LogEntry}> $tasks
 * @var array<string,string> $cronUrls
 * @var string $sheetUrl
 */
$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$badge = static function (bool $ok, string $okText, string $badText) use ($e): string {
    return $ok
        ? '<span class="badge b-success"><svg class="icon" aria-hidden="true"><use href="#i-check"/></svg>' . $e($okText) . '</span>'
        : '<span class="badge b-warning"><svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg>' . $e($badText) . '</span>';
};
$lastRun = static function (?\Xperion\Log\LogEntry $l) use ($e): string {
    if ($l === null) {
        return '<span>Még nem futott</span>';
    }
    $status = $l->ok
        ? '<span class="badge b-success"><svg class="icon" aria-hidden="true"><use href="#i-check"/></svg>OK</span>'
        : '<span class="badge b-danger"><svg class="icon" aria-hidden="true"><use href="#i-x-circle"/></svg>Hiba</span>';
    return '<span>Legutóbb: ' . $e($l->timeHu()) . ' · ' . ($l->dry ? 'száraz' : 'éles') . '</span>' . $status;
};
?><!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>Vezérlőpult · Xperion automatizálás</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="assets/admin.css?v=<?= $e($version) ?>">
</head>
<body>
<?php require __DIR__ . '/icons.php'; ?>
<script type="application/json" id="appData"><?= json_encode(['csrf' => $csrf, 'api' => 'api.php'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>

<div class="shell" id="shell" data-nav="open">
  <a class="skip" href="#main" id="skipLink">Ugrás a tartalomra</a>
  <nav class="sidebar" id="sidebar" aria-label="Fő menü">
    <div class="brand">
      <div class="brand-mark xp" aria-hidden="true"><svg class="icon" aria-hidden="true"><use href="#i-file"/></svg></div>
      <div class="brand-text"><b>Xperion</b><span>Sajtófigyelés</span></div>
    </div>
    <a class="nav-item" href="#vezerlo" data-nav="vezerlo" title="Vezérlőpult"><svg class="icon" aria-hidden="true"><use href="#i-home"/></svg><span class="nav-label">Vezérlőpult</span></a>
    <a class="nav-item" href="#naplo" data-nav="naplo" title="Napló"><svg class="icon" aria-hidden="true"><use href="#i-clock"/></svg><span class="nav-label">Napló</span></a>
    <div class="sidebar-foot">
      <form method="post" action="index.php" id="logoutForm">
        <input type="hidden" name="muvelet" value="kilepes">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <button class="nav-item" type="submit" title="Kilépés" style="width:100%"><svg class="icon" aria-hidden="true"><use href="#i-logout"/></svg><span class="nav-label">Kilépés</span></button>
      </form>
      <button class="nav-item collapse-btn" type="button" id="collapseBtn" aria-expanded="true" title="Menü összecsukása"><svg class="icon" aria-hidden="true" id="collapseIcon"><use href="#i-chev-left"/></svg><span class="nav-label" id="collapseLabel">Összecsukás</span></button>
    </div>
  </nav>
  <div class="scrim" id="scrim"></div>

  <div class="main-col">
    <header class="topbar">
      <button class="icon-btn menu-toggle" type="button" id="menuToggle" aria-label="Menü megnyitása" aria-controls="sidebar" aria-expanded="false"><svg class="icon" aria-hidden="true"><use href="#i-menu"/></svg></button>
      <span style="flex:1"></span>
      <div class="topbar-actions">
        <button class="icon-btn" type="button" id="densityBtn" aria-pressed="false" title="Kompakt sűrűség" aria-label="Kompakt sűrűség"><svg class="icon" aria-hidden="true"><use href="#i-rows"/></svg></button>
        <button class="icon-btn" type="button" id="themeBtn" title="Téma: rendszer" aria-label="Téma váltása, jelenleg: rendszer"><svg class="icon" aria-hidden="true"><use href="#i-monitor" id="themeIcon"/></svg></button>
      </div>
    </header>

    <main class="content" id="main">

      <!-- ================= NÉZET: VEZÉRLŐPULT (#vezerlo) ================= -->
      <section class="view" id="v-vezerlo" aria-labelledby="h-vezerlo" hidden>
        <div class="page-head">
          <div>
            <div class="page-title"><h1 id="h-vezerlo" tabindex="-1">Vezérlőpult</h1></div>
            <p class="page-sub">Feladatok kézi indítása. A szárazfuttatás nem ír a táblába, nem tölt fel és nem küld e-mailt, csak leírja, mit tenne.</p>
          </div>
          <div class="page-actions">
            <a class="btn btn-secondary" href="<?= $e($sheetUrl) ?>" target="_blank" rel="noopener"><svg class="icon icon-sm" aria-hidden="true"><use href="#i-file"/></svg>Google Sheet megnyitása</a>
          </div>
        </div>

        <div class="layout">
          <div class="stack">
            <div class="task-grid" role="list" aria-label="Feladatok">
              <?php foreach ($tasks as $t): ?>
              <section class="card task" role="listitem" aria-labelledby="t-<?= $e($t['id']) ?>" data-task="<?= $e($t['id']) ?>" data-label="<?= $e($t['label']) ?>" data-readonly="<?= $t['readOnly'] ? '1' : '0' ?>">
                <h2 id="t-<?= $e($t['id']) ?>"><?= $e($t['label']) ?></h2>
                <p><?= $e($t['desc']) ?></p>
                <div class="last" data-last><?= $lastRun($t['last']) ?></div>
                <div class="acts">
                  <?php if ($t['readOnly']): ?>
                  <button class="btn btn-primary sm" type="button" data-run="<?= $e($t['id']) ?>" data-dry="0"><svg class="icon icon-sm" aria-hidden="true"><use href="#i-play"/></svg>Ellenőrzés</button>
                  <?php else: ?>
                  <button class="btn btn-secondary sm" type="button" data-run="<?= $e($t['id']) ?>" data-dry="1" aria-label="Szárazfuttatás: <?= $e($t['label']) ?>"><svg class="icon icon-sm" aria-hidden="true"><use href="#i-flask"/></svg>Szárazfuttatás</button>
                  <button class="btn btn-primary sm" type="button" data-run="<?= $e($t['id']) ?>" data-dry="0" aria-label="Éles futtatás: <?= $e($t['label']) ?>"><svg class="icon icon-sm" aria-hidden="true"><use href="#i-play"/></svg>Éles futtatás</button>
                  <?php endif; ?>
                </div>
              </section>
              <?php endforeach; ?>
            </div>

            <section class="card" id="resultCard" aria-labelledby="h-result" hidden tabindex="-1">
              <div class="card-head">
                <div><h2 id="h-result">Eredmény</h2><div class="result-meta" id="resultMeta" style="margin-top:6px"></div></div>
                <button class="btn btn-ghost sm" type="button" id="resultClose"><svg class="icon icon-sm" aria-hidden="true"><use href="#i-x"/></svg>Bezárás</button>
              </div>
              <ol class="run-lines" id="resultLines"></ol>
            </section>
          </div>

          <aside class="stack" aria-label="Rendszerinformációk">
            <section class="card" aria-labelledby="h-sys">
              <h2 id="h-sys">Beállítások állapota</h2>
              <dl class="kv" style="margin-top:16px">
                <?php foreach ($checklist as $c): ?>
                <div><dt><?= $e($c['label']) ?></dt><dd><?= $badge($c['ok'], $c['note'], $c['note']) ?></dd></div>
                <?php endforeach; ?>
                <div><dt>PHP-verzió</dt><dd><?= $e(PHP_VERSION) ?></dd></div>
                <div><dt>Alkalmazás</dt><dd><?= $e($version) ?></dd></div>
              </dl>
            </section>
            <section class="card" aria-labelledby="h-cron">
              <h2 id="h-cron">Időzítés (cron)</h2>
              <p class="cron-hint">A tárhely vezérlőpultjában ezeket a címeket kell időzítve meghívni. A címben a titkos cron-kulcs van: ne oszd meg.</p>
              <?php foreach ($cronUrls as $name => $url): ?>
              <span class="copy-label" id="cl-<?= $e(md5($name)) ?>"><?= $e($name) ?></span>
              <div class="copy-box"><code title="<?= $e($url) ?>"><?= $e($url) ?></code><button class="btn btn-ghost sm" type="button" data-copy="<?= $e($url) ?>" aria-describedby="cl-<?= $e(md5($name)) ?>"><svg class="icon icon-sm" aria-hidden="true"><use href="#i-copy"/></svg>Másolás</button></div>
              <?php endforeach; ?>
            </section>
          </aside>
        </div>
      </section>

      <!-- ================= NÉZET: NAPLÓ (#naplo) ================= -->
      <section class="view" id="v-naplo" aria-labelledby="h-naplo" hidden>
        <div class="page-head">
          <div>
            <div class="page-title"><h1 id="h-naplo" tabindex="-1">Napló</h1></div>
            <p class="page-sub">Az összes futás, a szárazfuttatásokkal együtt. Az éles futások a Google Sheet „Napló” lapjára is felkerülnek.</p>
          </div>
          <div class="page-actions">
            <button class="btn btn-secondary" type="button" id="logRefresh"><svg class="icon icon-sm" aria-hidden="true"><use href="#i-refresh"/></svg>Frissítés</button>
          </div>
        </div>
        <div class="card table-card">
          <div class="toolbar" role="search" aria-label="Napló szűrése">
            <div class="search">
              <svg class="icon" aria-hidden="true"><use href="#i-search"/></svg>
              <label class="sr-only" for="logq">Keresés a naplóban</label>
              <input class="input" id="logq" type="search" placeholder="Keresés a naplóban…" autocomplete="off">
            </div>
            <label class="sr-only" for="logOk">Eredmény</label>
            <select class="select" id="logOk"><option value="">Minden eredmény</option><option value="ok">OK</option><option value="err">Hiba</option></select>
            <label class="sr-only" for="logMode">Mód</label>
            <select class="select" id="logMode"><option value="">Minden mód</option><option value="live">Éles</option><option value="dry">Száraz</option></select>
            <span class="spacer"></span>
            <button type="button" class="btn btn-secondary" id="logExport"><svg class="icon icon-sm" aria-hidden="true"><use href="#i-download"/></svg>Exportálás</button>
          </div>
          <div class="table-wrap" id="logTable"></div>
          <div class="pager" id="logPager"></div>
        </div>
      </section>

    </main>
  </div>
</div>

<!-- Megerősítő dialógus (közös) -->
<dialog id="dlg" aria-labelledby="dlgTitle" aria-describedby="dlgDesc">
  <div class="dlg-body"><h2 id="dlgTitle"></h2><p id="dlgDesc"></p><div class="field" id="dlgField" hidden><label for="dlgInput" id="dlgLabel"></label><input class="input" id="dlgInput" type="text" autocomplete="off"></div></div>
  <div class="dlg-foot"><button class="btn btn-secondary" type="button" id="dlgCancel">Mégse</button><button class="btn btn-danger" type="button" id="dlgConfirm"></button></div>
</dialog>

<div class="sr-only" id="liveStatus" role="status"></div>
<div class="sr-only" id="liveAlert" role="alert"></div>
<div class="toasts" id="toasts"></div>
<script src="assets/admin.js?v=<?= $e($version) ?>"></script>
</body>
</html>

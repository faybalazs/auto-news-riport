<?php
/**
 * Az alkalmazás kerete (Auris admin sablon app shell): oldalsáv jogosultság szerint,
 * ügyfélváltó a felső sávban, profilmenü, egyszeri üzenetek.
 *
 * @var \Xperion\Auth\Access $access
 * @var string $page @var string $title @var string $csrf @var string $content
 * @var list<array{type:string,message:string}> $flash
 */
$nav = static function (string $p, string $icon, string $label, ?string $perm = null, string $extra = '') use ($access, $page, $e, $ic): string {
    if ($perm !== null && !$access->can($perm)) {
        return '';
    }
    $cur = $p === (['poszt' => 'posztok', 'otlet' => 'otletek'][$page] ?? $page) ? ' aria-current="page"' : '';
    return '<a class="nav-item" href="index.php?p=' . $p . '" title="' . $e($label) . '"' . $cur . '>' . $ic($icon, 'icon') . '<span class="nav-label">' . $e($label) . '</span>' . $extra . '</a>';
};
$client = $access->client();
$roleLabel = \Xperion\Auth\Roles::LABELS[$access->role() ?? ''] ?? 'nincs ügyfél';
$ini = implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim((string) $access->user['name'])) ?: [], 0, 2)));
?><!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= $e($title) ?> · Sajtófigyelés</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/app.css?v=<?= $e($version) ?>">
</head>
<body data-page="<?= $e($jsPage ?? $page) ?>">
<?php require __DIR__ . '/icons.php'; ?>
<script type="application/json" id="appData"><?= json_encode(['csrf' => $csrf, 'page' => $jsPage ?? $page, 'flash' => $flash, 'data' => $data ?? [], 'me' => ['id' => $access->userId(), 'operator' => $access->isOperator(), 'role' => $access->role(), 'clientId' => $access->clientId(), 'perms' => array_values(array_filter(array_keys(\Xperion\Auth\Roles::PERMISSIONS), [$access, 'can']))], 'clients' => array_map(static fn ($c) => ['id' => (int) $c['id'], 'name' => $c['name']], $access->clients()), 'roles' => ['labels' => \Xperion\Auth\Roles::LABELS, 'desc' => \Xperion\Auth\Roles::DESCRIPTIONS, 'perms' => \Xperion\Auth\Roles::PERMISSIONS, 'grants' => \Xperion\Auth\Roles::GRANTS]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<div class="shell" id="shell" data-nav="open">
  <a class="skip" href="#main" id="skipLink">Ugrás a tartalomra</a>
  <nav class="sidebar" id="sidebar" aria-label="Fő menü">
    <div class="brand">
      <div class="brand-mark xp" aria-hidden="true"><?= $ic('file', 'icon') ?></div>
      <div class="brand-text"><b>Sajtófigyelés</b><span>Auris Dynamics</span></div>
    </div>
    <?= $nav('attekintes', 'home', 'Áttekintés') ?>
    <div class="nav-group-title">Havi munka</div>
    <?= $nav('cikkek', 'list', 'Havi cikkek') ?>
    <?= $nav('posztok', 'linkedin', 'LinkedIn-posztok') ?>
    <?= $nav('otletek', 'bulb', 'Posztötletek', 'ideas') ?>
    <?= $nav('szabalyok', 'bookmark', 'Posztszabályok', 'rules') ?>
    <?php if ($access->can('users')): ?><div class="nav-group-title">Adminisztráció</div><?php endif; ?>
    <?= $nav('forrasok', 'globe', 'Források', 'sources') ?>
    <?= $nav('felhasznalok', 'users', 'Felhasználók', 'users') ?>
    <?= $nav('ugyfelek', 'folder', 'Ügyfelek', 'ops') ?>
    <?= $nav('vezerlo', 'settings', 'Vezérlőpult', 'ops') ?>
    <?= $nav('naplo', 'clock', 'Napló', 'log') ?>
    <div class="sidebar-foot">
      <a class="nav-item nav-ext" href="index.php?p=kezikonyv" target="_blank" rel="noopener" title="Felhasználói kézikönyv (új ablakban nyílik)"><?= $ic('help', 'icon') ?><span class="nav-label">Felhasználói kézikönyv<span class="sr-only"> (új ablakban nyílik)</span></span><?= $ic('external', 'icon icon-sm nav-ext-ic') ?></a>
      <button class="nav-item collapse-btn" type="button" id="collapseBtn" aria-expanded="true" title="Menü összecsukása"><svg class="icon" aria-hidden="true" id="collapseIcon"><use href="#i-chev-left"/></svg><span class="nav-label" id="collapseLabel">Összecsukás</span></button>
    </div>
  </nav>
  <div class="scrim" id="scrim"></div>

  <div class="main-col">
    <header class="topbar">
      <button class="icon-btn menu-toggle" type="button" id="menuToggle" aria-label="Menü megnyitása" aria-controls="sidebar" aria-expanded="false"><?= $ic('menu', 'icon') ?></button>
      <div class="pop-wrap">
        <button class="workspace" type="button" id="wsBtn" aria-haspopup="menu" aria-expanded="false" aria-controls="wsMenu" title="Ügyfél váltása"><?= $ic('folder') ?><span><?= $e($client['name'] ?? 'Nincs ügyfél') ?></span><small><?= count($access->clients()) > 1 ? 'váltás' : 'ügyfél' ?></small><?= $ic('chev-down') ?></button>
        <div class="pop menu" id="wsMenu" role="menu" aria-label="Ügyfél váltása" hidden style="left:0;top:calc(100% + 6px);min-width:260px">
          <div class="pop-title" role="presentation"><?= count($access->clients()) > 1 ? 'Ügyfél váltása' : 'Ön ehhez az ügyfélhez fér hozzá' ?></div>
          <?php foreach ($access->clients() as $c): ?>
          <form method="post" action="index.php?p=ugyfelvaltas" role="none"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="client_id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="vissza" value="<?= $e($page) ?>">
            <button role="menuitemradio" aria-checked="<?= (int) $c['id'] === $access->clientId() ? 'true' : 'false' ?>" type="submit"><?= $ic((int) $c['id'] === $access->clientId() ? 'check' : 'folder') ?><?= $e($c['name']) ?></button></form>
          <?php endforeach; ?>
          <?php if (!$access->clients()): ?><p class="meta" style="padding:6px 12px">Még nincs ügyfél.</p><?php endif; ?>
          <?php if ($access->can('ops')): ?><hr role="separator"><a role="menuitem" href="index.php?p=ugyfelek"><?= $ic('settings') ?>Ügyfelek kezelése</a><?php endif; ?>
        </div>
      </div>
      <span style="flex:1"></span>
      <div class="topbar-actions">
        <button class="icon-btn" type="button" id="densityBtn" aria-pressed="false" title="Kompakt sűrűség" aria-label="Kompakt sűrűség"><?= $ic('rows', 'icon') ?></button>
        <button class="icon-btn" type="button" id="themeBtn" title="Téma: rendszer" aria-label="Téma váltása, jelenleg: rendszer"><svg class="icon" aria-hidden="true"><use href="#i-monitor" id="themeIcon"/></svg></button>
        <div class="pop-wrap">
          <button class="profile-btn" type="button" id="profileBtn" aria-haspopup="menu" aria-expanded="false" aria-controls="profileMenu" aria-label="Profil: <?= $e($access->user['name']) ?>"><span class="avatar" aria-hidden="true"><?= $e(mb_strtoupper($ini)) ?></span><?= $ic('chev-down') ?></button>
          <div class="pop right menu" id="profileMenu" role="menu" aria-label="Profil" hidden>
            <div class="menu-head" role="presentation"><span class="avatar" aria-hidden="true"><?= $e(mb_strtoupper($ini)) ?></span><div><b><?= $e($access->user['name']) ?></b><span class="meta"><?= $e($roleLabel) ?> · <?= $e($access->user['email']) ?></span></div></div>
            <hr role="separator">
            <a role="menuitem" href="index.php?p=profil"><?= $ic('key') ?>Jelszó módosítása</a>
            <hr role="separator">
            <form method="post" action="index.php?p=kilepes" role="none"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><button role="menuitem" type="submit" class="danger"><?= $ic('logout') ?>Kilépés</button></form>
          </div>
        </div>
      </div>
    </header>

    <main class="content" id="main">
<?= $content ?>
    </main>
    <footer class="app-foot"><span>© <?= substr(XPERION_RELEASE, 0, 4) ?> Auris Dynamics Zrt. Minden jog fenntartva.</span><span>Sajtófigyelés <?= $e($version) ?> · <?= $e(str_replace('-', '. ', XPERION_RELEASE)) ?>.</span></footer>
  </div>
</div>
<div class="pop menu" id="rowMenu" role="menu" aria-label="Sor műveletei" hidden></div>
<dialog id="dlg" aria-labelledby="dlgTitle" aria-describedby="dlgDesc">
  <div class="dlg-body"><h2 id="dlgTitle"></h2><p id="dlgDesc"></p><div class="field" id="dlgField" hidden><label for="dlgInput" id="dlgLabel"></label><input class="input" id="dlgInput" type="text" autocomplete="off"></div></div>
  <div class="dlg-foot"><button class="btn btn-secondary" type="button" id="dlgCancel">Mégse</button><button class="btn btn-danger" type="button" id="dlgConfirm"></button></div>
</dialog>
<dialog id="fDlg" aria-labelledby="fDlgTitle">
  <form id="fDlgForm" novalidate>
    <div class="dlg-body"><h2 id="fDlgTitle"></h2><div class="stack" id="fDlgBody" style="gap:16px;margin-top:12px"></div></div>
    <div class="dlg-foot"><button class="btn btn-secondary" type="button" id="fDlgCancel">Mégse</button><button class="btn btn-primary" type="submit" id="fDlgOk"></button></div>
  </form>
</dialog>
<div class="sr-only" id="liveStatus" role="status"></div>
<div class="sr-only" id="liveAlert" role="alert"></div>
<div class="toasts" id="toasts"></div>
<script src="assets/app.js?v=<?= $e($version) ?>"></script>
</body>
</html>

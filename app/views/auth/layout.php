<?php
/** Belépés nélküli oldalak kerete (az admin sablon #belepes elrendezése). @var string $content @var string $title */
?><!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= $e($title) ?> · Sajtófigyelés</title>
<script>try{var t=localStorage.getItem('xp-theme'),d=localStorage.getItem('xp-density');if(t!=='light'&&t!=='dark')t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t);if(d)document.documentElement.setAttribute('data-density',d);}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap">
<link rel="stylesheet" href="assets/app.css?v=<?= $e($version) ?>">
</head>
<body data-page="auth">
<?php require dirname(__DIR__) . '/icons.php'; ?>
<div class="auth">
  <aside class="auth-side">
    <div class="auth-brand"><span class="brand-mark" aria-hidden="true"><span>A</span></span><div><b>Sajtófigyelés</b><span>Auris Dynamics</span></div></div>
    <div class="auth-message">
      <span class="eyebrow">TARTALMI MUNKATÉR</span>
      <h2>A hónap cikkei és posztjai egy helyen</h2>
      <p>Döntsön a havi cikkekről, hagyja jóvá a LinkedIn-posztokat, és küldjön be saját posztötletet.</p>
      <div class="auth-points"><span><?= $ic('check', 'icon') ?>Havi sajtófigyelés</span><span><?= $ic('check', 'icon') ?>Jóváhagyási folyamat</span><span><?= $ic('check', 'icon') ?>Közös tartalomkezelés</span></div>
    </div>
    <small>© <?= substr(XPERION_RELEASE, 0, 4) ?> Auris Dynamics Zrt. · Sajtófigyelés <?= $e($version) ?> · <?= $e(str_replace('-', '. ', XPERION_RELEASE)) ?>.</small>
  </aside>
  <main class="auth-main">
    <div class="auth-display-controls"><div class="display-controls" role="group" aria-label="Megjelenési beállítások">
      <button type="button" class="display-control" id="themeBtn" aria-pressed="false" title="Téma: világos"><svg class="icon icon-sm" aria-hidden="true"><use href="#i-sun" id="themeIcon"/></svg><span id="themeLabel">Világos</span></button>
      <button type="button" class="display-control" id="densityBtn" aria-pressed="false" title="Sűrűség: normál"><?= $ic('rows') ?><span id="densityLabel">Normál</span></button>
    </div></div>
    <?= $content ?>
  </main>
</div>
<div class="sr-only" id="liveStatus" role="status"></div>
<div class="sr-only" id="liveAlert" role="alert"></div>
<div class="toasts" id="toasts"></div>
<script src="assets/app.js?v=<?= $e($version) ?>"></script>
</body>
</html>

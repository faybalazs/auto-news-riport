<?php
/** Belépés nélküli oldalak kerete (az admin sablon #belepes elrendezése). @var string $content @var string $title */
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
<body data-page="auth">
<?php require dirname(__DIR__) . '/icons.php'; ?>
<div class="auth">
  <aside class="auth-side xp">
    <div class="brand" style="padding:0"><div class="brand-mark xp" aria-hidden="true"><?= $ic('file', 'icon') ?></div><div class="brand-text"><b>Sajtófigyelés</b><span style="color:#fff">Auris Dynamics</span></div></div>
    <div><h2>A hónap cikkei és posztjai egy helyen</h2><p style="margin-top:12px">Döntsön a havi cikkekről, hagyja jóvá a LinkedIn-posztokat, és küldjön be saját posztötletet.</p></div>
    <p style="font-size:var(--fs-xs)">© <?= substr(XPERION_RELEASE, 0, 4) ?> Auris Dynamics Zrt. · Sajtófigyelés <?= $e($version) ?> · <?= $e(str_replace('-', '. ', XPERION_RELEASE)) ?>.</p>
  </aside>
  <main class="auth-main"><?= $content ?></main>
</div>
<div class="sr-only" id="liveStatus" role="status"></div>
<div class="sr-only" id="liveAlert" role="alert"></div>
<div class="toasts" id="toasts"></div>
<script src="assets/app.js?v=<?= $e($version) ?>"></script>
</body>
</html>

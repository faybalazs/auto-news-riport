<?php
/**
 * Belépés kulccsal (az Auris admin sablon #belepes nézete, e-mail és SSO nélkül).
 *
 * @var string $error
 * @var bool $configured
 * @var string $version
 */
$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>Belépés · Xperion automatizálás</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="assets/admin.css?v=<?= $e($version) ?>">
</head>
<body>
<?php require __DIR__ . '/icons.php'; ?>
<div class="auth" id="v-belepes">
  <aside class="auth-side xp">
    <div class="brand" style="padding:0"><div class="brand-mark xp" aria-hidden="true"><svg class="icon" aria-hidden="true"><use href="#i-file"/></svg></div><div class="brand-text"><b>Xperion automatizálás</b><span style="color:#fff">Auris Dynamics</span></div></div>
    <div><h2>Sajtófigyelés és posztok egy helyen</h2><p style="margin-top:12px">Havi sajtófigyelés, élő cikkfeltöltés és LinkedIn-posztok az Xperion számára, a közös Google Sheetre építve.</p></div>
    <p style="font-size:var(--fs-xs)">© <?= date('Y') ?> Auris Dynamics</p>
  </aside>
  <main class="auth-main">
    <form class="auth-card" id="loginForm" method="post" action="index.php" aria-labelledby="h-belepes">
      <div><h1 id="h-belepes" tabindex="-1">Belépés</h1><p class="page-sub">Adja meg a hozzáférési kulcsot.</p></div>
      <?php if (!$configured): ?>
      <div class="banner bn-info" role="status">
        <svg class="icon" aria-hidden="true"><use href="#i-info"/></svg>
        <div class="b-body"><b>A belépési kulcs még nincs beállítva</b><span>Töltse ki az <code>admin_key</code> értékét a config.php-ban (legalább 16 karakter), és töltse fel újra.</span></div>
      </div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
      <div class="banner bn-error" id="loginErr" role="alert" tabindex="-1">
        <svg class="icon" aria-hidden="true"><use href="#i-x-circle"/></svg>
        <div class="b-body"><b>Sikertelen belépés</b><span><?= $e($error) ?></span></div>
      </div>
      <?php endif; ?>
      <div class="field">
        <label for="l-key">Hozzáférési kulcs</label>
        <div class="ig"><input class="input" id="l-key" name="kulcs" type="password" autocomplete="current-password" required <?= $error !== '' ? 'aria-invalid="true" aria-describedby="loginErr"' : '' ?> autofocus><button class="icon-btn pw-toggle" type="button" aria-label="Kulcs megjelenítése" aria-pressed="false" aria-controls="l-key"><svg class="icon icon-sm" aria-hidden="true"><use href="#i-eye"/></svg></button></div>
        <div aria-live="polite"><span class="caps" id="capsWarn" hidden><svg class="icon icon-sm" aria-hidden="true"><use href="#i-alert"/></svg>A Caps Lock be van kapcsolva</span></div>
      </div>
      <input type="hidden" name="muvelet" value="belepes">
      <button class="btn btn-primary block" type="submit" id="loginBtn"><svg class="icon icon-sm" aria-hidden="true"><use href="#i-login"/></svg>Belépés</button>
      <p class="auth-foot"><span>Verzió: <?= $e($version) ?></span></p>
    </form>
  </main>
</div>
<div class="sr-only" id="liveStatus" role="status"></div>
<div class="sr-only" id="liveAlert" role="alert"></div>
<div class="toasts" id="toasts"></div>
<script src="assets/admin.js?v=<?= $e($version) ?>"></script>
</body>
</html>

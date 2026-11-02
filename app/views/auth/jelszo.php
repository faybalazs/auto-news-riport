<?php /** @var array|null $who @var bool $invite */ ?>
<?php if ($who === null): ?>
<div class="auth-card">
  <div><h1 tabindex="-1">A link nem érvényes</h1><p class="page-sub">Lejárt vagy már felhasználták. Kérjen új linket az adminisztrátorától.</p></div>
  <a class="btn btn-secondary block" href="index.php?p=belepes"><?= $ic('login') ?>Belépés</a>
</div>
<?php else: ?>
<form class="auth-card" method="post" aria-labelledby="h-pw" data-form="auth" novalidate>
  <div><h1 id="h-pw" tabindex="-1"><?= $invite ? 'Üdvözöljük, ' . $e($who['name']) . '!' : 'Új jelszó beállítása' ?></h1>
    <p class="page-sub"><?= $invite ? 'Állítsa be a jelszavát a belépéshez.' : 'Adja meg az új jelszavát.' ?> Fiók: <b><?= $e($who['email']) ?></b></p></div>
  <?php require __DIR__ . '/_error.php'; ?>
  <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
  <input type="hidden" name="email" value="<?= $e($who['email']) ?>" autocomplete="username">
  <div class="field"><label for="p-1">Jelszó</label><input class="input" id="p-1" name="jelszo" type="password" autocomplete="new-password" minlength="10" required aria-describedby="p-1-h"><span class="help" id="p-1-h">Legalább 10 karakter.</span></div>
  <div class="field"><label for="p-2">Jelszó még egyszer</label><input class="input" id="p-2" name="jelszo2" type="password" autocomplete="new-password" required></div>
  <button class="btn btn-primary block" type="submit"><?= $ic('check') ?><?= $invite ? 'Fiók aktiválása' : 'Jelszó mentése' ?></button>
</form>
<?php endif; ?>

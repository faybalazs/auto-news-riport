<form class="auth-card" method="post" action="index.php?p=belepes" aria-labelledby="h-belepes" data-form="auth">
  <div><span class="eyebrow">ÜDVÖZÖLJÜK ÚJRA</span><h1 id="h-belepes" tabindex="-1">Belépés</h1><p>Lépjen be a saját fiókjával.</p></div>
  <?php $errorTitle = 'Sikertelen belépés'; require __DIR__ . '/_error.php'; ?>
  <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
  <div class="field"><label for="l-email">E-mail-cím</label><input class="input" id="l-email" name="email" type="email" autocomplete="username" required value="<?= $e($email) ?>" <?= $error ? 'aria-invalid="true" aria-describedby="formErr"' : '' ?>></div>
  <div class="field">
    <div class="auth-row"><label for="l-pass">Jelszó</label><button type="button" class="link-btn" id="forgot">Elfelejtett jelszó?</button></div>
    <div class="ig"><input class="input" id="l-pass" name="jelszo" type="password" autocomplete="current-password" required><button class="icon-btn pw-toggle" type="button" aria-label="Jelszó megjelenítése" aria-pressed="false" aria-controls="l-pass"><?= $ic('eye') ?></button></div>
    <div aria-live="polite"><span class="caps" id="capsWarn" hidden><?= $ic('alert') ?>A Caps Lock be van kapcsolva</span></div>
  </div>
  <button class="btn btn-primary block" type="submit"><?= $ic('login') ?>Belépés</button>
  <p class="help" id="forgotHelp" hidden>Kérjen jelszó-visszaállító linket az adminisztrátorától (az ügyfél-adminisztrátortól vagy az üzemeltetőtől). A link 24 óráig érvényes.</p>
</form>

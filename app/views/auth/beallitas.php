<form class="auth-card" method="post" action="index.php" aria-labelledby="h-setup" data-form="auth" novalidate>
  <div><h1 id="h-setup" tabindex="-1">Első beállítás</h1><p class="page-sub">Hozza létre az első üzemeltetői fiókot. Ehhez a config.php-ban megadott beállítókulcs kell; később nincs rá szükség.</p></div>
  <?php require __DIR__ . '/_error.php'; ?>
  <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
  <div class="field"><label for="s-key">Beállítókulcs (admin_key)</label><input class="input" id="s-key" name="kulcs" type="password" autocomplete="off" required></div>
  <div class="field"><label for="s-name">Név</label><input class="input" id="s-name" name="nev" autocomplete="name" required value="<?= $e($old['nev'] ?? '') ?>"></div>
  <div class="field"><label for="s-email">E-mail-cím</label><input class="input" id="s-email" name="email" type="email" autocomplete="username" required value="<?= $e($old['email'] ?? '') ?>"></div>
  <div class="field"><label for="s-pass">Jelszó</label><input class="input" id="s-pass" name="jelszo" type="password" autocomplete="new-password" minlength="10" required aria-describedby="s-pass-h"><span class="help" id="s-pass-h">Legalább 10 karakter.</span></div>
  <div class="field"><label for="s-pass2">Jelszó még egyszer</label><input class="input" id="s-pass2" name="jelszo2" type="password" autocomplete="new-password" required></div>
  <button class="btn btn-primary block" type="submit"><?= $ic('check') ?>Fiók létrehozása</button>
</form>

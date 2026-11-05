<?php
/** @var \Xperion\Auth\Access $access @var string $csrf */
$sub = 'Személyes fiókadatok és jelszó módosítása.';
$actions = '<form method="post" action="index.php?p=kilepes"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><button class="btn btn-ghost" type="submit">' . $ic('logout') . 'Kilépés</button></form>';
$roleLabel = \Xperion\Auth\Roles::LABELS[$access->role() ?? ''] ?? '';
$ini = implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim((string) $access->user['name'])) ?: [], 0, 2)));
echo '<div class="form-page">';
require __DIR__ . '/_head.php';
?>
<div class="profile-layout">
  <form class="panel profile-form" id="pwForm" novalidate aria-labelledby="h-pw">
    <div class="panel-head"><div><span class="kicker">BIZTONSÁG</span><h2 id="h-pw">Jelszó módosítása</h2><p>Legalább 10 karakter</p></div><span class="icon-tile blue" aria-hidden="true"><?= $ic('key', 'icon') ?></span></div>
    <div class="profile-fields">
      <input type="text" autocomplete="username" value="<?= $e($access->user['email']) ?>" hidden>
      <div class="field"><label for="pw-cur">Jelenlegi jelszó</label><input class="input" id="pw-cur" type="password" autocomplete="current-password" required></div>
      <div class="field"><label for="pw-new">Új jelszó</label><input class="input" id="pw-new" type="password" autocomplete="new-password" minlength="10" required></div>
      <div class="field"><label for="pw-new2">Új jelszó még egyszer</label><input class="input" id="pw-new2" type="password" autocomplete="new-password" required></div>
      <button class="btn btn-primary" type="submit" id="pwSave"><?= $ic('check') ?>Jelszó mentése</button>
    </div>
  </form>
  <section class="panel account-card" aria-labelledby="h-me">
    <div class="account-avatar" aria-hidden="true"><?= $e(mb_strtoupper($ini)) ?></div>
    <span class="kicker">FIÓK</span>
    <h2 id="h-me"><?= $e($access->user['name']) ?></h2>
    <?php if ($roleLabel !== ''): ?><span class="badge b-ai"><?= $ic('key', 'icon') ?><?= $e($roleLabel) ?></span><?php endif; ?>
    <dl>
      <div><dt>Név</dt><dd><?= $e($access->user['name']) ?></dd></div>
      <div><dt>E-mail</dt><dd><?= $e($access->user['email']) ?></dd></div>
      <div><dt>Utolsó belépés</dt><dd><?= $e(\Xperion\Web\View::dateHu($access->user['last_login_at'])) ?></dd></div>
    </dl>
  </section>
</div>
</div>

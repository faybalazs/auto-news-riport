<?php /** @var \Xperion\Auth\Access $access */ require __DIR__ . '/_head.php'; ?>
<div class="layout">
  <form class="card widget stack" id="pwForm" novalidate aria-labelledby="h-pw" style="gap:16px;max-width:560px">
    <div class="widget-head"><div class="wh"><span class="ic-tile t-blue" aria-hidden="true"><?= $ic('key', 'icon') ?></span><div><h2 id="h-pw">Jelszó módosítása</h2><p class="meta">Legalább 10 karakter</p></div></div></div>
    <input type="text" autocomplete="username" value="<?= $e($access->user['email']) ?>" hidden>
    <div class="field"><label for="pw-cur">Jelenlegi jelszó</label><input class="input" id="pw-cur" type="password" autocomplete="current-password" required></div>
    <div class="field"><label for="pw-new">Új jelszó</label><input class="input" id="pw-new" type="password" autocomplete="new-password" minlength="10" required></div>
    <div class="field"><label for="pw-new2">Új jelszó még egyszer</label><input class="input" id="pw-new2" type="password" autocomplete="new-password" required></div>
    <div><button class="btn btn-primary" type="submit" id="pwSave"><?= $ic('check') ?>Jelszó mentése</button></div>
  </form>
  <aside class="stack">
    <section class="card widget" aria-labelledby="h-me">
      <div class="widget-head"><div class="wh"><span class="ic-tile t-blue" aria-hidden="true"><?= $ic('user', 'icon') ?></span><div><h2 id="h-me">Fiók</h2><p class="meta"><?= $e(\Xperion\Auth\Roles::LABELS[$access->role() ?? ''] ?? '') ?></p></div></div></div>
      <dl class="kv">
        <div><dt>Név</dt><dd><?= $e($access->user['name']) ?></dd></div>
        <div><dt>E-mail</dt><dd><?= $e($access->user['email']) ?></dd></div>
        <div><dt>Utolsó belépés</dt><dd><?= $e(\Xperion\Web\View::dateHu($access->user['last_login_at'])) ?></dd></div>
      </dl>
    </section>
  </aside>
</div>

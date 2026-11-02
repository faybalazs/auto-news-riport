<?php
/** @var \Xperion\Auth\Access $access */
$sub = $access->isOperator()
    ? 'Minden ügyfél felhasználói. Egy személy több ügyfélnél is dolgozhat, ügyfelenként más szerepkörrel.'
    : 'A(z) ' . $e($access->client()['name'] ?? '') . ' felhasználói. Meghívhatja a munkatársait; az üzemeltető fiókját nem módosíthatja.';
$actions = '<button class="btn btn-primary" type="button" id="inviteBtn">' . $ic('plus') . 'Meghívás</button>';
require __DIR__ . '/_head.php';
?>
<div class="card table-card">
  <div class="bulkbar" id="uBulk" hidden>
    <b id="uBulkCount">0 kiválasztva</b>
    <button type="button" class="btn btn-secondary sm" id="uBulkDisable"><?= $ic('x-circle') ?>Letiltás</button>
    <button type="button" class="btn btn-danger-outline sm" id="uBulkDelete"><?= $ic('trash') ?>Törlés…</button>
    <button type="button" class="btn btn-ghost sm" id="uBulkClear">Kijelölés megszüntetése</button>
  </div>
  <div class="toolbar" id="uToolbar" role="search" aria-label="Felhasználók szűrése">
    <div class="search"><?= $ic('search', 'icon') ?><label class="sr-only" for="uQ">Keresés név vagy e-mail szerint</label><input class="input" id="uQ" type="search" placeholder="Név vagy e-mail…" autocomplete="off"></div>
    <label class="sr-only" for="uRole">Szerepkör</label><select class="select" id="uRole"><option value="">Minden szerepkör</option></select>
    <label class="sr-only" for="uSt">Állapot</label><select class="select" id="uSt"><option value="">Minden állapot</option><option value="aktiv">Aktív</option><option value="meghivva">Meghívva</option><option value="letiltva">Letiltva</option></select>
  </div>
  <div class="table-wrap" id="userTable"></div>
  <div class="pager" id="userPager"></div>
</div>
<section class="card widget" aria-labelledby="h-roles">
  <div class="widget-head"><div><h2 id="h-roles">Szerepkörök és jogosultságok</h2><p class="meta">Ügyfelenként adható; az üzemeltető minden ügyfélhez hozzáfér</p></div></div>
  <div class="table-wrap mini-table" style="border:1px solid var(--border);border-radius:var(--r-lg)" id="roleMatrix"></div>
</section>

<dialog id="userDlg" aria-labelledby="userDlgTitle">
  <form id="userForm" novalidate>
    <div class="dlg-body">
      <h2 id="userDlgTitle">Felhasználó meghívása</h2>
      <p id="userDlgDesc">A meghívott linket kap a jelszó beállításához (7 napig érvényes). Amíg nincs e-mail-küldés, a linket Ön küldi el neki.</p>
      <div class="field"><label for="u-name">Név</label><input class="input" id="u-name" autocomplete="off" required></div>
      <div class="field"><label for="u-email">E-mail-cím</label><input class="input" id="u-email" type="email" autocomplete="off" required></div>
      <div class="field" id="u-client-f"><label for="u-client">Ügyfél</label><select class="select" id="u-client"></select></div>
      <fieldset class="field-set"><legend class="label">Szerepkör</legend><div class="role-choices" id="u-roles"></div></fieldset>
      <p class="error" id="userErr" role="alert" hidden></p>
    </div>
    <div class="dlg-foot"><button class="btn btn-secondary" type="button" id="userCancel">Mégse</button><button class="btn btn-primary" type="submit" id="userSubmit">Meghívó létrehozása</button></div>
  </form>
</dialog>

<dialog id="linkDlg" aria-labelledby="linkDlgTitle">
  <div class="dlg-body">
    <h2 id="linkDlgTitle">Meghívó link</h2>
    <p id="linkDlgDesc">Küldje el ezt a linket a felhasználónak (e-mailben vagy üzenetben). Egyszer használható.</p>
    <div class="copy-box"><code id="linkVal"></code><button class="btn btn-ghost sm" type="button" id="linkCopy"><?= $ic('copy') ?>Másolás</button></div>
  </div>
  <div class="dlg-foot"><button class="btn btn-primary" type="button" id="linkClose">Kész</button></div>
</dialog>

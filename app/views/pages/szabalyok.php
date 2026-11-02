<?php
/** @var \Xperion\Auth\Access $access */
$sub = 'A posztgenerálás egyetlen szabályforrása: hangnem, szerkezet, hossz, hashtagek, poszttípusok, kerülendők, kép. A módosítás a következő generálásnál már érvényes.';
$actions = '<button class="btn btn-primary" type="button" id="ruleAdd">' . $ic('plus') . 'Új szabály</button>';
require __DIR__ . '/_head.php';
?>
<div class="card table-card">
  <div class="toolbar" role="search" aria-label="Szabályok szűrése"><div class="search"><?= $ic('search', 'icon') ?><label class="sr-only" for="ruleQ">Keresés a szabályok között</label><input class="input" id="ruleQ" type="search" placeholder="Keresés megnevezésben, értékben…" autocomplete="off"></div><span class="spacer"></span><span class="meta" id="ruleCount"></span></div>
  <div class="table-wrap" id="ruleTable"></div>
</div>
<dialog id="ruleDlg" aria-labelledby="ruleDlgTitle">
  <form id="ruleForm" novalidate>
    <div class="dlg-body">
      <h2 id="ruleDlgTitle">Új szabály</h2>
      <div class="field"><label for="r-name">Megnevezés</label><input class="input" id="r-name" required maxlength="190" autocomplete="off" placeholder="pl. Hangnem, Hossz, Kerülendő"></div>
      <div class="field"><label for="r-value">Érték</label><textarea class="input" id="r-value" rows="6" required></textarea></div>
      <p class="error" id="ruleErr" role="alert" hidden></p>
    </div>
    <div class="dlg-foot"><button class="btn btn-secondary" type="button" id="ruleCancel">Mégse</button><button class="btn btn-primary" type="submit" id="ruleSubmit">Mentés</button></div>
  </form>
</dialog>

<?php
$sub = 'Felhasználói műveletek (ki, mit, mikor) és a futások. Hibánál később e-mail is megy az üzemeltetőnek.';
require __DIR__ . '/_head.php';
?>
<div class="tabs" role="tablist" aria-label="Napló">
  <button class="tab" role="tab" id="tab-audit" aria-selected="true" aria-controls="p-audit" tabindex="0"><?= $ic('users') ?>Műveletek</button>
  <button class="tab" role="tab" id="tab-runs" aria-selected="false" aria-controls="p-runs" tabindex="-1"><?= $ic('clock') ?>Futások</button>
</div>
<div class="card table-card" id="p-audit" role="tabpanel" aria-labelledby="tab-audit">
  <div class="toolbar" role="search" aria-label="Műveletek szűrése"><div class="search"><?= $ic('search', 'icon') ?><label class="sr-only" for="aQ">Keresés a műveletek között</label><input class="input" id="aQ" type="search" placeholder="Keresés…" autocomplete="off"></div></div>
  <div class="table-wrap" id="auditTable"></div><div class="pager" id="auditPager"></div>
</div>
<div class="card table-card" id="p-runs" role="tabpanel" aria-labelledby="tab-runs" hidden>
  <div class="toolbar" role="search" aria-label="Futások szűrése"><div class="search"><?= $ic('search', 'icon') ?><label class="sr-only" for="rQ">Keresés a futások között</label><input class="input" id="rQ" type="search" placeholder="Keresés…" autocomplete="off"></div></div>
  <div class="table-wrap" id="runTable"></div><div class="pager" id="runPager"></div>
</div>

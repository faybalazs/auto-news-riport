<?php
/** @var \Xperion\Auth\Access $access @var array $data */
$all = !empty($data['all']);
$sub = $all ? 'Felhasználói műveletek (ki, mit, mikor) és a futások. Az időzített futások hibájáról e-mailt kap. Megőrzés: műveletek 2 év, futások 90 nap.'
    : 'Az ügyfél felhasználóinak műveletei: ki, mit, mikor döntött, hagyott jóvá vagy módosított. Megőrzés: 2 év.';
$actions = '<button class="btn btn-secondary" type="button" id="aCsv">' . $ic('download') . 'Letöltés (CSV)</button>';
require __DIR__ . '/_head.php';
$cats = ['Döntések', 'Cikkek', 'Posztok', 'Havi e-mail', 'Feltöltés', 'Források', 'Futások', 'Felhasználók', 'Belépés', 'Beállítások'];
?>
<?php if ($all): ?>
<div class="tabs admin-tabs" role="tablist" aria-label="Napló">
  <button class="tab" role="tab" id="tab-audit" aria-selected="true" aria-controls="p-audit" tabindex="0"><?= $ic('users') ?>Műveletek</button>
  <button class="tab" role="tab" id="tab-runs" aria-selected="false" aria-controls="p-runs" tabindex="-1"><?= $ic('clock') ?>Futások <span class="count" id="runErrCount" hidden></span></button>
</div>
<?php endif; ?>
<div class="card table-card" id="p-audit" role="tabpanel" aria-labelledby="tab-audit">
  <div class="toolbar log-filters" role="search" aria-label="Műveletek szűrése">
    <div class="search"><?= $ic('search', 'icon') ?><label class="sr-only" for="aQ">Keresés a műveletek között</label><input class="input" id="aQ" type="search" placeholder="Keresés…" autocomplete="off"></div>
    <?php if ($all): ?><label class="sr-only" for="aClient">Ügyfél</label><select class="select" id="aClient"><option value="">Minden ügyfél</option></select><?php endif; ?>
    <label class="sr-only" for="aUser">Felhasználó</label><select class="select" id="aUser"><option value="">Mindenki</option></select>
    <label class="sr-only" for="aCat">Művelettípus</label><select class="select" id="aCat"><option value="">Minden művelet</option><?php foreach ($cats as $c): ?><option><?= $e($c) ?></option><?php endforeach; ?></select>
    <label class="date-f"><span class="sr-only">Ettől</span><input class="input" type="date" id="aFrom"></label>
    <label class="date-f"><span class="sr-only">Eddig</span><input class="input" type="date" id="aTo"></label>
  </div>
  <div class="table-wrap" id="auditTable"></div><div class="pager" id="auditPager"></div>
</div>
<?php if ($all): ?>
<div class="card table-card" id="p-runs" role="tabpanel" aria-labelledby="tab-runs" hidden>
  <div class="toolbar" role="search" aria-label="Futások szűrése"><div class="search"><?= $ic('search', 'icon') ?><label class="sr-only" for="rQ">Keresés a futások között</label><input class="input" id="rQ" type="search" placeholder="Keresés…" autocomplete="off"></div><label class="choice"><input type="checkbox" id="rErr"> Csak a hibás futások</label></div>
  <div class="table-wrap" id="runTable"></div><div class="pager" id="runPager"></div>
</div>
<?php endif; ?>

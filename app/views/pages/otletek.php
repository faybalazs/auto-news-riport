<?php
/** @var \Xperion\Auth\Access $access */
$sub = 'Saját posztötletek: rendezvény, díj, partnerség, esettanulmány, csapat. Egy ötletből egy poszt készül a Posztszabályok szerint.';
$actions = '<a class="btn btn-primary" href="index.php?p=otlet">' . $ic('plus') . 'Új ötlet</a>';
require __DIR__ . '/_head.php';
?>
<div class="card table-card">
  <div class="toolbar" role="search" aria-label="Ötletek szűrése">
    <div class="search"><?= $ic('search', 'icon') ?><label class="sr-only" for="ideaQ">Keresés az ötletek között</label><input class="input" id="ideaQ" type="search" placeholder="Téma, üzenet, beküldő…" autocomplete="off"></div>
    <label class="sr-only" for="ideaSt">Állapot</label><select class="select" id="ideaSt"><option value="">Minden állapot</option><?php foreach (\Xperion\Posts\IdeaService::STATUS as $k => $l): ?><option value="<?= $e($k) ?>"><?= $e($l) ?></option><?php endforeach; ?></select>
  </div>
  <div class="table-wrap" id="ideaTable"></div>
  <div class="pager" id="ideaPager"></div>
</div>

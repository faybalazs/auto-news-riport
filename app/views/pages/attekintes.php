<?php
/** @var \Xperion\Auth\Access $access @var array $data */
$client = $access->client();
$sub = $e($client['name'] ?? 'Nincs ügyfél') . ' · ' . $e(\Xperion\Web\View::monthHu(new DateTimeImmutable('first day of previous month')));
$todos = [];
if ($access->can('ops') && !$access->clients()) {
    $todos[] = ['warn', 'folder', 'Hozza létre az első ügyfelet', 'Név és weboldal; utána jöhetnek a felhasználók.', 'index.php?p=ugyfelek', 'Ügyfelek'];
}
if ($client && $access->can('users') && ($data['memberCount'] ?? 0) === 0) {
    $todos[] = ['info', 'users', 'Hívja meg az ügyfél felhasználóit', 'Ügyfél-adminisztrátor, tartalomkezelő. A meghívó link kimásolható.', 'index.php?p=felhasznalok', 'Felhasználók'];
}
if ($client && $access->can('ops') && ($client['status'] ?? '') === 'beallitas') {
    $todos[] = ['ai', 'settings', 'Az ügyfél beállítása folyamatban', 'Források, kulcsszavak és a weboldal-kapcsolat a következő lépésekben kerül be.', 'index.php?p=ugyfelek', 'Beállítási lépések'];
}
if (!$todos) {
    $todos[] = ['ok', 'check', 'Nincs teendő', 'Az első havi futás után itt jelennek meg a döntésre váró cikkek és a jóváhagyandó posztok.', 'index.php?p=cikkek', 'Havi cikkek'];
}
require __DIR__ . '/_head.php';
?>
<section aria-labelledby="h-todo" class="todo-sec">
  <h2 id="h-todo" class="sec-title">Teendők <span class="meta">· <?= count($todos) ?> tétel</span></h2>
  <div class="todo-grid">
    <?php foreach ($todos as [$tone, $icon, $t, $s, $href, $cta]): ?>
    <section class="card todo-w tw-<?= $tone ?>" aria-label="<?= $e($t) ?>">
      <div class="tw-head"><span class="tw-ic"><?= $ic($icon, 'icon') ?></span><div><h3><?= $e($t) ?></h3></div></div>
      <p><?= $e($s) ?></p>
      <a class="btn btn-secondary sm" href="<?= $e($href) ?>"><?= $e($cta) ?><?= $ic('chev-right') ?></a>
    </section>
    <?php endforeach; ?>
  </div>
</section>
<section class="card widget" aria-labelledby="w-sum">
  <div class="widget-head"><div><h2 id="w-sum">Vezetői összefoglaló</h2><p class="meta">A havi futás készíti</p></div></div>
  <div class="empty-state"><b>Még nincs havi összefoglaló</b><span>Az első havi futás után itt jelenik meg a hónap összefoglalója, alatta a számok, a döntések megoszlása és a források. A gyűjtés és az értékelés a 4–5. lépésben készül el.</span></div>
</section>

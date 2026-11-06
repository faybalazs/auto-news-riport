<?php
/** @var \Xperion\Auth\Access $access @var array $data */
use Xperion\Monthly\OverviewService;
use Xperion\Web\View;

$client = $access->client();
$label = static fn (string $m): string => View::monthHu(new DateTimeImmutable(sprintf('20%s-%s-01', substr($m, 0, 2), substr($m, 2, 2))));
$months = $data['months'] ?? [];
$month = $data['month'] ?? '';
$s = $data['stats'] ?? null;
$prev = $data['prev'] ?? null;
$rep = $data['report'] ?? null;
$t = $data['todo'] ?? [];
$sub = $e($client['name'] ?? 'Nincs ügyfél') . ($month !== '' ? ' · ' . $e($label($month)) : '');
$actions = $months ? '<label class="sr-only" for="period">Időszak</label><select class="select month-select" id="period">'
    . implode('', array_map(static fn ($m) => '<option value="' . $e($m) . '"' . ($m === $month ? ' selected' : '') . '>' . $e($label($m)) . '</option>', $months)) . '</select>' : '';

/* Teendők: soronként külön widget, típus szerinti színnel. [tónus, ikon, cím, szám|null, rövid meta (fejléc), részlet (HTML, a csempe tartalma), link, gomb]
   A fejléc csak a címet és egy rövid sort mutatja; a hosszabb részlet (pl. posztkód és téma) a tartalomba kerül. */
$todos = [];
if ($access->can('ops') && !$access->clients()) {
    $todos[] = ['warn', 'folder', 'Hozza létre az első ügyfelet', null, 'Ügyfelek', 'Név és weboldal; utána jöhetnek a felhasználók.', 'index.php?p=ugyfelek', 'Ügyfelek'];
}
if (($t['decide'] ?? 0) > 0) {
    $todos[] = ['warn', 'list', 'Cikk vár döntésre', $t['decide'], $e($label($t['decideMonth'])), 'Mehet, LinkedIn poszt vagy Nem kell.' . (($t['decideOlder'] ?? 0) > 0 ? ' Korábbi hónapokban további ' . (int) $t['decideOlder'] . '.' : ''), 'index.php?p=cikkek&honap=' . $e($t['decideMonth']), 'Döntések'];
}
foreach ([['postsClient', 'LinkedIn-poszt jóváhagyásra vár', 'Ügyfélnél'], ['postsApproved', 'Közzétehető a LinkedInen', 'Jóváhagyva'], ['postsDraft', 'LinkedIn-tervezet, ügyfélnek küldhető', 'Tervezet']] as [$k, $ttl, $st]) {
    if (!empty($t[$k])) {
        $first = explode(' · ', (string) $t[$k][0])[0];
        $todos[] = ['li', 'linkedin', $ttl, count($t[$k]), $st . ' állapotú poszt', implode('<br>', array_map($e, $t[$k])), 'index.php?p=poszt&kod=' . rawurlencode($first), ['postsClient' => 'Jóváhagyás', 'postsApproved' => 'Megnyitás', 'postsDraft' => 'Átnézés'][$k]];
    }
}
foreach ($t['mail'] ?? [] as $mm) {
    $todos[] = [$mm['error'] ? 'warn' : 'info', 'send', $mm['error'] ? 'A havi e-mail küldése nem sikerült' : 'Havi e-mail jóváhagyásra vár', null, 'Havi e-mail', $e($mm['subject']) . ' · az ügyfél még nem kapta meg', 'index.php?p=vezerlo', 'Jóváhagyás'];
}
if (($t['ideas'] ?? 0) > 0) {
    $todos[] = ['ai', 'sparkle', 'Posztötlet generálásra vár', $t['ideas'], 'Posztötletek', 'Küldje posztgenerálásra, vagy egészítse ki.', 'index.php?p=otletek', 'Ötletek'];
}
if (($t['upload'] ?? 0) > 0) {
    $todos[] = ['info', 'upload', 'Feltöltésre vár', $t['upload'], 'Mehet jelölésű cikk', 'Az óránkénti futás magyarul és angolul feltölti a weboldalra, vagy indítsa a Havi cikkek oldalról.', 'index.php?p=cikkek', 'Havi cikkek'];
}
if (($t['uploadErr'] ?? 0) > 0) {
    $todos[] = ['warn', 'alert', 'Feltöltési hiba', $t['uploadErr'], 'Mehet jelölésű cikk', 'Nem került fel a weboldalra. Az okot a Havi cikkek oldalon látja.', 'index.php?p=cikkek', 'Havi cikkek'];
}
if (($t['missingRun'] ?? '') !== '') {
    $todos[] = ['info', 'play', 'A havi futás még nem készült el', null, $e($label($t['missingRun'])), 'A cron minden hónap 1-jén futtatja, vagy indítsa a Vezérlőpultról.', 'index.php?p=vezerlo', 'Vezérlőpult'];
}
if ($client && $access->can('users') && ($t['members'] ?? 0) === 0) {
    $todos[] = ['info', 'users', 'Hívja meg az ügyfél felhasználóit', null, 'Felhasználók', 'Ügyfél-adminisztrátor, tartalomkezelő. A meghívó link kimásolható.', 'index.php?p=felhasznalok', 'Felhasználók'];
}
if ($client && $access->can('ops') && ($client['status'] ?? '') === 'beallitas') {
    $todos[] = ['ai', 'settings', 'Az ügyfél beállítása folyamatban', null, 'Ügyfelek', 'Adatátvétel a Google Sheetből, majd állítsa Aktívra az ügyfelet.', 'index.php?p=ugyfelek', 'Ügyfelek'];
}
if (!$todos) {
    $todos[] = ['ok', 'check', 'Nincs teendő', null, 'Minden kész', 'Minden döntés és jóváhagyás kész. Köszönjük!', 'index.php?p=cikkek', 'Havi cikkek'];
}

/* KPI-k: címke → érték → változás az előző hónaphoz → az utolsó öt hónap oszlopai → időszak */
$kpis = [['n', 'Cikkek a hónapban', 'list', 'blue'], ['strong', 'Erősen javasolt', 'check', 'green'], ['mehet', 'Mehet (feltöltésre)', 'upload', 'amber'], ['li', 'LinkedIn poszt jelölés', 'linkedin', 'violet']];

require __DIR__ . '/_head.php';
?>
<section class="panel todo-panel" aria-labelledby="h-todo">
  <div class="panel-head"><div><span class="kicker">TEENDŐK</span><h2 id="h-todo">Mi vár Önre</h2><p><?= count($todos) ?> tétel · jogosultsága szerint</p></div></div>
  <div class="todo-rows">
    <?php foreach ($todos as $ti => [$tone, $icon, $twTitle, $n, $twMeta, $twDetail, $href, $cta]): ?>
    <?php $tc = ['warn' => 'amber', 'info' => 'blue', 'li' => 'blue', 'ok' => 'green', 'ai' => 'violet'][$tone] ?? 'blue'; ?>
    <div class="todo-row">
      <span class="icon-tile <?= $tc ?>" aria-hidden="true"><?= $ic($icon, 'icon') ?></span>
      <div><h3 id="tw-<?= $ti ?>"><?= $e($twTitle) ?></h3><p><?= $twMeta ?> · <?= $twDetail ?></p></div>
      <span class="todo-n"><?= $n !== null ? (int) $n : '' ?></span>
      <a class="btn btn-secondary sm" href="<?= $e($href) ?>" aria-describedby="tw-<?= $ti ?>"><?= $e($cta) ?><?= $ic('chev-right') ?></a>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if (!$months): ?>
<section class="panel sum-panel" aria-labelledby="w-sum">
  <div class="panel-head"><div><span class="kicker">VEZETŐI ÖSSZEFOGLALÓ</span><h2 id="w-sum">A hónap összefoglalója</h2><p>A havi futás készíti</p></div></div>
  <div class="empty-state"><b>Még nincs havi adat</b><span>Az adatátvétel vagy az első havi futás után itt jelenik meg a hónap összefoglalója, alatta a számok, a döntések megoszlása és a cikkek forrásonként.</span></div>
</section>
<?php else: ?>
<?php $monthLabel = $label($month); require __DIR__ . '/_osszefoglalo.php'; ?>

<section class="kpis" aria-label="A hónap számai">
  <?php foreach ($kpis as [$k, $kl, $kic, $kc]):
      $c = (int) ($s[$k] ?? 0);
      $p = $prev === null ? null : (int) ($prev[$k] ?? 0);
      if ($p === null) {
          $delta = '<span class="delta flat">Nincs összehasonlító adat</span>';
      } elseif ($c === $p) {
          $delta = '<span class="delta flat">= <small>változatlan az előző hónaphoz</small></span>';
      } else {
          $up = $c > $p;
          $delta = '<span class="delta' . ($up ? '' : ' down') . '">' . ($up ? '↗ +' : '↘ −') . abs($c - $p) . ' db <small>az előző hónaphoz</small></span>';
      }
      $ser = array_slice(array_values($data['series'][$k] ?? []), -5);
      $ser = array_merge(array_fill(0, 5 - count($ser), 0), $ser);
      $smax = max(1, ...$ser); ?>
  <div class="kpi">
    <div class="kpi-top"><span><?= $e($kl) ?></span><span class="icon-tile <?= $kc ?>" aria-hidden="true"><?= $ic($kic, 'icon') ?></span></div>
    <strong><?= $c ?></strong>
    <?= $delta ?>
    <div class="sparkline" role="img" aria-label="Az utolsó öt hónap: <?= $e(implode(', ', $ser)) ?>"><?php foreach ($ser as $v): ?><i style="height:<?= max(8, (int) round($v / $smax * 100)) ?>%"></i><?php endforeach; ?></div>
    <small><?= $e($label($month)) ?></small>
  </div>
  <?php endforeach; ?>
</section>

<div class="overview-bottom">
  <?php
  $colors = ['mehet' => '#4f8a68', 'li' => '#4b7da2', 'nem' => '#ad6173', 'open' => '#c79338', 'notrec' => '#8a929b'];
  $tot = (int) ($s['n'] ?? 0);
  $parts = [];
  foreach (OverviewService::DECISION_PARTS as $k => $kl) {
      $parts[] = [$kl, (int) ($s[$k] ?? 0), $colors[$k]];
  }
  $decided = (int) ($s['mehet'] ?? 0) + (int) ($s['li'] ?? 0) + (int) ($s['nem'] ?? 0);
  $grad = [];
  $acc = 0.0;
  foreach ($parts as [, $v, $col]) {
      if ($v > 0 && $tot > 0) {
          $from = $acc;
          $acc += $v / $tot * 100;
          $grad[] = $col . ' ' . round($from, 2) . '% ' . round($acc, 2) . '%';
      }
  }
  ?>
  <section class="panel distribution" aria-labelledby="w-dec">
    <div class="panel-head">
      <div><span class="kicker">DÖNTÉSEK</span><h2 id="w-dec">Döntések megoszlása</h2><p><?= $e($label($month)) ?> · <?= $tot ?> cikk</p></div>
      <span class="total-pill"><?= $tot ?> cikk</span>
    </div>
    <?php if ($tot > 0): ?>
    <div class="donut-wrap">
      <div class="donut" style="background:conic-gradient(<?= $e(implode(', ', $grad)) ?>)" role="img" aria-label="<?= $e(implode(', ', array_map(static fn ($d) => $d[0] . ': ' . $d[1] . ' db', $parts))) ?>"><div><strong><?= $decided ?></strong><span>döntéssel</span></div></div>
      <ul class="legend">
        <?php foreach ($parts as [$kl, $v, $col]): ?><li><i style="background:<?= $col ?>"></i><span><?= $e($kl) ?></span><b><?= $v ?> db</b><small><?= round($v / $tot * 100) ?>%</small></li><?php endforeach; ?>
      </ul>
    </div>
    <?php else: ?>
    <div class="empty-state"><h3>Nincs cikk ebben a hónapban</h3></div>
    <?php endif; ?>
  </section>

  <?php $src = $data['sources'] ?? []; $max = max(1, ...array_map(static fn ($r) => $r['n'], $src ?: [['n' => 1]])); ?>
  <section class="panel source-panel" aria-labelledby="w-src">
    <div class="panel-head">
      <div><span class="kicker">FORRÁSOK</span><h2 id="w-src">Cikkek forrásonként</h2><p>A duplikátumok és kizárt témák nélkül</p></div>
      <?php if ($src): ?><div class="seg mini" role="group" aria-label="Megjelenítés" id="srcView"><button type="button" data-sv="chart" aria-pressed="true">Diagram</button><button type="button" data-sv="table" aria-pressed="false">Táblázat</button></div><?php endif; ?>
    </div>
    <?php if ($src): ?>
    <div class="bars" id="srcBars">
      <?php foreach ($src as $r): ?><div class="bar-row"><span><?= $e($r['name']) ?></span><div><i style="width:<?= round($r['n'] / $max * 100, 2) ?>%"></i></div><b><?= $r['n'] ?> db</b></div><?php endforeach; ?>
    </div>
    <div class="table-wrap mini" id="srcTable" hidden>
      <table><caption class="sr-only">Cikkek forrásonként</caption><thead><tr><th scope="col">Forrás</th><th scope="col">Cikkek</th><th scope="col">Arány</th></tr></thead>
      <tbody><?php foreach ($src as $r): ?><tr><td><?= $e($r['name']) ?></td><td><?= $r['n'] ?> db</td><td><?= $tot ? round($r['n'] / $tot * 100) : 0 ?>%</td></tr><?php endforeach; ?></tbody></table>
    </div>
    <?php else: ?>
    <div class="empty-state"><h3>Nincs cikk ebben a hónapban</h3></div>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>

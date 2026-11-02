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
$actions = $months ? '<label class="sr-only" for="period">Időszak</label><select class="select" id="period" style="width:auto">'
    . implode('', array_map(static fn ($m) => '<option value="' . $e($m) . '"' . ($m === $month ? ' selected' : '') . '>' . $e($label($m)) . '</option>', $months)) . '</select>' : '';

/* Teendők: soronként külön widget, típus szerinti színnel. [tónus, ikon, cím, szám|null, szöveg (HTML), link, gomb] */
$todos = [];
if ($access->can('ops') && !$access->clients()) {
    $todos[] = ['warn', 'folder', 'Hozza létre az első ügyfelet', null, 'Név és weboldal; utána jöhetnek a felhasználók.', 'index.php?p=ugyfelek', 'Ügyfelek'];
}
if (($t['decide'] ?? 0) > 0) {
    $todos[] = ['warn', 'list', 'Cikk vár döntésre', $t['decide'], $e($label($t['decideMonth'])) . ' · Mehet, LinkedIn poszt vagy Nem kell', 'index.php?p=cikkek&honap=' . $e($t['decideMonth']), 'Döntések'];
}
foreach ([['postsClient', 'LinkedIn-poszt jóváhagyásra vár'], ['postsApproved', 'Közzétehető a LinkedInen'], ['postsDraft', 'LinkedIn-tervezet, ügyfélnek küldhető']] as [$k, $ttl]) {
    if (!empty($t[$k])) {
        $first = explode(' · ', (string) $t[$k][0])[0];
        $todos[] = ['li', 'linkedin', $ttl, count($t[$k]), implode('<br>', array_map($e, $t[$k])), 'index.php?p=poszt&kod=' . rawurlencode($first), ['postsClient' => 'Jóváhagyás', 'postsApproved' => 'Megnyitás', 'postsDraft' => 'Átnézés'][$k]];
    }
}
foreach ($t['mail'] ?? [] as $mm) {
    $todos[] = [$mm['error'] ? 'warn' : 'info', 'send', $mm['error'] ? 'A havi e-mail küldése nem sikerült' : 'Havi e-mail jóváhagyásra vár', null, $e($mm['subject']) . ' · az ügyfél még nem kapta meg', 'index.php?p=vezerlo', 'Jóváhagyás'];
}
if (($t['ideas'] ?? 0) > 0) {
    $todos[] = ['ai', 'sparkle', 'Posztötlet generálásra vár', $t['ideas'], 'Küldje posztgenerálásra, vagy egészítse ki.', 'index.php?p=otletek', 'Ötletek'];
}
if (($t['upload'] ?? 0) > 0) {
    $todos[] = ['info', 'upload', 'Feltöltésre vár', $t['upload'], 'Mehet jelölésű cikk; az óránkénti futás magyarul és angolul feltölti a weboldalra.', 'index.php?p=cikkek', 'Havi cikkek'];
}
if (($t['uploadErr'] ?? 0) > 0) {
    $todos[] = ['warn', 'alert', 'Feltöltési hiba', $t['uploadErr'], 'Mehet cikk, amely nem került fel a weboldalra. Az okot a Havi cikkek oldalon látja.', 'index.php?p=cikkek', 'Havi cikkek'];
}
if (($t['missingRun'] ?? '') !== '') {
    $todos[] = ['info', 'play', 'A havi futás még nem készült el', null, $e($label($t['missingRun'])) . ' · a cron minden hónap 1-jén futtatja, vagy indítsa a Vezérlőpultról.', 'index.php?p=vezerlo', 'Vezérlőpult'];
}
if ($client && $access->can('users') && ($t['members'] ?? 0) === 0) {
    $todos[] = ['info', 'users', 'Hívja meg az ügyfél felhasználóit', null, 'Ügyfél-adminisztrátor, tartalomkezelő. A meghívó link kimásolható.', 'index.php?p=felhasznalok', 'Felhasználók'];
}
if ($client && $access->can('ops') && ($client['status'] ?? '') === 'beallitas') {
    $todos[] = ['ai', 'settings', 'Az ügyfél beállítása folyamatban', null, 'Adatátvétel a Google Sheetből, majd állítsa Aktívra az ügyfelet.', 'index.php?p=ugyfelek', 'Ügyfelek'];
}
if (!$todos) {
    $todos[] = ['ok', 'check', 'Nincs teendő', null, 'Minden döntés és jóváhagyás kész. Köszönjük!', 'index.php?p=cikkek', 'Havi cikkek'];
}

/* KPI-k: címke → érték → változás az előző hónaphoz → trendvonal → időszak */
$spark = static function (array $v): string {
    if (count($v) < 2) {
        return '';
    }
    $max = max($v) ?: 1;
    $n = count($v) - 1;
    $pts = implode(' ', array_map(static fn ($i, $x) => round($i / $n * 100, 1) . ',' . round(28 - $x / $max * 26, 1), array_keys($v), $v));
    return '<svg class="spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true"><polyline points="' . $pts . '" fill="none" stroke="var(--data-1)" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>';
};
$kpis = [['n', 'Cikkek a hónapban', 'list', 'blue'], ['strong', 'Erősen javasolt', 'arrow-up', 'green'], ['mehet', 'Mehet (feltöltésre)', 'upload', 'orange'], ['li', 'LinkedIn poszt jelölés', 'linkedin', 'purple']];

require __DIR__ . '/_head.php';
?>
<section aria-labelledby="h-todo" class="todo-sec">
  <h2 id="h-todo" class="sec-title">Teendők <span class="meta">· <?= count($todos) ?> tétel</span></h2>
  <div class="todo-grid">
    <?php foreach ($todos as $ti => [$tone, $icon, $twTitle, $n, $text, $href, $cta]): ?>
    <?php $tc = ['warn' => 't-orange', 'info' => 't-blue', 'li' => 't-li', 'ok' => 't-green', 'ai' => 't-purple'][$tone] ?? 't-blue'; ?>
    <section class="card widget todo-w" aria-labelledby="tw-<?= $ti ?>">
      <div class="widget-head"><div class="wh"><span class="ic-tile <?= $tc ?>" aria-hidden="true"><?= $ic($icon, 'icon') ?></span><div><h2 id="tw-<?= $ti ?>"><?= $e($twTitle) ?></h2><p class="meta"><?= $text ?></p></div></div></div>
      <div class="tw-body"><?php if ($n !== null): ?><span class="tw-n"><?= (int) $n ?></span><?php endif; ?><a class="btn btn-ghost sm" href="<?= $e($href) ?>"><?= $e($cta) ?><?= $ic('chev-right') ?></a></div>
    </section>
    <?php endforeach; ?>
  </div>
</section>

<?php if (!$months): ?>
<section class="card widget" aria-labelledby="w-sum">
  <div class="widget-head"><div class="wh"><span class="ic-tile t-purple" aria-hidden="true"><?= $ic('sparkle', 'icon') ?></span><div><h2 id="w-sum">Vezetői összefoglaló</h2><p class="meta">A havi futás készíti</p></div></div></div>
  <div class="empty-state"><b>Még nincs havi adat</b><span>Az adatátvétel vagy az első havi futás után itt jelenik meg a hónap összefoglalója, alatta a számok, a döntések megoszlása és a cikkek forrásonként.</span></div>
</section>
<?php else: ?>
<section class="card widget" aria-labelledby="w-sum">
  <div class="widget-head">
    <div class="wh"><span class="ic-tile t-purple" aria-hidden="true"><?= $ic('sparkle', 'icon') ?></span><div><h2 id="w-sum">Vezetői összefoglaló</h2><p class="meta"><?= $e($label($month)) ?><?= $rep ? ' · ' . $e(View::dateHu($rep['created_at'])) : '' ?></p></div></div>
    <?php if ($rep): ?><div class="head-actions"><span class="badge b-ai"><?= $ic('sparkle', 'icon') ?>AI által generált</span></div><?php endif; ?>
  </div>
  <?php if ($rep): ?>
  <dl class="kv"><?php foreach ($rep['summary_rows'] as $r): ?><div><dt><?= $e($r['label'] ?? '') ?></dt><dd><?= $e($r['value'] ?? '') ?></dd></div><?php endforeach; ?></dl>
  <?php if (!empty($rep['details'])): ?><div class="sum-text"><h3>Részletek</h3><p><?= $e($rep['details']) ?></p></div><?php endif; ?>
  <?php else: ?>
  <div class="empty-state"><b>Ehhez a hónaphoz nincs összefoglaló</b><span>A Google Sheetből átvett hónapokhoz nem készül; az új hónapokét a havi futás írja.</span></div>
  <?php endif; ?>
</section>

<div class="kpis" aria-label="A hónap számai">
  <?php foreach ($kpis as [$k, $kl, $kic, $kc]):
      $c = (int) ($s[$k] ?? 0);
      $p = $prev === null ? null : (int) ($prev[$k] ?? 0);
      if ($p === null) {
          $delta = '<span class="delta flat">Nincs összehasonlító adat</span>';
      } elseif ($c === $p) {
          $delta = '<span class="delta flat">Változatlan az előző hónaphoz</span>';
      } else {
          $up = $c > $p;
          $delta = '<span class="delta ' . ($up ? 'good' : 'bad') . '">' . $ic($up ? 'arrow-up' : 'arrow-down') . ($up ? '+' : '−') . abs($c - $p) . ' db <span style="font-weight:400">az előző hónaphoz</span></span>';
      } ?>
  <div class="card kpi k-<?= $kc ?>"><div class="kpi-top"><span class="k"><?= $e($kl) ?></span><span class="ic-tile t-<?= $kc ?>" aria-hidden="true"><?= $ic($kic, 'icon') ?></span></div><span class="v"><?= $c ?></span><?= $delta ?><?= $spark($data['series'][$k] ?? []) ?><span class="per meta"><?= $e($label($month)) ?></span></div>
  <?php endforeach; ?>
</div>

<div class="dash-grid">
  <?php
  $colors = ['mehet' => 'var(--data-3)', 'li' => 'var(--data-1)', 'nem' => 'var(--data-6)', 'open' => 'var(--data-4)', 'notrec' => 'var(--data-7)'];
  $tot = max(1, (int) ($s['n'] ?? 0));
  $parts = [];
  foreach (OverviewService::DECISION_PARTS as $k => $kl) {
      $parts[] = [$kl, (int) ($s[$k] ?? 0), $colors[$k]];
  }
  ?>
  <section class="card widget w-4" aria-labelledby="w-dec">
    <div class="widget-head"><div class="wh"><span class="ic-tile t-blue" aria-hidden="true"><?= $ic('chart', 'icon') ?></span><div><h2 id="w-dec">Döntések megoszlása</h2><p class="meta"><?= $e($label($month)) ?> · <?= (int) ($s['n'] ?? 0) ?> cikk</p></div></div></div>
    <?php if (($s['n'] ?? 0) > 0): ?>
    <div class="stack100" role="img" aria-label="<?= $e(implode(', ', array_map(static fn ($d) => $d[0] . ': ' . $d[1] . ' db', $parts))) ?>">
      <?php foreach ($parts as [, $v, $col]): if ($v > 0): ?><span style="width:<?= round($v / $tot * 100, 2) ?>%;background:<?= $col ?>"></span><?php endif; endforeach; ?>
    </div>
    <ul class="legend-list">
      <?php foreach ($parts as [$kl, $v, $col]): ?><li><span class="sw" style="background:<?= $col ?>"></span><?= $e($kl) ?><b><?= $v ?> db</b><span class="meta"><?= round($v / $tot * 100) ?>%</span></li><?php endforeach; ?>
    </ul>
    <?php else: ?>
    <div class="empty-state"><b>Nincs cikk ebben a hónapban</b></div>
    <?php endif; ?>
  </section>

  <?php $src = $data['sources'] ?? []; $max = max(1, ...array_map(static fn ($r) => $r['n'], $src ?: [['n' => 1]])); ?>
  <section class="card widget w-8" aria-labelledby="w-src">
    <div class="widget-head">
      <div class="wh"><span class="ic-tile t-blue" aria-hidden="true"><?= $ic('list', 'icon') ?></span><div><h2 id="w-src">Cikkek forrásonként</h2><p class="meta"><?= $e($label($month)) ?> · a duplikátumok és a kizárt témák nélkül</p></div></div>
      <?php if ($src): ?><div class="head-actions"><div class="seg" role="group" aria-label="Megjelenítés" id="srcView"><button type="button" data-sv="chart" aria-pressed="true">Diagram</button><button type="button" data-sv="table" aria-pressed="false">Táblázat</button></div></div><?php endif; ?>
    </div>
    <?php if ($src): ?>
    <ul class="hbar" id="srcBars">
      <?php foreach ($src as $r): ?><li><span><?= $e($r['name']) ?></span><span class="track"><span style="width:<?= round($r['n'] / $max * 100, 2) ?>%"></span></span><b><?= $r['n'] ?> db</b></li><?php endforeach; ?>
    </ul>
    <div class="mini-table table-wrap" id="srcTable" hidden style="border:1px solid var(--border);border-radius:var(--r-lg)">
      <table><caption class="sr-only">Cikkek forrásonként</caption><thead><tr><th scope="col">Forrás</th><th scope="col" class="num">Cikkek</th><th scope="col" class="num">Arány</th></tr></thead>
      <tbody><?php foreach ($src as $r): ?><tr><td><?= $e($r['name']) ?></td><td class="num"><?= $r['n'] ?> db</td><td class="num"><?= round($r['n'] / $tot * 100) ?>%</td></tr><?php endforeach; ?></tbody></table>
    </div>
    <?php else: ?>
    <div class="empty-state"><b>Nincs cikk ebben a hónapban</b></div>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>

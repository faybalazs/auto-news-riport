<?php
/** @var \Xperion\App $app @var \Xperion\Auth\Access $access */
$sub = 'Futások kézi indítása, a beállítások állapota és az időzítés. A szárazfuttatás semmit nem ír.';
require __DIR__ . '/_head.php';
$log = $app->log();
$desc = [
    'havi' => 'Minden hónap 1-jén 06:00-kor. Most a célhónapot határozza meg; a gyűjtés a 4. lépésben jön.',
    'orankenti' => 'Minden órában. A feltöltés, a posztgenerálás és az e-mail-küldés a későbbi lépésekben jön.',
];
$cronKey = $app->config->string('cron_key');
$base = \Xperion\Web\Http::baseUrl() . 'cron.php?k=' . rawurlencode($cronKey !== '' ? $cronKey : '<cron_key>');
$pending = $app->migrator()->pending();
?>
<div class="layout">
  <div class="stack">
    <div class="task-grid" role="list" aria-label="Feladatok">
      <?php foreach ($app->runner()->tasks() as $t): $last = $log->last($t->id()); ?>
      <section class="card task" role="listitem" aria-labelledby="t-<?= $e($t->id()) ?>" data-task="<?= $e($t->id()) ?>" data-label="<?= $e($t->label()) ?>">
        <h2 id="t-<?= $e($t->id()) ?>"><?= $e($t->label()) ?></h2>
        <p><?= $e($desc[$t->id()] ?? '') ?></p>
        <div class="last" data-last><?php if ($last): ?><span>Legutóbb: <?= $e($last->timeHu()) ?> · <?= $last->dry ? 'száraz' : 'éles' ?></span><?= $last->ok ? '<span class="badge b-success">' . $ic('check', 'icon') . 'OK</span>' : '<span class="badge b-danger">' . $ic('x-circle', 'icon') . 'Hiba</span>' ?><?php else: ?><span>Még nem futott</span><?php endif; ?></div>
        <div class="acts"><button class="btn btn-secondary sm" type="button" data-run="<?= $e($t->id()) ?>" data-dry="1"><?= $ic('flask') ?>Szárazfuttatás</button></div>
      </section>
      <?php endforeach; ?>
    </div>
    <section class="card widget" id="resultCard" aria-labelledby="h-result" hidden tabindex="-1">
      <div class="widget-head"><div><h2 id="h-result">Eredmény</h2><div class="result-meta" id="resultMeta"></div></div><button class="btn btn-ghost sm" type="button" id="resultClose"><?= $ic('x') ?>Bezárás</button></div>
      <ol class="run-lines" id="resultLines"></ol>
    </section>
  </div>
  <aside class="stack">
    <section class="card widget" aria-labelledby="h-sys">
      <div class="widget-head"><div><h2 id="h-sys">Beállítások állapota</h2><p class="meta">config.php · értékek nélkül</p></div></div>
      <dl class="kv">
        <?php foreach ($app->config->checklist() as $c): ?>
        <div><dt><?= $e($c['label']) ?></dt><dd><span class="badge <?= $c['ok'] ? 'b-success' : 'b-warning' ?>"><?= $ic($c['ok'] ? 'check' : 'alert', 'icon') ?><?= $e($c['note']) ?></span></dd></div>
        <?php endforeach; ?>
        <div><dt>Adatbázis-séma</dt><dd><span class="badge <?= $pending ? 'b-warning' : 'b-success' ?>"><?= $ic($pending ? 'alert' : 'check', 'icon') ?><?= $pending ? count($pending) . ' frissítés vár' : 'naprakész' ?></span></dd></div>
        <div><dt>PHP-verzió</dt><dd><?= $e(PHP_VERSION) ?></dd></div>
        <div><dt>Alkalmazás</dt><dd><?= $e($version) ?></dd></div>
      </dl>
    </section>
    <section class="card widget" aria-labelledby="h-cron">
      <div class="widget-head"><div><h2 id="h-cron">Időzítés (cron)</h2><p class="meta">A tárhely vezérlőpultjában kell beállítani</p></div></div>
      <p class="cron-hint">A címben a titkos cron-kulcs van: ne oszd meg.</p>
      <?php foreach (['Havi futás – minden hónap 1-jén 06:00' => $base . '&feladat=havi', 'Óránkénti futás – minden órában' => $base . '&feladat=orankenti'] as $name => $url): $id = 'cl-' . substr(md5($name), 0, 6); ?>
      <span class="copy-label" id="<?= $id ?>"><?= $e($name) ?></span>
      <div class="copy-box"><code title="<?= $e($url) ?>"><?= $e($url) ?></code><button class="btn btn-ghost sm" type="button" data-copy="<?= $e($url) ?>" aria-describedby="<?= $id ?>"><?= $ic('copy') ?>Másolás</button></div>
      <?php endforeach; ?>
    </section>
  </aside>
</div>

<?php
/**
 * A vezetői összefoglaló nyomtatható nézete (PDF-be menthető: Nyomtatás → Mentés PDF-ként; iPaden Megosztás → Nyomtatás).
 * Önálló oldal a layout nélkül. @var array $d @var callable $e @var string $version
 */
$r = $d['report'];
?><!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vezetői összefoglaló – <?= $e($d['client']) ?>, <?= $e($d['label']) ?></title>
<style>
  :root{ --ink:#141A33; --muted:#5B6275; --line:#E3E6EE; --accent:#2D388A; }
  *{ box-sizing:border-box; }
  body{ margin:0; font-family:Inter, -apple-system, "Segoe UI", Helvetica, Arial, sans-serif; color:var(--ink); background:#fff; line-height:1.55; }
  .page{ max-width:780px; margin:0 auto; padding:40px 32px 48px; }
  .bar{ display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:28px; }
  .brand{ font-weight:600; color:var(--accent); }
  .brand span{ display:block; font-weight:400; font-size:12px; color:var(--muted); }
  button{ font:inherit; font-weight:600; color:#fff; background:var(--accent); border:0; border-radius:10px; padding:10px 16px; min-height:44px; cursor:pointer; }
  .eyebrow{ font-size:12px; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); margin:0 0 6px; }
  h1{ font-size:26px; line-height:1.25; margin:0 0 18px; }
  .lead{ font-size:16px; margin:0 0 24px; }
  h2{ font-size:15px; margin:22px 0 8px; padding-top:14px; border-top:1px solid var(--line); }
  ul{ margin:0; padding-left:20px; } li{ margin:0 0 6px; font-size:14px; }
  dl{ margin:0; } dl div{ display:flex; justify-content:space-between; gap:16px; padding:8px 0; border-bottom:1px solid var(--line); font-size:14px; } dt{ color:var(--muted); } dd{ margin:0; font-weight:600; text-align:right; }
  footer{ margin-top:36px; padding-top:12px; border-top:1px solid var(--line); font-size:11px; color:var(--muted); display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  @media print{ .bar button{ display:none; } .page{ padding:0; max-width:none; } @page{ margin:18mm 16mm; } }
</style>
</head>
<body>
<div class="page">
  <div class="bar"><div class="brand">Sajtófigyelés<span>Auris Dynamics Zrt.</span></div><button type="button" id="printBtn">Nyomtatás / mentés PDF-be</button></div>
  <p class="eyebrow"><?= $e($d['client']) ?> · <?= $e($d['label']) ?></p>
  <h1>Vezetői összefoglaló</h1>
  <?php if (!empty($r['sections'])): ?>
    <?php if ((string) $r['details'] !== ''): ?><p class="lead"><?= $e($r['details']) ?></p><?php endif; ?>
    <?php foreach ($r['sections'] as $s): ?><h2><?= $e($s['title']) ?></h2><ul><?php foreach ($s['points'] as $pt): ?><li><?= $e($pt) ?></li><?php endforeach; ?></ul><?php endforeach; ?>
  <?php else: ?>
    <?php if ($r['summary_rows']): ?><dl><?php foreach ($r['summary_rows'] as $row): ?><div><dt><?= $e($row['label'] ?? '') ?></dt><dd><?= $e($row['value'] ?? '') ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
    <?php if ((string) $r['details'] !== ''): ?><h2>Részletek</h2><p><?= $e($r['details']) ?></p><?php endif; ?>
  <?php endif; ?>
  <footer><span>© <?= substr(XPERION_RELEASE, 0, 4) ?> Auris Dynamics Zrt. · AI által generált összefoglaló</span><span>Sajtófigyelés <?= $e($version) ?></span></footer>
</div>
<script src="assets/print.js?v=<?= $e($version) ?>"></script>
</body>
</html>

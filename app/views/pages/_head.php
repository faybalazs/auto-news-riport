<?php /** Oldalfejléc: cím, leírás, jobbra műveletek. @var string $title @var string $sub @var string $actions */ ?>
<?php
// Felirat a cím fölött (v2 oldalfej): ügyfél / terület
$eyeArea = ['attekintes' => 'Áttekintés', 'cikkek' => 'Havi munka', 'posztok' => 'Havi munka', 'poszt' => 'Havi munka', 'otletek' => 'Havi munka', 'otlet' => 'Havi munka', 'szabalyok' => 'Havi munka',
    'forrasok' => 'Adminisztráció', 'felhasznalok' => 'Adminisztráció', 'ugyfelek' => 'Adminisztráció', 'vezerlo' => 'Adminisztráció', 'naplo' => 'Adminisztráció', 'profil' => 'Fiók'][$page ?? ''] ?? 'Sajtófigyelés';
$eyeClient = (string) ($access->client()['name'] ?? 'Auris');
?>
<div class="page-head">
  <div>
    <span class="eyebrow"><?= $e(mb_strtoupper($eyeClient . ' / ' . $eyeArea)) ?></span>
    <div class="page-title"><h1 tabindex="-1" id="h-page"><?= $e($title) ?></h1><?= $badge ?? '' ?></div>
    <?php if (!empty($sub)): ?><p class="page-sub"><?= $sub ?></p><?php endif; ?>
  </div>
  <?php if (!empty($actions)): ?><div class="page-actions"><?= $actions ?></div><?php endif; ?>
</div>
<?php foreach ($flash as $f): ?>
<div class="banner <?= $f['type'] === 'success' ? 'bn-success' : 'bn-info' ?>" role="status"><?= $ic($f['type'] === 'success' ? 'check' : 'info', 'icon') ?><div class="b-body"><span><?= $e($f['message']) ?></span></div></div>
<?php endforeach; ?>

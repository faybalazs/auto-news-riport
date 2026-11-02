<?php /** Oldalfejléc: cím, leírás, jobbra műveletek. @var string $title @var string $sub @var string $actions */ ?>
<div class="page-head">
  <div>
    <div class="page-title"><h1 tabindex="-1" id="h-page"><?= $e($title) ?></h1><?= $badge ?? '' ?></div>
    <?php if (!empty($sub)): ?><p class="page-sub"><?= $sub ?></p><?php endif; ?>
  </div>
  <?php if (!empty($actions)): ?><div class="page-actions"><?= $actions ?></div><?php endif; ?>
</div>
<?php foreach ($flash as $f): ?>
<div class="banner <?= $f['type'] === 'success' ? 'bn-success' : 'bn-info' ?>" role="status"><?= $ic($f['type'] === 'success' ? 'check' : 'info', 'icon') ?><div class="b-body"><span><?= $e($f['message']) ?></span></div></div>
<?php endforeach; ?>

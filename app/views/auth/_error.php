<?php if (!empty($error)): ?>
<div class="banner bn-error" id="formErr" role="alert" tabindex="-1">
  <?= $ic('x-circle', 'icon') ?>
  <div class="b-body"><b><?= $e($errorTitle ?? 'Nem sikerült') ?></b><span><?= $e($error) ?></span></div>
</div>
<?php endif; ?>

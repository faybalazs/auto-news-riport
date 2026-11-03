<?php
/** @var \Xperion\Auth\Access $access @var array $data */
$idea = $data['idea'] ?? null;
$v = static fn (string $k): string => (string) ($idea[$k] ?? '');
$sub = $idea ? 'Beküldte: ' . $e($idea['submitter']) . ' · ' . $e(\Xperion\Web\View::dateHu($idea['created_at'])) : 'A *-gal jelölt mezők kötelezők. Mentés után bármikor szerkesztheti, vagy elküldheti posztgenerálásra.';
$badge = $idea ? ' <span class="badge ' . ['uj' => 'b-info', 'elkeszult' => 'b-success', 'jovahagyva' => 'b-success', 'utemezve' => 'b-info', 'elvetve' => 'b-neutral'][$idea['status']] . '">' . $e($idea['status_label']) . '</span>' : '';
$actions = '<a class="btn btn-ghost" href="index.php?p=otletek">' . $ic('chev-left') . 'Ötletek</a>';
require __DIR__ . '/_head.php';
$genOk = $idea && $idea['status'] !== 'elvetve' && $idea['post_code'] === '';
?>
<form class="card widget idea-form" id="ideaForm" novalidate aria-labelledby="h-if">
    <div class="widget-head"><div class="wh"><span class="ic-tile t-orange" aria-hidden="true"><?= $ic('bulb', 'icon') ?></span><div><h2 id="h-if"><?= $idea ? 'Az ötlet adatai' : 'Új posztötlet' ?></h2><p class="meta">A *-gal jelölt mezők kötelezők</p></div></div></div>
    <input type="hidden" id="f-code" value="<?= $e($v('code')) ?>">
    <div class="grid-2">
      <div class="field"><label for="f-from">Beküldő</label><input class="input" id="f-from" value="<?= $e($v('submitter')) ?>" maxlength="150" placeholder="a neve kerül ide, ha üres"></div>
      <div class="field"><label for="f-tipus">Poszttípus *</label><select class="select" id="f-tipus" required><option value="">Válasszon…</option><?php foreach ($data['types'] as $t): ?><option<?= $v('type') === $t ? ' selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></div>
      <div class="field full"><label for="f-tema">Téma *</label><input class="input" id="f-tema" required maxlength="255" value="<?= $e($v('topic')) ?>"></div>
      <div class="field full"><label for="f-uzenet">Fő üzenet *</label><textarea class="input" id="f-uzenet" rows="5" required><?= $e($v('message')) ?></textarea><span class="help">Mit szeretne elmondani? Tények, nevek, számok: a poszt csak abból dolgozik, ami itt van.</span></div>
      <div class="field full"><label for="f-link">Forrás- vagy fotólink <span class="opt">(nem kötelező)</span></label><input class="input" id="f-link" type="url" placeholder="https://" value="<?= $e($v('link')) ?>"></div>
      <div class="field"><label for="f-het">Kívánt hét <span class="opt">(nem kötelező)</span></label><input class="input" id="f-het" maxlength="60" placeholder="pl. 2026. 11. 16–22. vagy mindegy" value="<?= $e($v('wanted_week')) ?>"></div>
      <?php if ($idea): ?><div class="field"><label for="f-allapot">Állapot</label><select class="select" id="f-allapot"><?php foreach ($data['statuses'] as $k => $l): ?><option value="<?= $e($k) ?>"<?= $idea['status'] === $k ? ' selected' : '' ?>><?= $e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div class="field full"><label for="f-note">Megjegyzés <span class="opt">(nem kötelező)</span></label><textarea class="input" id="f-note" rows="2"><?= $e($v('note')) ?></textarea></div>
    </div>
    <div class="acts-row">
      <button class="btn btn-secondary" type="submit" id="ideaSave"><?= $ic('check') ?><?= $idea ? 'Mentés' : 'Ötlet mentése' ?></button>
      <?php if ($idea): ?>
      <button class="btn btn-primary" type="button" id="ideaGen"<?= $genOk ? '' : ' hidden' ?>><?= $ic('sparkle') ?>Küldés posztgenerálásra</button>
      <?php if ($idea['post_code'] !== ''): ?><a class="btn btn-primary" href="index.php?p=poszt&amp;kod=<?= $e(rawurlencode($idea['post_code'])) ?>"><?= $ic('linkedin') ?>Poszt megnyitása (<?= $e($idea['post_code']) ?>)</a><?php endif; ?>
      <button class="btn btn-ghost" type="button" id="ideaReject"<?= $idea['status'] === 'elvetve' ? ' hidden' : '' ?>><?= $ic('x') ?>Elvetés</button>
      <button class="btn btn-ghost danger" type="button" id="ideaDelete"><?= $ic('trash') ?>Törlés</button>
      <?php endif; ?>
    </div>
    <?php if ($idea && $idea['pending']): ?><div class="banner bn-info" role="status"><?= $ic('clock', 'icon') ?><div class="b-body"><span>Generálásra vár: az előző próba nem sikerült, az óránkénti futás újrapróbálja.</span></div></div><?php endif; ?>
    <p class="help" id="genHint"<?= $genOk ? '' : ' hidden' ?>>A generálás fél–egy percig tart (Claude Opus, kb. 15–25 cent). A kész poszt magától jóváhagyásra kerül (Ügyfélnél állapot), és az ügyfél-adminisztrátor e-mailt kap róla.</p>
  </form>

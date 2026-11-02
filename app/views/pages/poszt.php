<?php
/** @var \Xperion\Auth\Access $access @var array $data */
$p = $data['post'];
$stCls = ['tervezet' => 'b-neutral', 'ugyfelnel' => 'b-warning', 'jovahagyva' => 'b-success', 'megjelent' => 'b-info'][$p['status']] ?? 'b-neutral';
$badge = ' <span class="badge ' . $stCls . '" id="postStatus">' . $e($p['status_label']) . '</span>' . ($p['model'] !== '' ? ' <span class="badge b-ai">' . $ic('sparkle', 'icon') . 'AI által generált</span>' : '');
$sub = $e($p['topic']) . ($p['status_hu'] !== '' ? ' · ' . $e($p['status_label']) . ': ' . $e($p['status_hu']) : '');
$actions = '<a class="btn btn-ghost" href="index.php?p=posztok">' . $ic('chev-left') . 'Posztok</a><span id="postActs" class="acts-row"></span>'
    . '<button class="icon-btn" type="button" id="postMore" aria-haspopup="menu" aria-expanded="false" aria-label="További műveletek">' . $ic('more') . '</button>';
require __DIR__ . '/_head.php';
?>
<div class="banner bn-info" role="status" id="crBanner" hidden><?= $ic('msg', 'icon') ?><div class="b-body"><b>Módosítási kérés</b><span id="crText"></span></div></div>
<div class="layout post-layout">
  <div class="stack">
    <div class="tabs" role="tablist" aria-label="Nézetek">
      <button class="tab" role="tab" id="tab-elo" aria-selected="true" aria-controls="p-elo" tabindex="0"><?= $ic('eye') ?>Előnézet</button>
      <button class="tab" role="tab" id="tab-szerk" aria-selected="false" aria-controls="p-szerk" tabindex="-1"><?= $ic('edit') ?>Szerkesztés</button>
    </div>
    <section id="p-elo" role="tabpanel" aria-labelledby="tab-elo" class="stack">
      <div class="seg" role="group" aria-label="Előnézet módja" id="liMode"><button type="button" data-m="closed" aria-pressed="true">Zárt nézet</button><button type="button" data-m="open" aria-pressed="false">Kibontott nézet</button></div>
      <article class="li-card" aria-label="LinkedIn-előnézet">
        <div class="li-head"><span class="li-logo" aria-hidden="true"><?= $e(mb_strtoupper(mb_substr($data['company']['name'], 0, 1))) ?></span><div class="li-meta"><b><?= $e($data['company']['name']) ?></b><?php if ($data['company']['followers'] !== ''): ?><span><?= $e($data['company']['followers']) ?> követő</span><?php endif; ?><span>Most · <?= $ic('globe', 'icon icon-xs') ?></span></div><span class="li-follow">+ Követés</span></div>
        <div class="li-text" id="liText"></div>
        <canvas id="postCanvas" width="1200" height="1200" role="img" aria-label="A poszt képe"></canvas>
        <div class="li-react" aria-hidden="true"><span><?= $ic('thumb') ?>Tetszik</span><span><?= $ic('msg') ?>Hozzászólás</span><span><?= $ic('repeat') ?>Megosztás</span><span><?= $ic('send') ?>Küldés</span></div>
      </article>
      <div class="acts-row">
        <button class="btn btn-secondary" type="button" id="copyText"><?= $ic('copy') ?>Szöveg másolása</button>
        <button class="btn btn-secondary" type="button" id="dlImage"><?= $ic('download') ?>Kép letöltése (PNG)</button>
        <button class="btn btn-ghost" type="button" id="copyLink" hidden><?= $ic('copy') ?>Forráslink (első komment)</button>
      </div>
    </section>
    <section id="p-szerk" role="tabpanel" aria-labelledby="tab-szerk" hidden>
      <form class="card widget" id="postForm" novalidate>
        <p class="help">A poszt részei. A teljes szöveg ezekből áll össze (nyitó sor, kontextus, kiemelések, szemszög, zárás, hashtagek); a forráslink a szabályok szerint nem kerül bele.</p>
        <div class="grid-2">
          <div class="field full"><label for="pe-topic">Témakör</label><input class="input" id="pe-topic" maxlength="255"></div>
          <div class="field full"><label for="pe-opening">Nyitó sor</label><textarea class="input" id="pe-opening" rows="2"></textarea><span class="help">A „…több” előtt csak ez látszik.</span></div>
          <div class="field"><label for="pe-alt_opening_1">Alternatív nyitó 1</label><textarea class="input" id="pe-alt_opening_1" rows="2"></textarea></div>
          <div class="field"><label for="pe-alt_opening_2">Alternatív nyitó 2</label><textarea class="input" id="pe-alt_opening_2" rows="2"></textarea></div>
          <div class="field full"><label for="pe-context">Kontextus</label><textarea class="input" id="pe-context" rows="4"></textarea></div>
          <div class="field full"><label for="pe-highlights">Kiemelések</label><textarea class="input" id="pe-highlights" rows="4"></textarea></div>
          <div class="field full"><label for="pe-xperion_view">Szemszög</label><textarea class="input" id="pe-xperion_view" rows="3"></textarea></div>
          <div class="field full"><label for="pe-closing">Zárás</label><textarea class="input" id="pe-closing" rows="2"></textarea></div>
          <div class="field full"><label for="pe-hashtags">Hashtagek</label><input class="input" id="pe-hashtags"></div>
          <div class="field full"><label for="pe-source_link">Forráslink <span class="opt">(első kommentbe)</span></label><input class="input" id="pe-source_link" type="url" placeholder="https://"></div>
          <div class="field"><label for="pe-it">Kép címe</label><input class="input" id="pe-it" maxlength="200"></div>
          <div class="field"><label for="pe-is">Kép alcíme</label><input class="input" id="pe-is" maxlength="200"></div>
          <div class="field"><label for="pe-e1">Számpélda: mért <span class="opt">(nem kötelező)</span></label><input class="input" id="pe-e1" maxlength="200"></div>
          <div class="field"><label for="pe-e2">Számpélda: valós <span class="opt">(nem kötelező)</span></label><input class="input" id="pe-e2" maxlength="200"></div>
          <div class="field full"><label for="pe-note">Megjegyzés <span class="opt">(belső)</span></label><textarea class="input" id="pe-note" rows="2"></textarea></div>
        </div>
        <div class="acts-row"><button class="btn btn-primary" type="submit" id="peSave"><?= $ic('check') ?>Módosítások mentése</button><button class="btn btn-ghost" type="button" id="peReset">Elvetés</button></div>
      </form>
    </section>
  </div>
  <aside class="stack">
    <section class="card widget" aria-labelledby="h-open">
      <div class="widget-head"><div><h2 id="h-open">Nyitó sor</h2><p class="meta">A „…több” előtt csak ez látszik</p></div></div>
      <fieldset class="open-choices" id="openChoices"><legend class="sr-only">Nyitó sor választása</legend></fieldset>
    </section>
    <section class="card widget" aria-labelledby="h-src">
      <div class="widget-head"><div><h2 id="h-src">Adatok</h2><p class="meta">A poszt adatlapja</p></div></div>
      <dl class="kv" id="postKv"></dl>
    </section>
    <section class="card widget" aria-labelledby="h-hist">
      <div class="widget-head"><div><h2 id="h-hist">Előzmények</h2><p class="meta">Ki, mit, mikor</p></div></div>
      <ol class="timeline" id="postHist"></ol>
    </section>
  </aside>
</div>

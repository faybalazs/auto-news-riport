<?php
/** @var \Xperion\Auth\Access $access @var array $data */
$p = $data['post'];
$stCls = ['tervezet' => 'b-neutral', 'ugyfelnel' => 'b-warning', 'jovahagyva' => 'b-success', 'megjelent' => 'b-info'][$p['status']] ?? 'b-neutral';
$badge = '<span class="badge ' . $stCls . '" id="postStatus">' . $e($p['status_label']) . '</span>' . ($p['model'] !== '' ? '<span class="badge b-ai">' . $ic('sparkle', 'icon') . 'AI által generált</span>' : '');
?>
<div class="detail-head">
  <a class="back-link" href="index.php?p=posztok"><?= $ic('chev-left') ?>Posztok</a>
  <div>
    <div class="detail-title"><h1 tabindex="-1" id="h-page"><?= $e($p['code']) ?></h1><?= $badge ?></div>
    <p><?= $p['status_hu'] !== '' ? $e($p['status_label']) . ': ' . $e($p['status_hu']) : $e($p['topic']) ?></p>
  </div>
  <div class="detail-actions"><span id="postActs" class="acts-row"></span><button class="icon-btn" type="button" id="postMore" aria-haspopup="menu" aria-expanded="false" aria-label="További műveletek"><?= $ic('more') ?></button></div>
</div>
<div class="banner bn-info work-banner" role="status" id="workBanner" hidden><span class="spinner" aria-hidden="true"></span><div class="b-body"><b id="workTitle">Generálás folyamatban…</b><span id="workText"></span></div></div>
<div class="banner bn-info" role="status" id="crBanner" hidden><?= $ic('msg', 'icon') ?><div class="b-body"><b>Módosítási kérés</b><span id="crText"></span></div></div>
<div class="post-layout">
  <div class="post-main">
    <div class="tabs" role="tablist" aria-label="A poszt nézetei">
      <button class="tab" role="tab" id="tab-elo" aria-selected="true" aria-controls="p-elo" tabindex="0"><?= $ic('eye') ?>Előnézet</button>
      <button class="tab" role="tab" id="tab-szerk" aria-selected="false" aria-controls="p-szerk" tabindex="-1"><?= $ic('edit') ?>Szerkesztés</button>
      <button class="tab" role="tab" id="tab-adat" aria-selected="false" aria-controls="p-adat" tabindex="-1"><?= $ic('file') ?>Adatok</button>
      <button class="tab" role="tab" id="tab-hist" aria-selected="false" aria-controls="p-hist" tabindex="-1"><?= $ic('clock') ?>Előzmények <span class="count" id="histCount">0</span></button>
    </div>
    <section id="p-elo" role="tabpanel" aria-labelledby="tab-elo" class="tab-content">
      <div class="seg preview-mode" role="group" aria-label="Előnézet módja" id="liMode"><button type="button" data-m="closed" aria-pressed="true">Zárt nézet</button><button type="button" data-m="open" aria-pressed="false">Kibontott nézet</button></div>
      <article class="li-card" aria-label="LinkedIn-előnézet">
        <div class="li-head"><span class="li-logo" aria-hidden="true"><?= $e(mb_strtoupper(mb_substr($data['company']['name'], 0, 1))) ?></span><div><b><?= $e($data['company']['name']) ?></b><?php if ($data['company']['followers'] !== ''): ?><span><?= $e($data['company']['followers']) ?> követő</span><?php endif; ?><span>Most · <?= $ic('globe', 'icon li-globe') ?></span></div><span class="li-follow">+ Követés</span></div>
        <div class="li-text" id="liText"></div>
        <img id="postImg" alt="A poszt képe" width="1200" height="1200" hidden>
        <canvas id="postCanvas" width="1200" height="1200" role="img" aria-label="A poszt képe (sablon)"></canvas>
        <div class="li-react" aria-hidden="true"><span><?= $ic('thumb') ?>Tetszik</span><span><?= $ic('msg') ?>Hozzászólás</span><span><?= $ic('repeat') ?>Megosztás</span><span><?= $ic('send') ?>Küldés</span></div>
      </article>
    </section>
    <section id="p-adat" role="tabpanel" aria-labelledby="tab-adat" class="panel data-panel" hidden>
      <div class="panel-head"><div><span class="kicker">ADATOK</span><h2 id="h-src">A poszt adatlapja</h2></div></div>
      <dl id="postKv"></dl>
    </section>
    <section id="p-hist" role="tabpanel" aria-labelledby="tab-hist" class="panel timeline-panel" hidden>
      <div class="panel-head"><div><span class="kicker">ELŐZMÉNYEK</span><h2 id="h-hist">Ki, mit, mikor</h2></div></div>
      <ol class="timeline" id="postHist"></ol>
    </section>
    <section id="p-szerk" role="tabpanel" aria-labelledby="tab-szerk" hidden>
      <form class="panel editor-form" id="postForm" novalidate aria-labelledby="h-pe">
        <div class="panel-head"><div><span class="kicker">SZERKESZTÉS</span><h2 id="h-pe">A poszt szerkesztése</h2><p>Mezőnként; a kép szövegének javítása azonnal újrarajzolja a képet.</p></div></div>
        <div class="edit-grid">
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
          <div class="field"><label for="pe-it">Kép címe <span class="opt">(új sor = sortörés a képen)</span></label><textarea class="input" id="pe-it" rows="2" maxlength="200"></textarea></div>
          <div class="field"><label for="pe-is">Kép alcíme</label><input class="input" id="pe-is" maxlength="200"></div>
          <div class="field"><label for="pe-e1">Számpélda: mért <span class="opt">(nem kötelező)</span></label><input class="input" id="pe-e1" maxlength="200"></div>
          <div class="field"><label for="pe-e2">Számpélda: valós <span class="opt">(nem kötelező)</span></label><input class="input" id="pe-e2" maxlength="200"></div>
          <div class="field full"><label for="pe-note">Megjegyzés <span class="opt">(belső)</span></label><textarea class="input" id="pe-note" rows="2"></textarea></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit" id="peSave"><?= $ic('check') ?>Módosítások mentése</button><button class="btn btn-ghost" type="button" id="peReset">Elvetés</button><?= $tip('t-pe', 'a poszt részei', 'A teljes szöveg ezekből a részekből áll össze, ebben a sorrendben: nyitó sor, kontextus, kiemelések, szemszög, zárás, hashtagek. A forráslink a szabályok szerint nem kerül a szövegbe, az első kommentbe való.') ?></div>
      </form>
    </section>
  </div>
  <aside class="post-aside">
    <section class="panel publish-card" aria-labelledby="h-use">
      <div class="panel-head"><div><span class="kicker">KÖZZÉTÉTELHEZ</span><h2 id="h-use">Szöveg, kép és forráslink</h2></div></div>
      <div class="use-acts">
        <button class="btn btn-secondary" type="button" id="copyText"><?= $ic('copy') ?>Szöveg másolása</button>
        <button class="btn btn-secondary" type="button" id="dlImage"><?= $ic('download') ?>Kép letöltése (PNG)</button>
        <button class="btn btn-ghost" type="button" id="copyLink" hidden><?= $ic('copy') ?>Forráslink (első kommentbe)</button>
      </div>
    </section>
    <section class="panel opening-card" aria-labelledby="h-open">
      <div class="panel-head"><div><span class="kicker">NYITÓ SOR</span><h2 id="h-open">A „…több” előtt</h2></div></div>
      <fieldset class="open-choices" id="openChoices"><legend class="sr-only">Nyitó sor választása</legend></fieldset>
    </section>
    <section class="panel source-articles" aria-labelledby="h-arts" id="artsCard" hidden>
      <div class="panel-head"><div><span class="kicker">FORRÁSCIKKEK</span><h2 id="h-arts">Melyik cikkekből készüljön</h2></div></div>
      <fieldset class="arts-list" id="artsList"><legend class="sr-only">Forráscikkek kiválasztása</legend></fieldset>
      <p class="meta arts-hint" id="artsHint">Jelöljön ki más cikkeket; a gombok akkor aktívak, ha a kijelölés eltér a mostanitól.</p>
      <div class="source-actions" id="artsActs"><button class="btn btn-primary sm" type="button" id="artsRegen" aria-describedby="artsHint"><?= $ic('refresh') ?>Mentés és újraírás</button><button class="btn btn-ghost sm" type="button" id="artsSave" aria-describedby="artsHint">Csak mentés</button></div>
    </section>
  </aside>
</div>

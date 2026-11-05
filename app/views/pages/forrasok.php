<?php
/** @var \Xperion\Auth\Access $access */
$sub = 'A havi gyűjtés ezekből dolgozik: a figyelt oldalak (hírlista vagy RSS) és a kulcsszavak. A kizáró kulcsszóval jelölt témájú cikkek kimaradnak.';
require __DIR__ . '/_head.php';
?>
<div class="tabs admin-tabs" role="tablist" aria-label="Források és kulcsszavak">
  <button class="tab" role="tab" id="tab-src" aria-selected="true" aria-controls="p-src" tabindex="0"><?= $ic('globe') ?>Források <span class="count" id="srcCount">0</span></button>
  <button class="tab" role="tab" id="tab-kw" aria-selected="false" aria-controls="p-kw" tabindex="-1"><?= $ic('search') ?>Kulcsszavak <span class="count" id="kwCount">0</span></button>
  <button class="tab" role="tab" id="tab-sug" aria-selected="false" aria-controls="p-sug" tabindex="-1"><?= $ic('sparkle') ?>Javasolt források <span class="count" id="sugCount">0</span></button>
</div>
<div class="card table-card" id="p-src" role="tabpanel" aria-labelledby="tab-src">
  <div class="toolbar" role="search" aria-label="Források szűrése"><div class="search"><?= $ic('search', 'icon') ?><label class="sr-only" for="srcQ">Keresés a források között</label><input class="input" id="srcQ" type="search" placeholder="Név, cím, téma…" autocomplete="off"></div><span class="spacer"></span><button class="btn btn-primary sm" type="button" id="srcAdd"><?= $ic('plus') ?>Új forrás</button></div>
  <div class="table-wrap" id="srcTable"></div>
</div>
<div class="card table-card" id="p-sug" role="tabpanel" aria-labelledby="tab-sug" hidden>
  <div class="toolbar"><b>Új-forrás-figyelő</b><?= $tip('t-sug', 'új-forrás-figyelő', 'A Claude a kulcsszavak és a figyelt források témái alapján a weben olyan hírforrásokat keres, amelyek még nincsenek a listában. Mindegyiket próbagyűjtéssel ellenőrizzük; csak a működők kerülnek ide. <b>Felvétel:</b> aktív, „új forrás” jelöléssel figyelt forrás lesz. <b>Elvetés:</b> a figyelő többé nem javasolja. Keresésenként kb. 5–15 cent, 1–3 perc.') ?><span class="spacer"></span><button class="btn btn-primary sm" type="button" id="sugSearch"><?= $ic('search') ?>Új források keresése</button></div>
  <div class="table-wrap" id="sugTable"></div>
</div>
<div class="card table-card" id="p-kw" role="tabpanel" aria-labelledby="tab-kw" hidden>
  <div class="toolbar" role="search" aria-label="Kulcsszavak szűrése"><div class="search"><?= $ic('search', 'icon') ?><label class="sr-only" for="kwQ">Keresés a kulcsszavak között</label><input class="input" id="kwQ" type="search" placeholder="Kulcsszó, téma…" autocomplete="off"></div><span class="spacer"></span><button class="btn btn-primary sm" type="button" id="kwAdd"><?= $ic('plus') ?>Új kulcsszó</button></div>
  <div class="table-wrap" id="kwTable"></div>
</div>
<dialog id="srcDlg" aria-labelledby="srcDlgTitle">
  <form id="srcForm" novalidate>
    <div class="dlg-body">
      <h2 id="srcDlgTitle">Új forrás</h2>
      <div class="field"><label for="s-url">Cím (hírlista vagy RSS)</label><input class="input" id="s-url" type="url" required placeholder="https://…/news vagy https://…/rss.xml" autocomplete="off"></div>
      <div class="field"><label for="s-name">Név</label><input class="input" id="s-name" maxlength="190" placeholder="a webhely neve, ha üres" autocomplete="off"></div>
      <div class="field"><label for="s-type">Típus <span class="opt">(nem kötelező)</span></label><input class="input" id="s-type" maxlength="100" placeholder="pl. versenytárs, partner, szakmai média, RSS" autocomplete="off"></div>
      <div class="field"><label for="s-topics">Témák <span class="opt">(nem kötelező)</span></label><input class="input" id="s-topics" maxlength="500" autocomplete="off"></div>
      <label class="choice"><input type="checkbox" id="s-active" checked> Aktív (a havi gyűjtés figyeli)</label>
      <label class="choice"><input type="checkbox" id="s-new"> Új forrás (próbaidőszak: a Havi cikkekben külön jelölve)</label>
      <div class="field"><label for="s-note">Megjegyzés <span class="opt">(nem kötelező)</span></label><input class="input" id="s-note" maxlength="500" autocomplete="off"></div>
      <div class="acts-row"><button class="btn btn-ghost sm" type="button" id="srcTest"><?= $ic('flask') ?>Próbagyűjtés</button></div>
      <div id="srcTestOut" class="help" role="status"></div>
      <p class="error" id="srcErr" role="alert" hidden></p>
    </div>
    <div class="dlg-foot"><button class="btn btn-secondary" type="button" id="srcCancel">Mégse</button><button class="btn btn-primary" type="submit" id="srcSubmit">Mentés</button></div>
  </form>
</dialog>
<dialog id="kwDlg" aria-labelledby="kwDlgTitle">
  <form id="kwForm" novalidate>
    <div class="dlg-body">
      <h2 id="kwDlgTitle">Új kulcsszó</h2>
      <div class="field"><label for="k-word">Kulcsszó</label><input class="input" id="k-word" required maxlength="190" autocomplete="off"></div>
      <div class="field"><label for="k-topic">Téma <span class="opt">(nem kötelező)</span></label><input class="input" id="k-topic" maxlength="190" autocomplete="off"></div>
      <div class="field"><label for="k-lang">Nyelv <span class="opt">(nem kötelező)</span></label><select class="select" id="k-lang"><option value="">mindegy</option><option value="hu">magyar</option><option value="en">angol</option></select></div>
      <label class="choice"><input type="checkbox" id="k-exclude"> Kizáró kulcsszó (az ilyen témájú cikk kimarad)</label>
      <p class="error" id="kwErr" role="alert" hidden></p>
    </div>
    <div class="dlg-foot"><button class="btn btn-secondary" type="button" id="kwCancel">Mégse</button><button class="btn btn-primary" type="submit" id="kwSubmit">Mentés</button></div>
  </form>
</dialog>

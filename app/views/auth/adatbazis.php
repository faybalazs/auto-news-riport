<div class="auth-card">
  <div><h1 tabindex="-1">Adatbázis beállítása</h1><p class="page-sub">Az alkalmazás MySQL-adatbázist használ. A táblákat az első indításkor maga hozza létre.</p></div>
  <?php require __DIR__ . '/_error.php'; ?>
  <ol class="steps">
    <li>A tárhely vezérlőpultjában hozzon létre egy MySQL-adatbázist és hozzá egy felhasználót (utf8mb4 kódolás).</li>
    <li>A fájlkezelőben nyissa meg a <code>config.php</code>-t, és töltse ki a <code>db</code> részt: gépnév (általában localhost), adatbázisnév, felhasználó, jelszó.</li>
    <li>Mentse el, és töltse újra ezt az oldalt.</li>
  </ol>
</div>

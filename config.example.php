<?php
/**
 * Minta-konfiguráció. Másold le "config.php" néven (vagy a webgyökér fölé
 * "xperion-config.php" néven), és töltsd ki. A config.php soha nem kerül a repóba.
 */
return [
    // Beállítókulcs (legalább 16 karakter, véletlenszerű). Csak az első indításkor kell:
    // ezzel lehet létrehozni az első üzemeltetői fiókot.
    'admin_key' => '',

    // MySQL-adatbázis (a tárhely vezérlőpultjában hozd létre, utf8mb4 kódolással).
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => '',
        'user' => '',
        'pass' => '',
    ],

    // Kulcs a cron-hívásokhoz: cron.php?feladat=havi&k=<cron_key>
    'cron_key' => '',

    // Google szolgáltatásfiók az adatátvételhez. Legegyszerűbb: üresen hagyni, és az Ügyfelek oldal
    // „Adatátvétel” kártyáján feltölteni a letöltött JSON-fájlt (az elsőbbséget élvez).
    // Itt megadva: a fájl neve a config.php mellett (pl. 'google-kulcs.json'), VAGY a JSON teljes tartalma.
    'google_service_account_file' => '',
    'google_service_account_json' => '',

    // Claude API-kulcs (Claude Console → API Keys). A havi futás értékeléséhez és összefoglalójához kell.
    // Modellek ügyfelenként felülírhatók (client_settings: modell_ertekeles, modell_poszt);
    // alapértelmezés: értékelés claude-haiku-5-5, összefoglaló claude-sonnet-5-5.
    'claude_api_key' => '',

    // SMTP a havi e-mailhez és az értesítésekhez: a tárhelyen létrehozott postafiók adatai.
    // secure: ssl (port 465) vagy tls (port 587). A from_email általában maga a postafiók címe.
    'smtp' => [
        'host' => '',
        'port' => 465,
        'secure' => 'ssl',          // ssl | tls | none
        'user' => '',
        'pass' => '',
        'from_email' => '',
        'from_name' => 'Xperion sajtófigyelés',
    ],

    // Az alkalmazás webcíme a levelek linkjeihez (pl. https://pelda.hu/xperion/). Üresen hagyható:
    // böngészőből és URL-es cronból indított futásnál a program magától tudja.
    'app_url' => '',

    'timezone' => 'Europe/Budapest',

    // Futási adatok (napló, zárolás, token-gyorsítótár). Üresen: a "storage" mappa.
    'storage_dir' => '',
];

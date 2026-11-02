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

    // Google szolgáltatásfiók az adatátvételhez. A táblát ügyfelenként az Ügyfelek oldalon adod meg,
    // és megosztod ennek a fióknak az e-mail-címével. VAGY a letöltött JSON-fájl elérési útja,
    // VAGY a JSON teljes tartalma idézőjelek között (az egyik elég).
    'google_service_account_file' => '',
    'google_service_account_json' => '',

    // Claude API-kulcs (az 5. lépéstől kell: értékelés, összefoglaló, posztok).
    'claude_api_key' => '',

    // SMTP a havi e-mailhez, a meghívókhoz és az értesítésekhez (a 7. lépéstől kell; addig a meghívó link másolható).
    'smtp' => [
        'host' => '',
        'port' => 465,
        'secure' => 'ssl',          // ssl | tls | none
        'user' => '',
        'pass' => '',
        'from_email' => '',
        'from_name' => 'Xperion sajtófigyelés',
    ],

    'timezone' => 'Europe/Budapest',

    // Futási adatok (napló, zárolás, token-gyorsítótár). Üresen: a "storage" mappa.
    'storage_dir' => '',
];

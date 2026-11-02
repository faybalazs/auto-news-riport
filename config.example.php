<?php
/**
 * Minta-konfiguráció. Másold le "config.php" néven (vagy a webgyökér fölé
 * "xperion-config.php" néven), és töltsd ki. A config.php soha nem kerül a repóba.
 */
return [
    // Belépési kulcs az admin felülethez (legalább 16 karakter, véletlenszerű).
    'admin_key' => '',

    // Kulcs a cron-hívásokhoz: cron.php?feladat=havi&k=<cron_key>
    'cron_key' => '',

    // A Google Sheet azonosítója.
    'spreadsheet_id' => '1MuPaB3VG2LAkHntWJy7FmjTyzzDSx9thgeKjLkZSqPA',

    // Google szolgáltatásfiók: VAGY a letöltött JSON-fájl elérési útja,
    // VAGY a JSON teljes tartalma idézőjelek között (az egyik elég).
    'google_service_account_file' => '',
    'google_service_account_json' => '',

    // Claude API-kulcs (a 3. lépéstől kell).
    'claude_api_key' => '',

    // SMTP a havi e-mailhez és az értesítésekhez (a 4. lépéstől kell).
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

<?php
declare(strict_types=1);

/** Admin felület: belépés, Vezérlőpult, Napló. */

use Xperion\Admin\Auth;
use Xperion\Admin\Http;

/** @var \Xperion\App $app */
$app = require __DIR__ . '/app/bootstrap.php';
Http::securityHeaders();

$auth = new Auth($app->config->string('admin_key'), $app->storageDir . '/belepes-hibak.json');
$auth->start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['muvelet'] ?? '');
    if ($action === 'belepes') {
        $error = $auth->login((string) ($_POST['kulcs'] ?? ''), Http::clientIp());
        if ($error === null) {
            header('Location: index.php#vezerlo', true, 303);
            exit;
        }
        $_SESSION['login_error'] = $error;
        header('Location: index.php', true, 303);
        exit;
    }
    if ($action === 'kilepes' && $auth->check() && $auth->validCsrf((string) ($_POST['csrf'] ?? ''))) {
        $auth->logout();
    }
    header('Location: index.php', true, 303);
    exit;
}

$version = XPERION_VERSION;

if (!$auth->check()) {
    $error = (string) ($_SESSION['login_error'] ?? '');
    unset($_SESSION['login_error']);
    $configured = $auth->configured();
    require __DIR__ . '/app/views/login.php';
    exit;
}

$descriptions = [
    'kapcsolat' => 'Ellenőrzi, hogy a szolgáltatásfiók eléri-e a táblát, és megvannak-e a szükséges munkalapok. Csak olvas.',
    'elokeszites' => 'Létrehozza a Beállítások, Napló és Posztötletek lapot, és pótolja a hiányzó beállításokat. Meglévő adatot nem ír felül.',
    'havi' => 'Az előző hónap cikkeinek feldolgozása (minden hónap 1-jén). Most még csak az előkészítés fut: célhónap, források, kulcsszavak.',
    'orankenti' => 'Döntések figyelése, feltöltés, posztok, jóváhagyott e-mail (óránként). Most még csak a Beállításokat olvassa.',
];
$log = $app->log();
$tasks = [];
foreach ($app->runner()->tasks() as $task) {
    $tasks[] = [
        'id' => $task->id(),
        'label' => $task->label(),
        'desc' => $descriptions[$task->id()] ?? '',
        'readOnly' => $task->readOnly(),
        'last' => $log->last($task->id()),
    ];
}

$cronKey = $app->config->string('cron_key');
$base = Http::baseUrl() . 'cron.php?k=' . rawurlencode($cronKey !== '' ? $cronKey : '<cron_key>');
$cronUrls = [
    'Havi futás – minden hónap 1-jén 06:00' => $base . '&feladat=havi',
    'Óránkénti futás – minden órában' => $base . '&feladat=orankenti',
];
$csrf = $auth->csrf();
$checklist = $app->config->checklist();
$sheetUrl = 'https://docs.google.com/spreadsheets/d/' . rawurlencode($app->config->string('spreadsheet_id')) . '/edit';

require __DIR__ . '/app/views/admin.php';

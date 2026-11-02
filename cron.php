<?php
declare(strict_types=1);

/**
 * Időzített futás.
 *   Böngészőből / cron URL-ként:  cron.php?feladat=havi&k=<cron_key>[&dry=1]
 *   Parancssorból:                php cron.php havi [--dry]
 * Feladatok: havi, orankenti.
 */

use Xperion\Web\Http;

/** @var \Xperion\App $app */
$app = require __DIR__ . '/app/bootstrap.php';

const CRON_TASKS = ['havi', 'orankenti'];

if (PHP_SAPI === 'cli') {
    $task = (string) ($argv[1] ?? '');
    $dry = in_array('--dry', $argv, true);
    $trigger = 'cli';
} else {
    Http::securityHeaders();
    header('Content-Type: text/plain; charset=utf-8');
    $cronKey = $app->config->string('cron_key');
    if (strlen($cronKey) < 16 || !hash_equals($cronKey, (string) ($_GET['k'] ?? ''))) {
        http_response_code(403);
        exit("Hozzáférés megtagadva.\n");
    }
    $task = (string) ($_GET['feladat'] ?? '');
    $dry = ($_GET['dry'] ?? '') === '1';
    $trigger = 'cron';
}

if (!in_array($task, CRON_TASKS, true)) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(400);
    }
    echo 'Ismeretlen feladat. Lehetséges: ' . implode(', ', CRON_TASKS) . "\n";
    exit(2);
}

ignore_user_abort(true);
@set_time_limit(600);
$result = $app->runner()->run($task, $dry, $trigger);
if (PHP_SAPI !== 'cli' && !$result->ok) {
    http_response_code(500); // a cron-szolgáltatás így hibának látja
}
echo $result->toText();
exit($result->ok ? 0 : 1);

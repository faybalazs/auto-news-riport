<?php
declare(strict_types=1);

/**
 * Az admin felület JSON-végpontjai (belépett munkamenettel).
 *   GET  api.php?muvelet=naplo
 *   POST api.php?muvelet=futtatas  {"feladat":"havi","dry":true}  (X-CSRF-Token fejléc)
 */

use Xperion\Admin\Auth;
use Xperion\Admin\Http;

/** @var \Xperion\App $app */
$app = require __DIR__ . '/app/bootstrap.php';
Http::securityHeaders();

$auth = new Auth($app->config->string('admin_key'), $app->storageDir . '/belepes-hibak.json');
if (!$auth->check()) {
    Http::json(['error' => 'Nincs belépve.'], 401);
}

$action = (string) ($_GET['muvelet'] ?? '');

if ($action === 'naplo' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $entries = array_map(static fn ($e) => $e->toArray() + ['time_hu' => $e->timeHu()], $app->log()->recent(500));
    Http::json(['entries' => $entries]);
}

if ($action === 'futtatas' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validCsrf((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        Http::json(['error' => 'Érvénytelen biztonsági token. Töltse újra az oldalt.'], 403);
    }
    session_write_close(); // a hosszú futás ne zárolja a munkamenetet
    $body = json_decode((string) file_get_contents('php://input'), true);
    $task = is_array($body) ? (string) ($body['feladat'] ?? '') : '';
    $dry = is_array($body) && !empty($body['dry']);
    if (!$app->runner()->has($task)) {
        Http::json(['error' => 'Ismeretlen feladat.'], 400);
    }
    ignore_user_abort(true);
    @set_time_limit(600);
    Http::json($app->runner()->run($task, $dry, 'admin')->toArray() + ['time_hu' => date('Y. m. d. H:i')]);
}

Http::json(['error' => 'Ismeretlen művelet.'], 404);

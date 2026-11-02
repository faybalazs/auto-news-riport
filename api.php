<?php
declare(strict_types=1);

/**
 * JSON-végpontok a belépett felhasználónak: api.php?m=<művelet>, POST, X-CSRF-Token fejléccel.
 * Minden művelet a szerveren ellenőrzi a jogosultságot és az ügyfélhatárt.
 */

use Xperion\Auth\Access;
use Xperion\Auth\AccessDenied;
use Xperion\Auth\TokenService;
use Xperion\Web\Http;
use Xperion\Web\Session;

/** @var \Xperion\App $app */
$app = require __DIR__ . '/app/bootstrap.php';
Http::securityHeaders();
if (!$app->dbConfigured()) {
    Http::json(['error' => 'Nincs beállítva az adatbázis.'], 503);
}
Session::start();
$access = Session::userId() !== null ? Access::forUser($app->db(), Session::userId(), Session::clientId()) : null;
if ($access === null) {
    Http::json(['error' => 'A munkamenet lejárt. Lépjen be újra.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    Http::json(['error' => 'Érvénytelen kérés. Töltse újra az oldalt.'], 403);
}
$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
$str = static fn (string $k): string => trim((string) ($in[$k] ?? ''));
$int = static fn (string $k): ?int => isset($in[$k]) && $in[$k] !== '' && $in[$k] !== null ? (int) $in[$k] : null;
$link = static fn (array $r): string => Http::baseUrl() . 'index.php?p=' . ($r['purpose'] === TokenService::INVITE ? 'meghivo' : 'jelszo') . '&t=' . $r['token'];
$users = $app->users();

try {
    $m = (string) ($_GET['m'] ?? '');
    $out = match ($m) {
        'felhasznalok' => ['users' => $users->visibleUsers($access)],
        'meghivas' => (function () use ($users, $access, $str, $int, $link) {
            $r = $users->invite($access, $str('name'), $str('email'), $int('client_id'), $str('role'));
            return ['ok' => true, 'link' => $r['token'] ? $link(['purpose' => TokenService::INVITE, 'token' => $r['token']]) : null, 'users' => $users->visibleUsers($access)];
        })(),
        'felhasznalo' => (function () use ($users, $access, $str, $int) {
            $users->update($access, (int) $int('id'), $str('name'), $int('client_id'), $str('role'));
            return ['ok' => true, 'users' => $users->visibleUsers($access)];
        })(),
        'letiltas' => (function () use ($users, $access, $in) {
            foreach ((array) ($in['ids'] ?? []) as $id) {
                $users->setDisabled($access, (int) $id, (bool) ($in['disabled'] ?? true));
            }
            return ['ok' => true, 'users' => $users->visibleUsers($access)];
        })(),
        'torles' => (function () use ($users, $access, $in) {
            $res = [];
            foreach ((array) ($in['ids'] ?? []) as $id) {
                $res[] = $users->remove($access, (int) $id);
            }
            return ['ok' => true, 'results' => $res, 'users' => $users->visibleUsers($access)];
        })(),
        'link' => (function () use ($users, $access, $int, $link) {
            $r = $users->issueLink($access, (int) $int('id'));
            return ['ok' => true, 'purpose' => $r['purpose'], 'link' => $link($r)];
        })(),
        'ugyfel' => (function () use ($app, $access, $str, $int) {
            $id = $int('id');
            $id === null ? $app->clients()->create($access, $str('name'), $str('website')) : $app->clients()->update($access, $id, $str('name'), $str('website'), $str('status'));
            return ['ok' => true, 'clients' => $app->clients()->all($access)];
        })(),
        'jelszocsere' => (function () use ($users, $access, $in) {
            $new = (string) ($in['new'] ?? '');
            if ($new !== (string) ($in['new2'] ?? '')) {
                throw new \InvalidArgumentException('A két új jelszó nem egyezik.');
            }
            $users->changeOwnPassword($access, (string) ($in['current'] ?? ''), $new);
            return ['ok' => true];
        })(),
        'futtatas' => (function () use ($app, $access, $str, $in) {
            $access->require('ops');
            session_write_close();
            if (!$app->runner()->has($str('task'))) {
                throw new \InvalidArgumentException('Ismeretlen feladat.');
            }
            ignore_user_abort(true);
            @set_time_limit(600);
            return $app->runner()->run($str('task'), !empty($in['dry']), 'admin')->toArray() + ['time_hu' => date('Y. m. d. H:i')];
        })(),
        default => throw new \InvalidArgumentException('Ismeretlen művelet.'),
    };
    Http::json($out);
} catch (AccessDenied $e) {
    Http::json(['error' => $e->getMessage()], 403);
} catch (\InvalidArgumentException $e) {
    Http::json(['error' => $e->getMessage()], 422);
} catch (\Throwable $e) {
    error_log('Xperion API: ' . $e->getMessage());
    Http::json(['error' => 'Váratlan hiba történt. A részletek a szerver naplójában vannak.'], 500);
}

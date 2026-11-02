<?php
declare(strict_types=1);

/**
 * Az alkalmazás belépési pontja (oldalak). Útvonal: index.php?p=<oldal>.
 * Belépés nélkül: belepes, beallitas (első indítás), meghivo, jelszo.
 */

use Xperion\Auth\Access;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\Roles;
use Xperion\Auth\TokenService;
use Xperion\Web\Http;
use Xperion\Web\Session;
use Xperion\Web\View;

/** @var \Xperion\App $app */
$app = require __DIR__ . '/app/bootstrap.php';
Http::securityHeaders();
$view = new View(__DIR__ . '/app/views', XPERION_VERSION);
$page = preg_replace('/[^a-z]/', '', (string) ($_GET['p'] ?? '')) ?: 'attekintes';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';

// Adatbázis nélkül csak a beállítási útmutató jelenik meg.
if (!$app->dbConfigured()) {
    $view->render('auth/adatbazis', ['title' => 'Adatbázis beállítása', 'error' => null], 'auth/layout');
}
try {
    $app->migrator()->migrate();
} catch (\Throwable $e) {
    error_log('Xperion adatbázis: ' . $e->getMessage());
    $view->render('auth/adatbazis', ['title' => 'Adatbázis beállítása', 'error' => 'Az adatbázis nem érhető el. Ellenőrizze a config.php „db” részét (gépnév, adatbázisnév, felhasználó, jelszó).'], 'auth/layout');
}
Session::start();
$auth = $app->authenticator();

// ---- Első indítás: első üzemeltető létrehozása a beállítókulccsal -------------
if ($auth->setupNeeded()) {
    $error = null;
    if ($post) {
        $key = $app->config->string('admin_key');
        if (!Session::validCsrf($_POST['csrf'] ?? null)) {
            $error = 'Lejárt az űrlap. Töltse újra az oldalt.';
        } elseif (strlen($key) < 16 || !hash_equals($key, (string) ($_POST['kulcs'] ?? ''))) {
            usleep(400_000);
            $error = strlen($key) < 16 ? 'A beállítókulcs nincs megadva a config.php-ban (admin_key, legalább 16 karakter).' : 'Hibás beállítókulcs.';
        } elseif (($_POST['jelszo'] ?? '') !== ($_POST['jelszo2'] ?? '')) {
            $error = 'A két jelszó nem egyezik.';
        } else {
            try {
                $uid = $app->users()->createFirstOperator((string) ($_POST['nev'] ?? ''), (string) ($_POST['email'] ?? ''), (string) ($_POST['jelszo'] ?? ''));
                Session::login($uid);
                Session::flash('success', 'A rendszer beállítva. Következő lépés: hozza létre az első ügyfelet.');
                Http::redirect('index.php?p=ugyfelek');
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
    }
    $view->render('auth/beallitas', ['title' => 'Első beállítás', 'error' => $error, 'csrf' => Session::csrf(), 'old' => $_POST], 'auth/layout');
}

// ---- Meghívó elfogadása / jelszó-visszaállítás ----------------------------------
if ($page === 'meghivo' || $page === 'jelszo') {
    $purpose = $page === 'meghivo' ? TokenService::INVITE : TokenService::RESET;
    $token = (string) ($_GET['t'] ?? '');
    $who = $app->tokens()->peek($token, $purpose);
    $error = null;
    if ($who !== null && $post) {
        if (!Session::validCsrf($_POST['csrf'] ?? null)) {
            $error = 'Lejárt az űrlap. Töltse újra az oldalt.';
        } elseif (($_POST['jelszo'] ?? '') !== ($_POST['jelszo2'] ?? '')) {
            $error = 'A két jelszó nem egyezik.';
        } else {
            try {
                $uid = $app->users()->setPasswordWithToken($token, $purpose, (string) $_POST['jelszo']);
                if ($uid !== null) {
                    Session::login($uid);
                    Session::flash('success', $purpose === TokenService::INVITE ? 'Üdvözöljük! A fiókja aktív.' : 'Az új jelszó beállítva.');
                    Http::redirect('index.php');
                }
                $who = null;
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
    }
    $view->render('auth/jelszo', ['title' => $purpose === TokenService::INVITE ? 'Meghívó elfogadása' : 'Új jelszó', 'who' => $who, 'invite' => $purpose === TokenService::INVITE, 'error' => $error, 'csrf' => Session::csrf()], 'auth/layout');
}

// ---- Belépés / kilépés ---------------------------------------------------------------
if ($page === 'kilepes' && $post) {
    if (Session::validCsrf($_POST['csrf'] ?? null)) {
        Session::logout();
    }
    Http::redirect('index.php?p=belepes');
}
$access = Session::userId() !== null ? Access::forUser($app->db(), Session::userId(), Session::clientId()) : null;
if ($access === null) {
    if (Session::userId() !== null) {
        Session::logout(); // letiltott vagy törölt fiók
    }
    $error = null;
    if ($page === 'belepes' && $post) {
        if (!Session::validCsrf($_POST['csrf'] ?? null)) {
            $error = 'Lejárt az űrlap. Töltse újra az oldalt.';
        } else {
            $r = $auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['jelszo'] ?? ''), Http::clientIp());
            if ($r['user'] !== null) {
                Session::login((int) $r['user']['id']);
                Http::redirect('index.php');
            }
            $error = $r['error'];
            usleep(300_000);
        }
    }
    $view->render('auth/belepes', ['title' => 'Belépés', 'error' => $error, 'csrf' => Session::csrf(), 'email' => (string) ($_POST['email'] ?? '')], 'auth/layout');
}
if ($page === 'belepes') {
    Http::redirect('index.php');
}
Session::setClientId($access->clientId());

// ---- Ügyfélváltás --------------------------------------------------------------------
if ($page === 'ugyfelvaltas' && $post) {
    $cid = (int) ($_POST['client_id'] ?? 0);
    if (Session::validCsrf($_POST['csrf'] ?? null) && $access->canAccessClient($cid)) {
        Session::setClientId($cid);
    }
    $back = preg_replace('/[^a-z]/', '', (string) ($_POST['vissza'] ?? '')) ?: 'attekintes';
    Http::redirect('index.php?p=' . $back);
}

// ---- Oldalak -------------------------------------------------------------------------
$pages = [
    'attekintes' => ['Áttekintés', null],
    'cikkek' => ['Havi cikkek', null],
    'posztok' => ['LinkedIn-posztok', null],
    'otletek' => ['Posztötletek', 'ideas'],
    'szabalyok' => ['Posztszabályok', 'rules'],
    'felhasznalok' => ['Felhasználók', 'users'],
    'ugyfelek' => ['Ügyfelek', 'ops'],
    'vezerlo' => ['Vezérlőpult', 'ops'],
    'naplo' => ['Napló', 'ops'],
    'profil' => ['Profil', null],
];
if (!isset($pages[$page])) {
    $page = 'attekintes';
}
[$title, $perm] = $pages[$page];
$data = [];
if ($perm !== null && !$access->can($perm)) {
    http_response_code(403);
    $template = 'pages/nincsjog';
} else {
    $template = match ($page) {
        'cikkek', 'posztok', 'otletek', 'szabalyok' => 'pages/hamarosan',
        default => 'pages/' . $page,
    };
    if ($page === 'felhasznalok') {
        $data = ['users' => $app->users()->visibleUsers($access)];
    } elseif ($page === 'ugyfelek') {
        $data = ['clients' => $app->clients()->all($access), 'serviceEmail' => $app->serviceAccountEmail(), 'googleReady' => $app->config->serviceAccountJson() !== ''];
    } elseif ($page === 'naplo') {
        $data = ['runs' => array_map(static fn ($e) => $e->toArray() + ['time_hu' => $e->timeHu()], $app->log()->recent(300)), 'audit' => $app->audit()->recent(null)];
    } elseif ($page === 'attekintes') {
        $cid = $access->clientId();
        $data = ['memberCount' => $cid ? (int) $app->db()->value('SELECT COUNT(*) FROM memberships WHERE client_id = ?', [$cid]) : 0];
    }
}

$view->render($template, [
    'title' => $title,
    'page' => $page,
    'jsPage' => in_array($template, ['pages/nincsjog', 'pages/hamarosan'], true) ? 'statikus' : $page,
    'access' => $access,
    'csrf' => Session::csrf(),
    'flash' => Session::takeFlash(),
    'data' => $data,
    'app' => $app,
    'roles' => Roles::class,
    'clientStatus' => ClientAdmin::STATUS_LABELS,
]);

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
                $app->audit()->add(null, (int) $r['user']['id'], 'Belépés');
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
    'poszt' => ['LinkedIn-poszt', null],
    'otletek' => ['Posztötletek', 'ideas'],
    'otlet' => ['Posztötlet', 'ideas'],
    'szabalyok' => ['Posztszabályok', 'rules'],
    'felhasznalok' => ['Felhasználók', 'users'],
    'ugyfelek' => ['Ügyfelek', 'ops'],
    'vezerlo' => ['Vezérlőpult', 'ops'],
    'naplo' => ['Napló', 'log'],
    'forrasok' => ['Források és kulcsszavak', 'sources'],
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
    $template = 'pages/' . $page;
    if ($page === 'felhasznalok') {
        $data = ['users' => $app->users()->visibleUsers($access)];
    } elseif ($page === 'ugyfelek') {
        $data = ['clients' => $app->clients()->all($access), 'serviceEmail' => $app->serviceAccountEmail(), 'googleReady' => $app->config->serviceAccountJson() !== '', 'googleSource' => $app->config->serviceAccountSource()];
        if (($cl = $access->client()) !== null) {
            $data['onb'] = $app->clients()->onboarding((int) $cl['id'], $app->config->wordpressSite((string) $cl['website']));
            $data['settings'] = $app->clients()->settings((int) $cl['id']);
        }
    } elseif ($page === 'naplo') {
        $ops = $access->can('ops');
        $data = ['runs' => $ops ? array_map(static fn ($e) => $e->toArray() + ['time_hu' => $e->timeHu()], $app->log()->recent(1000)) : [],
            'audit' => $ops ? $app->audit()->recent(null, 2000) : ($access->clientId() !== null ? $app->audit()->recent($access->clientId(), 2000) : []), 'all' => $ops];
    } elseif ($page === 'cikkek') {
        $months = $app->articles()->months($access);
        $month = preg_match('/^\d{4}$/', (string) ($_GET['honap'] ?? '')) ? (string) $_GET['honap'] : ($months[0]['month'] ?? '');
        $data = ['months' => $months, 'month' => $month, 'articles' => $month !== '' ? $app->articles()->list($access, $month) : [], 'report' => $month !== '' ? $app->articles()->report($access, $month) : null];
    } elseif ($page === 'vezerlo' && $access->client() !== null) {
        $em = $app->emails();
        $mm = $em->months($access);
        $data = ['mail' => ['months' => array_map(static fn ($m) => ['month' => $m, 'label' => \Xperion\Monthly\EmailService::monthLabel($m)], $mm), 'email' => $mm ? $em->get($access, $mm[0]) : null,
            'settings' => $em->settings((int) $access->clientId()), 'ready' => $em->mailerReady(), 'problem' => $em->mailerProblem(), 'claude' => $app->config->has('claude_api_key')]];
    } elseif ($page === 'forrasok') {
        $data = $app->sources()->all($access);
    } elseif ($page === 'posztok') {
        $data = ['posts' => $app->posts()->all($access), 'openMonths' => $app->posts()->openMonths($access), 'jobs' => $app->posts()->pendingJobs($access)];
    } elseif ($page === 'poszt') {
        $post = $app->posts()->get($access, (string) ($_GET['kod'] ?? ''));
        if ($post === null) {
            http_response_code(404);
            $template = 'pages/nincs';
            $title = 'Nincs ilyen poszt';
        } else {
            $title = $post['code'];
            $cl = $access->client();
            $data = ['post' => $post, 'company' => ['name' => (string) ($app->db()->value("SELECT value FROM client_settings WHERE client_id = ? AND name = 'linkedin_nev'", [$cl['id']]) ?: $cl['name']),
                'followers' => (string) ($app->db()->value("SELECT value FROM client_settings WHERE client_id = ? AND name = 'linkedin_kovetok'", [$cl['id']]) ?: ''), 'website' => (string) $cl['website']]];
        }
    } elseif ($page === 'otletek') {
        $data = ['ideas' => $app->ideas()->all($access)];
    } elseif ($page === 'otlet') {
        $kod = (string) ($_GET['kod'] ?? '');
        $idea = $kod !== '' ? $app->ideas()->get($access, $kod) : null;
        if ($kod !== '' && $idea === null) {
            http_response_code(404);
            $template = 'pages/nincs';
            $title = 'Nincs ilyen ötlet';
        } else {
            $title = $idea ? $idea['code'] : 'Új posztötlet';
            $data = ['idea' => $idea, 'types' => \Xperion\Posts\IdeaService::TYPES, 'statuses' => \Xperion\Posts\IdeaService::STATUS];
        }
    } elseif ($page === 'szabalyok') {
        $data = ['rules' => $app->rules()->all($access)];
    } elseif ($page === 'attekintes') {
        $data = $app->overview()->build($access, preg_match('/^\d{4}$/', (string) ($_GET['honap'] ?? '')) ? (string) $_GET['honap'] : '');
    }
}

$view->render($template, [
    'title' => $title,
    'page' => $page,
    'jsPage' => in_array($template, ['pages/nincsjog', 'pages/nincs', 'pages/hamarosan'], true) ? 'statikus' : $page,
    'access' => $access,
    'csrf' => Session::csrf(),
    'flash' => Session::takeFlash(),
    'data' => $data,
    'app' => $app,
    'roles' => Roles::class,
    'clientStatus' => ClientAdmin::STATUS_LABELS,
]);

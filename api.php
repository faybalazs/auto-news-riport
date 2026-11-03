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
// A válasz mindig JSON legyen: a PHP-figyelmeztetések nem kerülnek a kimenetre, végzetes hibánál (pl. időtúllépés,
// hiányzó vagy régi fájl) is érthető üzenet megy vissza, nem üres 500-as oldal.
@ini_set('display_errors', '0');
ob_start();
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $msg = (string) $err['message'];
    $why = str_contains($msg, 'Maximum execution time')
        ? 'A művelet túllépte a tárhely futási időkorlátját. Próbálja újra; ha ismét előfordul, a weboldal lassan válaszol.'
        : 'A szerver hibával leállt: ' . mb_substr($msg, 0, 300) . ' (' . basename((string) $err['file']) . ', ' . $err['line'] . '. sor). Ha a hiba egy fájlnevet említ, valószínűleg az a fájl hiányzik vagy régi a tárhelyen: töltse fel újra a teljes kiadást.';
    error_log('Xperion API végzetes hiba: ' . $msg . ' @ ' . $err['file'] . ':' . $err['line']);
    echo json_encode(['error' => $why], JSON_UNESCAPED_UNICODE);
});
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
            $id === null ? $app->clients()->create($access, $str('name'), $str('website'), $str('sheet')) : $app->clients()->update($access, $id, $str('name'), $str('website'), $str('status'), $str('sheet'));
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
            $app->runner()->userId = $access->userId();
            return $app->runner()->run($str('task'), !empty($in['dry']), 'admin')->toArray() + ['time_hu' => date('Y. m. d. H:i')];
        })(),
        'atvetel' => (function () use ($app, $access, $in) {
            $access->require('ops');
            session_write_close();
            ignore_user_abort(true);
            @set_time_limit(600);
            return $app->importer()->run($access, !empty($in['dry']))->toArray() + ['time_hu' => date('Y. m. d. H:i'), 'clients' => $app->clients()->all($access)];
        })(),
        'gyujtes' => (function () use ($app, $access, $str) {
            $access->require('ops');
            session_write_close();
            ignore_user_abort(true);
            @set_time_limit(900);
            return $app->collector()->run($access, $str('month'))->toArray() + ['time_hu' => date('Y. m. d. H:i')];
        })(),
        'havifutas' => (function () use ($app, $access, $str, $in) {
            $access->require('ops');
            session_write_close();
            ignore_user_abort(true);
            @set_time_limit(1200);
            return $app->monthly()->runForAccess($access, $str('month'), !empty($in['dry']))->toArray() + ['time_hu' => date('Y. m. d. H:i')];
        })(),
        'dontes' => (function () use ($app, $access, $in) {
            $n = $app->articles()->decide($access, array_values(array_filter((array) ($in['changes'] ?? []), 'is_array')));
            return ['ok' => true, 'changed' => $n];
        })(),
        'googlekulcs' => (function () use ($app, $access, $in) {
            $email = $app->saveServiceAccountKey($access, (string) ($in['json'] ?? ''));
            return ['ok' => true, 'email' => $email];
        })(),
        'sheetlink' => (function () use ($app, $access, $str) {
            $access->require('ops');
            $c = $access->client();
            if ($c === null) {
                throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
            }
            $app->clients()->update($access, (int) $c['id'], (string) $c['name'], (string) $c['website'], (string) $c['status'], $str('sheet'));
            return ['ok' => true, 'clients' => $app->clients()->all($access)];
        })(),
        'forras' => ['data' => $app->sources()->saveSource($access, $int('id'), (array) ($in['source'] ?? []))],
        'forrastorles' => ['data' => $app->sources()->deleteSource($access, (int) $int('id'))],
        'forrasproba' => (function () use ($app, $access, $str) {
            session_write_close();
            @set_time_limit(120);
            return ['test' => $app->sources()->test($access, $str('url'), $str('type'))];
        })(),
        'kulcsszo' => ['data' => $app->sources()->saveKeyword($access, $int('id'), (array) ($in['keyword'] ?? []))],
        'kulcsszotorles' => ['data' => $app->sources()->deleteKeyword($access, (int) $int('id'))],
        'ugyfelbeallitas' => ['settings' => $app->clients()->saveSettings($access, (array) ($in['settings'] ?? []))],
        'szabaly' => ['rules' => $app->rules()->save($access, $int('id'), $str('name'), (string) ($in['value'] ?? ''))],
        'szabalytorles' => ['rules' => $app->rules()->delete($access, (int) $int('id'))],
        'szabalysorrend' => ['rules' => $app->rules()->reorder($access, array_map('intval', (array) ($in['ids'] ?? [])))],
        'otlet' => ['idea' => $app->ideas()->save($access, $str('code'), (array) ($in['idea'] ?? []))],
        'otlettorles' => (function () use ($app, $access, $str) {
            $app->ideas()->delete($access, $str('code'));
            return ['ok' => true];
        })(),
        'posztkeres' => (function () use ($app, $access, $str) {
            session_write_close();
            ignore_user_abort(true);
            @set_time_limit(600);
            try {
                return ['code' => $app->posts()->request($access, $str('kind'), $str('ref'))];
            } catch (\RuntimeException $e) {
                if ($e instanceof AccessDenied) {
                    throw $e;
                }
                throw new \InvalidArgumentException($e->getMessage());
            }
        })(),
        'posztcikkek' => (function () use ($app, $access, $str, $in) {
            session_write_close();
            @set_time_limit(600);
            try {
                return ['post' => $app->posts()->setArticles($access, $str('code'), array_map('intval', (array) ($in['ids'] ?? [])), !empty($in['regen']))];
            } catch (\RuntimeException $e) {
                if ($e instanceof AccessDenied) {
                    throw $e;
                }
                throw new \InvalidArgumentException($e->getMessage());
            }
        })(),
        'posztkep' => (function () use ($app, $access, $str, $in) {
            session_write_close();
            @set_time_limit(600);
            return ['post' => $app->posts()->newImage($access, $str('code'), (string) ($in['request'] ?? ''))];
        })(),
        'poszt' => ['post' => $app->posts()->update($access, $str('code'), (array) ($in['post'] ?? []))],
        'posztlepes' => ['post' => $app->posts()->transition($access, $str('code'), $str('action'), (string) ($in['comment'] ?? ''))],
        'poszttorles' => (function () use ($app, $access, $str) {
            $app->posts()->delete($access, $str('code'));
            return ['ok' => true];
        })(),
        'wpellenorzes' => (function () use ($app, $access) {
            session_write_close();
            @set_time_limit(180);
            return ['wp' => $app->uploads()->check($access)];
        })(),
        'feltoltes' => (function () use ($app, $access, $in) {
            $access->require('upload');
            session_write_close();
            ignore_user_abort(true);
            @set_time_limit(1200);
            return ['result' => $app->uploads()->runForAccess($access, !empty($in['dry']))->toArray()];
        })(),
        'feltoltesvissza' => $app->uploads()->revert($access, (int) $int('id'), !empty($in['force'])),
        'cikktorles' => (function () use ($app, $access, $int) {
            $app->articles()->remove($access, (int) $int('id'));
            return ['ok' => true];
        })(),
        'cikkvissza' => ['article' => $app->articles()->restore($access, (int) $int('id'))],
        'feltoltesujra' => (function () use ($app, $access, $int) {
            $app->uploads()->retry($access, (int) $int('id'));
            return ['ok' => true];
        })(),
        'email' => (function () use ($app, $access, $str) {
            $access->require('ops');
            $em = $app->emails();
            $month = $str('month');
            return ['email' => $month !== '' ? $em->get($access, $month) : null, 'months' => $em->months($access)];
        })(),
        'emailgen' => (function () use ($app, $access, $str, $in) {
            $em = $app->emails();
            $gen = $em->generate($access, $str('month'), !empty($in['dry']));
            return ['email' => $gen, 'row' => $em->get($access, $str('month'))];
        })(),
        'emailmentes' => ['email' => $app->emails()->update($access, $str('month'), $str('subject'), (string) ($in['body'] ?? ''))],
        'futasallapot' => (function () use ($app, $access) {
            $access->require('ops');
            return $app->runState();
        })(),
        'smtpproba' => (function () use ($app, $access) {
            $access->require('ops');
            session_write_close();
            @set_time_limit(180);
            return ['rows' => \Xperion\Mail\SmtpMailer::probe($app->config->smtp()), 'duplicate' => in_array('smtp', $app->config->duplicateKeys(), true)];
        })(),
        'emailteszt' => ['to' => $app->emails()->sendTest($access, $str('month'))],
        'emailkuldes' => ['email' => $app->emails()->approveAndSend($access, $str('month'))],
        'emailbeallitas' => ['settings' => $app->emails()->saveSettings($access, $str('to'), $str('cc'), (string) ($in['style'] ?? ''))],
        default => throw new \InvalidArgumentException('Ismeretlen művelet.'),
    };
    Http::json($out);
} catch (AccessDenied $e) {
    Http::json(['error' => $e->getMessage()], 403);
} catch (\InvalidArgumentException | \Xperion\Mail\MailException | \Xperion\Claude\ClaudeException | \Xperion\Press\WpException $e) {
    Http::json(['error' => $e->getMessage()], 422);
} catch (\Throwable $e) {
    error_log('Xperion API: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    // az üzemeltető lássa az okot (a tárhely naplójához nehéz hozzáférni), más csak általános üzenetet kap
    $detail = $access->isOperator() ? ' Részletek: ' . mb_substr($e->getMessage(), 0, 300) . ' (' . basename($e->getFile()) . ', ' . $e->getLine() . '. sor). Ha egy fájl vagy osztály hiányzik, töltse fel újra a teljes kiadást.' : ' A részletek a szerver naplójában vannak.';
    Http::json(['error' => 'Váratlan hiba történt.' . $detail], 500);
}

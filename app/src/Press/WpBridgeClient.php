<?php
declare(strict_types=1);

namespace Xperion\Press;

use Xperion\Http\HttpClient;
use Xperion\Http\HttpException;

/**
 * Az Xperion OAuth Bridge sajtószemle-végpontja (POST /wp-json/xperion-bridge/v1/press, JSON-RPC 2.0, tools/call),
 * WordPress-alkalmazásjelszóval. A HTTP-kliens nem ismételhet (vak ismétlés tilos).
 */
final class WpBridgeClient
{
    public function __construct(private HttpClient $http, private string $baseUrl, private string $user, private string $appPassword)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /** @return array<string,mixed> */
    public function context(): array
    {
        return $this->call('xperion_press_context', []);
    }

    /** @return array<string,mixed> {key, state, posts} */
    public function getPair(string $key): array
    {
        return $this->call('xperion_press_get_pair', ['key' => $key]);
    }

    /**
     * @param array<string,mixed> $args key, source_url, date, hu, en (+ featured_image_url)
     * @return array<string,mixed> {key, state, changed, posts}
     */
    public function deployPair(array $args): array
    {
        return $this->call('xperion_press_deploy_pair', $args);
    }

    /**
     * A WordPress hibakódjából érthető magyar ok és teendő (a belépés nem sikerült).
     */
    public static function authMessage(int $status, string $code, string $wpMessage): string
    {
        $why = match ($code) {
            'incorrect_password' => 'az alkalmazásjelszó hibás. Az alkalmazásjelszó nem a belépési jelszó: a WordPress-adminban a felhasználó profilján, az „Alkalmazásjelszavak” résznél kell létrehozni (pl. „Sajtófigyelés” néven), és a megjelenő, szóközökkel tagolt jelszót kell a config.php wordpress részébe, app_password-ként másolni.',
            'invalid_username', 'invalid_email' => 'ilyen felhasználó nincs. A config.php-ba a WordPress-felhasználónév kell (a profil tetején „Felhasználónév”), nem a megjelenített név.',
            'application_passwords_disabled', 'application_passwords_disabled_for_user' => 'az alkalmazásjelszavak ki vannak kapcsolva ezen a webhelyen vagy ennél a felhasználónál (gyakran egy biztonsági bővítmény, pl. Wordfence, kapcsolja ki). Kapcsolja vissza a bővítmény beállításaiban.',
            'rest_forbidden', 'rest_not_logged_in', 'rest_cannot_access' => $status === 403
                ? 'a felhasználó belépett, de nincs elég joga. Legyen legalább Szerző (bejegyzés írása, közzététele, kép feltöltése).'
                : 'a WordPress nem kapta meg a belépési adatot. Megosztott tárhelyen ez a leggyakoribb: a szerver kiszűri az „Authorization” fejlécet. Megoldás a Vezérlőpult „Kapcsolat ellenőrzése” gombjánál.',
            default => $status === 403 ? 'a hozzáférés tiltva (pl. biztonsági bővítmény vagy tűzfal blokkolja a REST API-t).' : 'a belépés nem sikerült.',
        };
        return 'A WordPress elutasította a belépést (HTTP ' . $status . ($code !== '' ? ', ' . $code : '') . '): ' . $why;
    }

    /**
     * Belépés-diagnosztika csak olvasó hívással (/wp-json/wp/v2/users/me): kiderül, hogy a belépési adat
     * eljut-e a WordPressig, helyes-e, és milyen szerepkörű a felhasználó.
     *
     * @return array{ok:bool, user:string, roles:list<string>, code:string, message:string, hint:string}
     */
    public function diagnose(): array
    {
        $h = ['Authorization' => 'Basic ' . base64_encode($this->user . ':' . $this->appPassword), 'Accept' => 'application/json'];
        try {
            $r = $this->http->request('GET', $this->baseUrl . '/wp-json/wp/v2/users/me?context=edit', $h);
            if ($r->status === 404) {
                $r = $this->http->request('GET', $this->baseUrl . '/?rest_route=/wp/v2/users/me&context=edit', $h);
            }
        } catch (HttpException $e) {
            return ['ok' => false, 'user' => '', 'roles' => [], 'code' => 'transport', 'message' => $e->getMessage(), 'hint' => 'A webhely nem érhető el.'];
        }
        $d = json_decode($r->body, true) ?: [];
        if ($r->status === 200) {
            return ['ok' => true, 'user' => (string) ($d['name'] ?? $d['slug'] ?? ''), 'roles' => array_values(array_map('strval', (array) ($d['roles'] ?? []))), 'code' => '', 'message' => '', 'hint' => ''];
        }
        $code = (string) ($d['code'] ?? '');
        $hint = $r->status === 401 && in_array($code, ['rest_not_logged_in', 'rest_forbidden', ''], true)
            ? 'A belépési adat nem jut el a WordPressig: a tárhely kiszűri az „Authorization” fejlécet. A tárhely fájlkezelőjében nyissa meg a WordPress gyökérmappájában lévő .htaccess fájlt, és a „# BEGIN WordPress” sor elé írja be ezt a két sort:' . "\n" . 'RewriteEngine On' . "\n" . 'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]' . "\n" . 'Mentés után próbálja újra. Ha így sem megy, a tárhely ügyfélszolgálata tudja engedélyezni az Authorization fejléc továbbítását.'
            : self::authMessage($r->status, $code, (string) ($d['message'] ?? ''));
        return ['ok' => false, 'user' => '', 'roles' => [], 'code' => $code, 'message' => (string) ($d['message'] ?? ''), 'hint' => $hint];
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function call(string $tool, array $args): array
    {
        $body = json_encode(['jsonrpc' => '2.0', 'id' => bin2hex(random_bytes(8)), 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => (object) $args]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $headers = ['Authorization' => 'Basic ' . base64_encode($this->user . ':' . $this->appPassword), 'Content-Type' => 'application/json', 'Accept' => 'application/json'];
        try {
            $r = $this->http->request('POST', $this->baseUrl . '/wp-json/xperion-bridge/v1/press', $headers, $body);
            if ($r->status === 404) {
                // permalink nélküli WordPress
                $r = $this->http->request('POST', $this->baseUrl . '/?rest_route=/xperion-bridge/v1/press', $headers, $body);
            }
        } catch (HttpException $e) {
            throw new WpException('A WordPress nem válaszolt időben (' . $e->getMessage() . '). Legközelebb előbb az állapotot kérdezem le.', 'transport');
        }
        if ($r->status === 401 || $r->status === 403) {
            $e = json_decode($r->body, true);
            throw new WpException(self::authMessage($r->status, (string) ($e['code'] ?? ''), (string) ($e['message'] ?? '')), 'auth', $r->status);
        }
        if ($r->status === 404) {
            throw new WpException('A sajtószemle-végpont nem található (HTTP 404). Telepítve és bekapcsolva van az Xperion OAuth Bridge 0.6.0 bővítmény?', 'tool', 404);
        }
        if ($r->status >= 500 || $r->status === 0) {
            throw new WpException('A WordPress-szerver hibát adott (HTTP ' . $r->status . '). Legközelebb előbb az állapotot kérdezem le.', 'transport', $r->status);
        }
        $d = json_decode($r->body, true);
        if (!is_array($d)) {
            throw new WpException('A WordPress nem JSON-választ adott (HTTP ' . $r->status . ').', 'transport', $r->status);
        }
        if (isset($d['error'])) {
            throw new WpException('A bővítmény hibát adott: ' . (string) ($d['error']['message'] ?? 'ismeretlen'), 'tool', $r->status);
        }
        $text = (string) ($d['result']['content'][0]['text'] ?? '');
        if (!empty($d['result']['isError'])) {
            throw new WpException($text !== '' ? $text : 'A bővítmény ismeretlen hibát adott.', 'tool', $r->status);
        }
        $data = json_decode($text, true);
        if (!is_array($data)) {
            throw new WpException('A bővítmény válasza nem értelmezhető.', 'tool', $r->status);
        }
        return $data;
    }
}

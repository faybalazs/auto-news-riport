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
        $fail = fn (string $code, string $hint, string $msg = '') => ['ok' => false, 'user' => '', 'roles' => [], 'code' => $code, 'message' => $msg, 'hint' => $hint];
        // 1. Átirányít-e a webhely (pl. www nélkül → www-vel, http → https)? Átirányításkor a belépési adat elveszik.
        try {
            $idx = $this->http->request('GET', $this->baseUrl . '/wp-json/', ['Accept' => 'application/json']);
            if ($idx->status === 404) {
                $idx = $this->http->request('GET', $this->baseUrl . '/?rest_route=/', ['Accept' => 'application/json']);
            }
        } catch (HttpException $e) {
            return $fail('transport', 'A webhely nem érhető el (' . $e->getMessage() . ').');
        }
        $final = self::origin((string) ($idx->url ?? ''));
        if ($final !== '' && $final !== self::origin($this->baseUrl)) {
            return $fail('redirect', 'A(z) ' . $this->baseUrl . ' cím átirányít ide: ' . $final . '. Átirányításkor a belépési adat elveszik, ezért a WordPress nem lát belépést. '
                . 'Írja a config.php wordpress részébe, a felhasználó és az alkalmazásjelszó mellé ezt a sort, majd próbálja újra:' . "\n```\n'base_url' => '" . $final . "',\n```");
        }
        // 2. Elérhetők-e az alkalmazásjelszavak? (A WordPress csak HTTPS-en kínálja őket; biztonsági bővítmény is kikapcsolhatja.)
        $index = json_decode($idx->body, true);
        if (is_array($index) && array_key_exists('authentication', $index) && !isset(((array) $index['authentication'])['application-passwords'])) {
            return $fail('application_passwords_unavailable', 'Az alkalmazásjelszavak ezen a webhelyen nem használhatók, ezért a WordPress figyelmen kívül hagyja a belépési adatot. Két gyakori oka van:' . "\n"
                . '1. Egy biztonsági bővítmény (pl. Wordfence, iThemes/Solid Security, All In One WP Security) kikapcsolta őket: a bővítmény beállításaiban engedélyezze az alkalmazásjelszavakat (Application Passwords).' . "\n"
                . '2. A WordPress nem érzékeli a HTTPS-t (a tárhely proxyn vagy CDN-en át szolgál ki). Ekkor a WordPress gyökérmappájában lévő wp-config.php-ba, a „That\'s all, stop editing” sor elé írja be:' . "\n```\n" . 'if (($_SERVER[\'HTTP_X_FORWARDED_PROTO\'] ?? \'\') === \'https\') { $_SERVER[\'HTTPS\'] = \'on\'; }' . "\n```");
        }
        // 3. Belépés próbája (csak olvas).
        $h = ['Authorization' => 'Basic ' . base64_encode($this->user . ':' . $this->appPassword), 'Accept' => 'application/json'];
        try {
            $r = $this->http->request('GET', $this->baseUrl . '/wp-json/wp/v2/users/me?context=edit', $h);
            if ($r->status === 404) {
                $r = $this->http->request('GET', $this->baseUrl . '/?rest_route=/wp/v2/users/me&context=edit', $h);
            }
        } catch (HttpException $e) {
            return $fail('transport', 'A webhely nem érhető el (' . $e->getMessage() . ').');
        }
        $d = json_decode($r->body, true) ?: [];
        if ($r->status === 200) {
            return ['ok' => true, 'user' => (string) ($d['name'] ?? $d['slug'] ?? ''), 'roles' => array_values(array_map('strval', (array) ($d['roles'] ?? []))), 'code' => '', 'message' => '', 'hint' => ''];
        }
        $code = (string) ($d['code'] ?? '');
        $hint = $r->status === 401 && in_array($code, ['rest_not_logged_in', 'rest_forbidden', ''], true)
            ? 'A webhely nem irányít át, és az alkalmazásjelszavak elérhetők, mégsem ér el a belépési adat a WordPressig: a tárhely kiszűri az „Authorization” fejlécet.' . "\n"
                . 'Ellenőrizze: a .htaccess az, amelyik a WordPress gyökérmappájában, a wp-config.php mellett van (nem a sajtófigyelő mappájában), és a sorok a „# BEGIN WordPress” sor FÖLÖTT vannak. Ezt a három sort írja be (a harmadik egyes tárhelyeken kell):'
                . "\n```\n" . 'RewriteEngine On' . "\n" . 'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]' . "\n" . 'SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1' . "\n```\n"
                . 'Ha így sem megy, kérje a tárhely ügyfélszolgálatától az Authorization fejléc továbbítását a PHP felé (LiteSpeed vagy nginx szervernél a .htaccess ezt nem mindig tudja).'
            : self::authMessage($r->status, $code, (string) ($d['message'] ?? ''));
        return $fail($code, $hint, (string) ($d['message'] ?? ''));
    }

    /** séma://gép a címből (kisbetűvel, port nélkül, ha alapértelmezett); üres, ha nem értelmezhető */
    private static function origin(string $url): string
    {
        $p = parse_url($url);
        if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) {
            return '';
        }
        return strtolower($p['scheme']) . '://' . strtolower($p['host']) . (isset($p['port']) ? ':' . $p['port'] : '');
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

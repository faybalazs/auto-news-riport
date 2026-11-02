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
            throw new WpException('A WordPress elutasította a belépést (HTTP ' . $r->status . '). Ellenőrizze a config.php wordpress részét (felhasználó, alkalmazásjelszó) és a felhasználó jogait (Szerző).', 'auth', $r->status);
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

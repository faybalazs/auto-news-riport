<?php
declare(strict_types=1);

namespace Xperion\Tests;

use Xperion\Http\HttpClient;
use Xperion\Http\HttpResponse;

/**
 * Az Xperion OAuth Bridge sajtószemle-végpontjának helyettesítője (PRESS_API.md válaszformái):
 * párok kulcs szerint, deploy_pair idempotens, hibák forrás-URL szerint előírhatók.
 */
final class FakeWpBridge implements HttpClient
{
    /** @var array<string, array<string,mixed>> kulcs → pár */
    public array $pairs = [];
    /** @var array<string,string> forrás-URL → a bővítmény hibaüzenete (deploy_pair-nél) */
    public array $failFor = [];
    public int $status = 200;
    /** A 401/403-as válasz WordPress-hibakódja (pl. incorrect_password, rest_forbidden). */
    public string $authCode = 'rest_forbidden';
    /** @var array{0:int,1:array<string,mixed>} a /wp/v2/users/me válasza */
    public array $me = [200, ['name' => 'cikkfigyelo', 'roles' => ['author']]];
    /** @var list<array{tool:string, args:array<string,mixed>, auth:string, url:string}> */
    public array $calls = [];
    private int $nextId = 100;

    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        if ($method === 'GET' && str_contains($url, 'users/me')) {
            $this->calls[] = ['tool' => 'users/me', 'args' => [], 'auth' => (string) ($headers['Authorization'] ?? ''), 'url' => $url];
            return new HttpResponse($this->me[0], (string) json_encode($this->me[1]));
        }
        $b = json_decode((string) $body, true);
        $tool = (string) ($b['params']['name'] ?? '');
        $args = (array) ($b['params']['arguments'] ?? []);
        $this->calls[] = ['tool' => $tool, 'args' => $args, 'auth' => (string) ($headers['Authorization'] ?? ''), 'url' => $url];
        if ($this->status !== 200) {
            return new HttpResponse($this->status, (string) json_encode(['code' => $this->authCode, 'message' => 'WordPress message', 'data' => ['status' => $this->status]]));
        }
        $ok = fn (array $d) => new HttpResponse(200, (string) json_encode(['jsonrpc' => '2.0', 'id' => $b['id'], 'result' => ['content' => [['type' => 'text', 'text' => json_encode($d, JSON_UNESCAPED_UNICODE)]]]]));
        $err = fn (string $m) => new HttpResponse(200, (string) json_encode(['jsonrpc' => '2.0', 'id' => $b['id'], 'result' => ['isError' => true, 'content' => [['type' => 'text', 'text' => $m]]]]));
        $key = (string) ($args['key'] ?? '');
        return match ($tool) {
            'xperion_press_context' => $ok(['version' => '0.6.0', 'language_engine' => 'polylang', 'categories' => [['id' => 3, 'name' => 'Sajtószemle', 'language' => 'hu'], ['id' => 4, 'name' => 'Press review', 'language' => 'en']],
                'defaults' => ['hu_category' => 3, 'en_category' => 4], 'can_publish' => true, 'can_upload' => true, 'pairs' => []]),
            'xperion_press_get_pair' => $ok(['key' => $key, 'state' => $this->pairs[$key]['state'] ?? 'none', 'posts' => $this->pairs[$key]['posts'] ?? []]),
            'xperion_press_deploy_pair' => (function () use ($args, $key, $ok, $err) {
                if (isset($this->failFor[$args['source_url']])) {
                    return $err($this->failFor[$args['source_url']]);
                }
                if (($this->pairs[$key]['state'] ?? '') === 'published') {
                    return $ok(['key' => $key, 'state' => 'published', 'changed' => false, 'posts' => $this->pairs[$key]['posts']]);
                }
                $posts = [];
                foreach (['hu', 'en'] as $l) {
                    $id = $this->nextId++;
                    $posts[] = ['id' => $id, 'title' => $args[$l]['title'], 'summary' => $args[$l]['summary'], 'status' => 'publish', 'language' => $l, 'source_url' => $args['source_url'], 'url' => "https://xperion.hu/{$l}/p{$id}/"];
                }
                $this->pairs[$key] = ['state' => 'published', 'posts' => $posts];
                return $ok(['key' => $key, 'state' => 'published', 'changed' => true, 'posts' => $posts]);
            })(),
            default => $err('Unknown tool.'),
        };
    }
}

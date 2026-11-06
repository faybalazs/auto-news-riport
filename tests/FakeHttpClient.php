<?php
declare(strict_types=1);

namespace Xperion\Tests;

use Xperion\Http\HttpClient;
use Xperion\Http\HttpResponse;

/** Helyettesítő HTTP-kliens: URL-minta szerinti válaszok, a kérések rögzítésével. */
final class FakeHttpClient implements HttpClient
{
    /** @var list<array{method:string, url:string, headers:array<string,string>, body:?string}> */
    public array $requests = [];

    /** @param list<array{0:string, 1:string, 2:int|callable, 3?:string}> $routes [metódus, URL-regex, státusz vagy callable, törzs] */
    public function __construct(private array $routes = [])
    {
    }

    public function on(string $method, string $pattern, int $status, string|array $body = ''): self
    {
        $this->routes[] = [$method, $pattern, $status, is_array($body) ? (string) json_encode($body) : $body];
        return $this;
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        foreach ($this->routes as $r) {
            if ($r[0] === $method && preg_match($r[1], $url)) {
                return new HttpResponse($r[2], $r[3] ?? '');
            }
        }
        return new HttpResponse(404, '{"error":{"message":"nincs ilyen útvonal a tesztben"}}');
    }

    /** @return list<array{method:string, url:string, headers:array<string,string>, body:?string}> */
    public function matching(string $method, string $pattern): array
    {
        return array_values(array_filter($this->requests, static fn ($r) => $r['method'] === $method && preg_match($pattern, $r['url'])));
    }
}

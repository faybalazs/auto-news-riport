<?php
declare(strict_types=1);

namespace Xperion\Tests;

use Xperion\Http\HttpClient;
use Xperion\Http\HttpResponse;

/** Helyettesítő web: URL → oldal tartalma; minden más 404. Rögzíti a lekért címeket. */
final class FakeWeb implements HttpClient
{
    /** @var list<string> */
    public array $requested = [];

    /** @param array<string,string> $pages */
    public function __construct(public array $pages)
    {
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $this->requested[] = $url;
        return isset($this->pages[$url]) ? new HttpResponse(200, $this->pages[$url]) : new HttpResponse(404, 'nincs');
    }
}

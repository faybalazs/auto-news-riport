<?php
declare(strict_types=1);

namespace Xperion\Tests;

use Xperion\Http\HttpClient;
use Xperion\Http\HttpResponse;

/** Helyettesítő Google Sheet: munkalapnév → sorok; a metaadat- és batchGet-hívásokra válaszol. */
final class FakeSpreadsheet implements HttpClient
{
    public int $calls = 0;

    /** @param array<string,list<list<string>>> $sheets */
    public function __construct(public array $sheets)
    {
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $this->calls++;
        if ($method !== 'GET') {
            return new HttpResponse(400, '{"error":{"message":"a tesztben csak olvasás van"}}');
        }
        if (str_contains($url, '?fields=')) {
            $i = 0;
            return new HttpResponse(200, (string) json_encode(['sheets' => array_map(static function ($t) use (&$i) { return ['properties' => ['title' => $t, 'sheetId' => $i++]]; }, array_keys($this->sheets))]));
        }
        if (str_contains($url, 'values:batchGet')) {
            preg_match_all('/ranges=([^&]+)/', $url, $m);
            $out = [];
            foreach ($m[1] as $r) {
                $range = rawurldecode($r);
                $name = str_replace("''", "'", substr($range, 1, strrpos($range, "'!") - 1));
                $out[] = ['range' => $range, 'values' => $this->sheets[$name] ?? []];
            }
            return new HttpResponse(200, (string) json_encode(['valueRanges' => $out]));
        }
        return new HttpResponse(404, '{"error":{"message":"ismeretlen"}}');
    }
}

<?php
declare(strict_types=1);

namespace Xperion\Tests;

use Xperion\Google\SheetsClient;

/** Kész Sheets-kliens a helyettesítő HTTP-vel; a lapok és tartományok tartalma megadható. */
final class SheetsFixture
{
    public FakeHttpClient $http;
    public SheetsClient $client;

    /**
     * @param list<string> $sheetTitles
     * @param array<string,list<list<string>>> $values munkalapnév → sorok (egyedi values-olvasás)
     * @param list<list<list<string>>> $batch a batchGet válasza, a tartományok sorrendjében
     */
    public function __construct(array $sheetTitles, array $values = [], array $batch = [])
    {
        $this->http = new FakeHttpClient();
        $meta = ['sheets' => array_map(static fn ($t, $i) => ['properties' => ['title' => $t, 'sheetId' => $i + 100]], $sheetTitles, array_keys($sheetTitles))];
        $this->http->on('GET', '#\?fields=#', 200, $meta);
        $this->http->on('GET', '#/values:batchGet\?#', 200, ['valueRanges' => array_map(static fn ($v) => ['values' => $v], $batch)]);
        foreach ($values as $sheet => $rows) {
            $this->http->on('GET', '#/values/' . preg_quote(rawurlencode(SheetsClient::range($sheet)), '#') . '#', 200, ['values' => $rows]);
        }
        $this->http->on('GET', '#/values/#', 200, ['values' => []]);
        $this->http->on('POST', '#:batchUpdate$#', 200, ['replies' => [['addSheet' => ['properties' => ['sheetId' => 999]]]]]);
        $this->http->on('PUT', '#/values/#', 200, '{}');
        $this->http->on('POST', '#:append\?#', 200, '{}');
        $this->client = new SheetsClient('TESZT', $this->http, static fn (): string => 'tok');
    }

    /** @return list<array{method:string, url:string, headers:array<string,string>, body:?string}> írási kérések */
    public function writes(): array
    {
        return array_values(array_filter($this->http->requests, static fn ($r) => $r['method'] !== 'GET'));
    }
}

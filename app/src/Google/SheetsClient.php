<?php
declare(strict_types=1);

namespace Xperion\Google;

use Xperion\Http\HttpClient;
use Xperion\Http\HttpException;

/**
 * Google Sheets API v4, csak a szükséges hívások.
 * A magyar területi beállítás a képletekben pontosvesszőt vár; a USER_ENTERED
 * mód a tábla saját beállítása szerint értelmezi a beírt képletet.
 */
final class SheetsClient
{
    private const BASE = 'https://sheets.googleapis.com/v4/spreadsheets/';

    /** @var callable():string */
    private $token;

    public function __construct(private string $spreadsheetId, private HttpClient $http, callable $tokenProvider)
    {
        $this->token = $tokenProvider;
    }

    public function spreadsheetId(): string
    {
        return $this->spreadsheetId;
    }

    /** Munkalapnév + tartomány, idézőjelezve: 'Napló'!A:D */
    public static function range(string $sheet, string $cells = ''): string
    {
        $quoted = "'" . str_replace("'", "''", $sheet) . "'";
        return $cells === '' ? $quoted : $quoted . '!' . $cells;
    }

    /**
     * A munkalapok listája: cím → sheetId.
     *
     * @return array<string,int>
     */
    public function sheets(): array
    {
        $data = $this->call('GET', '?fields=' . rawurlencode('sheets.properties(sheetId,title)'));
        $out = [];
        foreach ($data['sheets'] ?? [] as $s) {
            $out[(string) $s['properties']['title']] = (int) $s['properties']['sheetId'];
        }
        return $out;
    }

    /** @return list<list<string>> */
    public function getValues(string $range): array
    {
        $data = $this->call('GET', '/values/' . rawurlencode($range) . '?valueRenderOption=FORMATTED_VALUE');
        return self::normalizeRows($data['values'] ?? []);
    }

    /**
     * Több tartomány egy hívással.
     *
     * @param list<string> $ranges
     * @return list<list<list<string>>>
     */
    public function batchGetValues(array $ranges): array
    {
        $query = implode('&', array_map(static fn ($r) => 'ranges=' . rawurlencode($r), $ranges));
        $data = $this->call('GET', '/values:batchGet?' . $query . '&valueRenderOption=FORMATTED_VALUE');
        $out = [];
        foreach ($data['valueRanges'] ?? [] as $vr) {
            $out[] = self::normalizeRows($vr['values'] ?? []);
        }
        return $out;
    }

    /** @param list<list<mixed>> $rows */
    public function updateValues(string $range, array $rows): void
    {
        $this->call('PUT', '/values/' . rawurlencode($range) . '?valueInputOption=USER_ENTERED', ['range' => $range, 'values' => $rows]);
    }

    /** @param list<list<mixed>> $rows */
    public function appendValues(string $range, array $rows): void
    {
        $this->call('POST', '/values/' . rawurlencode($range) . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS', ['values' => $rows]);
    }

    /**
     * @param list<array<string,mixed>> $requests
     * @return array<mixed>
     */
    public function batchUpdate(array $requests): array
    {
        return $this->call('POST', ':batchUpdate', ['requests' => $requests]);
    }

    /** Új munkalap; visszaadja a sheetId-t. */
    public function addSheet(string $title, int $frozenRows = 0): int
    {
        $props = ['title' => $title];
        if ($frozenRows > 0) {
            $props['gridProperties'] = ['frozenRowCount' => $frozenRows];
        }
        $res = $this->batchUpdate([['addSheet' => ['properties' => $props]]]);
        return (int) ($res['replies'][0]['addSheet']['properties']['sheetId'] ?? 0);
    }

    /**
     * @param array<mixed>|null $body
     * @return array<mixed>
     */
    private function call(string $method, string $path, ?array $body = null): array
    {
        $headers = ['Authorization' => 'Bearer ' . ($this->token)()];
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json; charset=utf-8';
        }
        $response = $this->http->request(
            $method,
            self::BASE . rawurlencode($this->spreadsheetId) . $path,
            $headers,
            $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
        if (!$response->ok()) {
            $hint = match ($response->status) {
                403 => ' (Meg van osztva a tábla a szolgáltatásfiók e-mail-címével? Be van kapcsolva a Google Sheets API a Google Cloud projektben?)',
                404 => ' (A tábla nem található: ellenőrizze az ügyfélnél megadott linket.)',
                default => '',
            };
            throw new HttpException('Google Sheets: ' . $response->errorMessage() . $hint, $response->status);
        }
        return $response->body === '' ? [] : $response->json();
    }

    /**
     * @param array<mixed> $rows
     * @return list<list<string>>
     */
    private static function normalizeRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = array_map(static fn ($v) => is_scalar($v) ? (string) $v : '', array_values((array) $row));
        }
        return $out;
    }
}

<?php
declare(strict_types=1);

namespace Xperion\Sheets;

use Xperion\Google\SheetsClient;

/** A "Beállítások" lap (A: Kulcs, B: Érték). */
final class Settings
{
    /** @var array<string,array{row:int, value:string}>|null */
    private ?array $cache = null;

    public function __construct(private SheetsClient $sheets)
    {
    }

    /** @return array<string,string> */
    public function all(): array
    {
        return array_map(static fn ($e) => $e['value'], $this->load());
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->load()[$key]['value'] ?? $default;
    }

    public function set(string $key, string $value): void
    {
        $entries = $this->load();
        if (isset($entries[$key])) {
            $row = $entries[$key]['row'];
            $this->sheets->updateValues(SheetsClient::range(SheetNames::SETTINGS, 'B' . $row), [[$value]]);
        } else {
            $this->sheets->appendValues(SheetsClient::range(SheetNames::SETTINGS, 'A:B'), [[$key, $value]]);
        }
        $this->cache = null;
    }

    /**
     * @param list<list<string>> $rows a lap A:B tartománya
     * @return array<string,array{row:int, value:string}>
     */
    public static function parse(array $rows): array
    {
        $out = [];
        foreach ($rows as $i => $row) {
            $key = trim($row[0] ?? '');
            if ($key === '' || ($i === 0 && $key === 'Kulcs')) {
                continue;
            }
            $out[$key] = ['row' => $i + 1, 'value' => trim($row[1] ?? '')];
        }
        return $out;
    }

    /** @return array<string,array{row:int, value:string}> */
    private function load(): array
    {
        return $this->cache ??= self::parse($this->sheets->getValues(SheetsClient::range(SheetNames::SETTINGS, 'A:B')));
    }
}

<?php
declare(strict_types=1);

namespace Xperion\Monthly;

use Xperion\Claude\ClaudeClient;

/** Vezetői összefoglaló: „Címke: érték” sorok és egy rövid Részletek bekezdés. */
final class Summarizer
{
    public function __construct(private ClaudeClient $claude)
    {
    }

    /**
     * @param list<array<string,mixed>> $items értékelt cikkek
     * @return array{rows: list<array{label:string,value:string}>, details:string}
     */
    public function summarize(string $clientName, string $monthLabel, array $items, string $model): array
    {
        $system = "Te a(z) {$clientName} havi sajtófigyelésének vezetői összefoglalóját írod, magyarul.\n"
            . "- rows: 4–6 sor „Címke: érték” formában, rövid értékekkel (pl. Cikkek száma: 26; Legtöbb cikk: Vemco Group (14 db); Erősen javasolt: 6; Fő téma: …). Hosszú mondat nincs.\n"
            . "- details: 2–4 mondat a hónap fő témáiról és arról, mi a legrelevánsabb a cégnek. Tárgyilagos, nem reklámízű; csak a kapott adatokból dolgozz.";
        $list = array_map(static fn ($i) => ['source' => $i['source'], 'title' => $i['title'], 'recommendation' => $i['recommendation'], 'new_source' => (bool) ($i['new_source'] ?? false)], $items);
        $schema = [
            'type' => 'object',
            'properties' => [
                'rows' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['label' => ['type' => 'string'], 'value' => ['type' => 'string']], 'required' => ['label', 'value'], 'additionalProperties' => false]],
                'details' => ['type' => 'string'],
            ],
            'required' => ['rows', 'details'],
            'additionalProperties' => false,
        ];
        $res = $this->claude->json($model, $system, "Hónap: {$monthLabel}\nA hónap értékelt cikkei:\n" . json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), $schema, 'medium');
        $rows = array_values(array_filter(array_map(static fn ($r) => ['label' => trim((string) ($r['label'] ?? '')), 'value' => trim((string) ($r['value'] ?? ''))], $res['rows'] ?? []), static fn ($r) => $r['label'] !== ''));
        return ['rows' => $rows, 'details' => trim((string) ($res['details'] ?? ''))];
    }
}

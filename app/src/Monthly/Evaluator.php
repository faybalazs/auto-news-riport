<?php
declare(strict_types=1);

namespace Xperion\Monthly;

use Xperion\Claude\ClaudeClient;
use Xperion\Claude\ClaudeException;
use Xperion\Db\Database;

/**
 * Cikkek értékelése a Claude-dal: magyar leírás (Desc), Javaslat és Indoklás.
 * A rendszerprompt ügyfelenként stabil (gyorsítótárazható): cégleírás a posztszabályokból,
 * kulcsszavak, az ügyfél korábbi döntései és a már feltöltött cikkek címei.
 */
final class Evaluator
{
    public const RECOMMENDATIONS = ['Erősen javasolt', 'Javasolt', 'Nem javasolt', 'Nem javasolt (már fent)'];
    public const BATCH = 20;

    public function __construct(private Database $db, private ClaudeClient $claude)
    {
    }

    public function systemPrompt(int $clientId): string
    {
        $client = (string) $this->db->value('SELECT name FROM clients WHERE id = ?', [$clientId]);
        $company = $this->db->all("SELECT name, value FROM post_rules WHERE client_id = ? AND (name IN ('Cég','Célközönség','Megoldásterületek','Partnerek','A posztok célja') OR name LIKE 'Kulcsfogalom%') ORDER BY sort", [$clientId]);
        $kw = $this->db->all('SELECT keyword, topic, is_exclude FROM keywords WHERE client_id = ? ORDER BY keyword', [$clientId]);
        $chosen = $this->db->all("SELECT title, decision FROM articles WHERE client_id = ? AND decision IN ('mehet','linkedin') ORDER BY month DESC, id DESC LIMIT 40", [$clientId]);
        $rejected = $this->db->all("SELECT title FROM articles WHERE client_id = ? AND decision = 'nemkell' ORDER BY month DESC, id DESC LIMIT 20", [$clientId]);
        $uploaded = $this->db->all('SELECT title FROM articles WHERE client_id = ? AND uploaded = 1 ORDER BY month DESC, id DESC LIMIT 80', [$clientId]);

        $lines = ["Te a(z) {$client} havi sajtófigyelésének szerkesztője vagy. A feladatod: eldönteni, mely iparági cikkek érdemesek arra, hogy a(z) {$client} weboldalán (saját szavakkal átírva) megjelenjenek, vagy LinkedIn-poszt alapjául szolgáljanak.", ''];
        if ($company) {
            $lines[] = '## A cég';
            foreach ($company as $r) {
                $lines[] = "- {$r['name']}: {$r['value']}";
            }
            $lines[] = '';
        }
        $pos = array_filter($kw, static fn ($k) => !(int) $k['is_exclude']);
        $neg = array_filter($kw, static fn ($k) => (int) $k['is_exclude']);
        if ($pos) {
            $lines[] = '## Fontos témák (kulcsszavak)';
            $lines[] = implode(', ', array_map(static fn ($k) => $k['keyword'] . ($k['topic'] !== '' ? " ({$k['topic']})" : ''), $pos));
            $lines[] = '';
        }
        if ($neg) {
            $lines[] = '## Kerülendő témák';
            $lines[] = implode(', ', array_column($neg, 'keyword'));
            $lines[] = '';
        }
        if ($chosen) {
            $lines[] = '## Az ügyfél korábban ezeket választotta (Mehet = feltöltés, LinkedIn = poszt)';
            foreach ($chosen as $a) {
                $lines[] = '- ' . ($a['decision'] === 'mehet' ? 'Mehet' : 'LinkedIn') . ': ' . $a['title'];
            }
            $lines[] = '';
        }
        if ($rejected) {
            $lines[] = '## Az ügyfél ezeket nem kérte';
            foreach ($rejected as $a) {
                $lines[] = '- ' . $a['title'];
            }
            $lines[] = '';
        }
        if ($uploaded) {
            $lines[] = '## Már feltöltött cikkek (ha egy új cikk lényegében ugyanerről szól, a javaslat „Nem javasolt (már fent)”)';
            foreach ($uploaded as $a) {
                $lines[] = '- ' . $a['title'];
            }
            $lines[] = '';
        }
        $lines[] = '## Az értékelés';
        $lines[] = '- desc: magyarul, 1–2 mondat, saját szavakkal; tárgyilagos, ne reklámízű. Csak a cikkben szereplő tényeket használd.';
        $lines[] = '- recommendation: „Erősen javasolt”, ha szorosan illeszkedik a cég témáihoz és a korábbi választásokhoz, és van benne konkrétum (mért eredmény, esettanulmány, kutatás); „Javasolt”, ha kapcsolódik, de kevésbé erős; „Nem javasolt”, ha céges hír, rendezvény-, személyi vagy pénzügyi hír, vagy nem kapcsolódik; „Nem javasolt (már fent)”, ha egy már feltöltött cikkel lényegében azonos.';
        $lines[] = '- reason: magyarul, egy rövid mondat, miért.';
        $lines[] = '- Minden kapott cikkre pontosan egy értékelést adj, a kapott index szerint.';
        return implode("\n", $lines);
    }

    /**
     * @param list<array<string,mixed>> $items gyűjtött cikkek (title, url, date, description, source)
     * @return list<array{desc:string, recommendation:string, reason:string}> ugyanabban a sorrendben
     */
    public function evaluate(int $clientId, array $items, string $model): array
    {
        $system = $this->systemPrompt($clientId);
        $schema = [
            'type' => 'object',
            'properties' => ['items' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => [
                    'index' => ['type' => 'integer'],
                    'desc' => ['type' => 'string'],
                    'recommendation' => ['type' => 'string', 'enum' => self::RECOMMENDATIONS],
                    'reason' => ['type' => 'string'],
                ],
                'required' => ['index', 'desc', 'recommendation', 'reason'],
                'additionalProperties' => false,
            ]]],
            'required' => ['items'],
            'additionalProperties' => false,
        ];
        $out = [];
        foreach (array_chunk($items, self::BATCH, true) as $chunk) {
            $payload = [];
            foreach ($chunk as $i => $it) {
                $payload[] = ['index' => $i, 'source' => $it['source'] ?? '', 'date' => $it['date'] ?? '', 'title' => $it['title'], 'summary' => mb_substr((string) ($it['description'] ?? ''), 0, 600), 'url' => $it['url']];
            }
            $res = $this->claude->json($model, $system, "Értékeld a következő cikkeket:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), $schema, 'low');
            foreach ($res['items'] ?? [] as $r) {
                $idx = (int) ($r['index'] ?? -1);
                if (array_key_exists($idx, $chunk) && in_array($r['recommendation'] ?? '', self::RECOMMENDATIONS, true)) {
                    $out[$idx] = ['desc' => trim((string) $r['desc']), 'recommendation' => (string) $r['recommendation'], 'reason' => trim((string) $r['reason'])];
                }
            }
            foreach (array_keys($chunk) as $idx) {
                if (!isset($out[$idx])) {
                    throw new ClaudeException('Az értékelés hiányos: a(z) ' . ($idx + 1) . '. cikkre nem jött válasz.');
                }
            }
        }
        ksort($out);
        return array_values($out);
    }
}

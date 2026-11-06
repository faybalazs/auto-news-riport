<?php
declare(strict_types=1);

namespace Xperion\Collect;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Claude\ClaudeClient;
use Xperion\Db\Database;
use Xperion\Import\Url;
use Xperion\Monthly\MonthlyService;

/**
 * Új-forrás-figyelő: a Claude webkereséssel a kulcsszavak és témák alapján olyan webhelyeket keres, amelyek még nincsenek
 * a figyelt források között; mindegyiket próbagyűjtéssel ellenőrizzük, és csak a működőket tesszük a javaslatlistára.
 * Figyelt forrás csak a felhasználó „Felvétel” döntésével lesz belőle (próbaidős „új forrás” jelöléssel); az elvetett
 * címet a figyelő többé nem javasolja.
 */
final class SourceDiscovery
{
    public const DEFAULT_COUNT = 3;
    public const MAX_COUNT = 10;
    public const SETTING = 'uj_forras_db';

    private const SYSTEM = <<<'TXT'
Egy B2B sajtófigyelő szolgáltatás forráskutatója vagy. Feladatod: új, rendszeresen frissülő szakmai hírforrásokat keresni
a megadott ügyfél témáihoz a webkereső eszközzel.

Jó forrás: szakmai hírportál, iparági magazin, szövetség vagy elemzőcég híroldala, versenytárs vagy partner hírek/sajtó
oldala, amely havonta több releváns cikket közöl. Rossz forrás: egyetlen cikk, közösségi média, fórum, álláshirdetés,
fizetős fal mögötti oldal, aggregátor, a már figyelt webhelyek bármely aloldala.

Az URL a forrás hírlistájának vagy RSS-csatornájának címe legyen (pl. https://pelda.hu/hirek vagy https://pelda.hu/feed),
ne egy konkrét cikké és ne a nyitóoldal, ha van külön hírlista. Csak olyan címet adj meg, amelyet a keresésben láttál.

A válaszod végén pontosan egy ```json kódblokk legyen ebben a szerkezetben:
{"sources":[{"name":"…","url":"https://…","type":"szakmai média | versenytárs | partner | elemző | szövetség","topics":"2–4 téma vesszővel","reason":"egy magyar mondat: miért releváns"}]}
TXT;

    public function __construct(private Database $db, private AuditLog $audit, private ClaudeClient $claude, private Collector $collector)
    {
    }

    public function count(int $clientId): int
    {
        $v = (int) $this->db->value('SELECT value FROM client_settings WHERE client_id = ? AND name = ?', [$clientId, self::SETTING]);
        return $v > 0 ? min($v, self::MAX_COUNT) : self::DEFAULT_COUNT;
    }

    /** @return list<array<string,mixed>> a döntésre váró javaslatok */
    public function pending(Access $a): array
    {
        $cid = $a->clientId();
        if ($cid === null) {
            return [];
        }
        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'url' => (string) $r['url'], 'type' => (string) $r['type'],
            'topics' => (string) $r['topics'], 'reason' => (string) $r['reason'], 'method' => (string) $r['test_method'], 'count' => (int) $r['test_count'],
            'sample' => json_decode((string) $r['sample'], true) ?: [], 'created' => substr((string) $r['created_at'], 0, 10)],
            $this->db->all("SELECT * FROM source_suggestions WHERE client_id = ? AND status = 'javasolt' ORDER BY created_at DESC, id", [$cid]));
    }

    /**
     * Keresés: legfeljebb $count új, próbagyűjtéssel ellenőrzött javaslat.
     *
     * @return array{found:int, checked:int, failed:list<string>, pending:list<array<string,mixed>>, count:int}
     */
    public function search(Access $a, int $count): array
    {
        $a->require('sources');
        $client = $a->client() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        $cid = (int) $client['id'];
        if (!$this->claude->hasKey()) {
            throw new \InvalidArgumentException('Nincs beállítva a Claude API-kulcs (config.php: claude_api_key). Szóljon az üzemeltetőnek.');
        }
        $count = max(1, min(self::MAX_COUNT, $count));
        $this->db->run('INSERT INTO client_settings (client_id, name, value, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)',
            [$cid, self::SETTING, (string) $count, Database::now()]);

        $known = $this->knownHosts($cid, (string) ($client['website'] ?? ''));
        $model = (string) ($this->db->value("SELECT value FROM client_settings WHERE client_id = ? AND name = 'modell_poszt'", [$cid]) ?: MonthlyService::DEFAULT_SUMMARY_MODEL);
        $before = $this->claude->usage;
        $out = $this->claude->webSearchJson($model, self::SYSTEM, $this->prompt($cid, (string) $client['name'], (string) ($client['website'] ?? ''), $known, $count), min(20, 4 + 2 * $count));

        $found = 0;
        $checked = 0;
        $failed = [];
        foreach (array_slice((array) ($out['sources'] ?? []), 0, $count + 4) as $s) {
            if ($found >= $count || !is_array($s)) {
                break;
            }
            $url = trim((string) ($s['url'] ?? ''));
            $host = self::host($url);
            if (!preg_match('~^https?://[^\s/]+~i', $url) || $host === '' || isset($known[$host])) {
                continue;
            }
            $hash = Url::hash($url);
            if ($this->db->value('SELECT id FROM source_suggestions WHERE client_id = ? AND url_hash = ?', [$cid, $hash])) {
                continue;
            }
            $known[$host] = true;
            $checked++;
            $t = $this->collector->collect($url, (string) ($s['type'] ?? ''));
            $name = mb_substr(trim((string) ($s['name'] ?? '')) ?: $host, 0, 190);
            if (!$t['items']) {
                $failed[] = $name . ': ' . ($t['error'] ?? 'nincs cikk');
                continue;
            }
            $this->db->insert('source_suggestions', [
                'client_id' => $cid, 'name' => $name, 'url' => mb_substr($url, 0, 500), 'url_hash' => $hash,
                'type' => mb_substr(trim((string) ($s['type'] ?? '')), 0, 100), 'topics' => mb_substr(trim((string) ($s['topics'] ?? '')), 0, 500),
                'reason' => mb_substr(trim((string) ($s['reason'] ?? '')), 0, 1000), 'test_method' => mb_substr((string) $t['method'], 0, 100), 'test_count' => count($t['items']),
                'sample' => json_encode(array_map(static fn ($i) => ['title' => $i->title, 'date' => $i->date, 'url' => $i->url], array_slice($t['items'], 0, 3)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'javasolt', 'created_at' => Database::now(),
            ]);
            $found++;
        }
        $usd = ClaudeClient::estimateUsd($model, ['input' => $this->claude->usage['input'] - $before['input'], 'output' => $this->claude->usage['output'] - $before['output']])
            + ($this->claude->usage['searches'] - $before['searches']) * 0.01;
        $this->audit->add($cid, $a->userId(), 'Új források keresése', sprintf('%d javaslat (%d ellenőrizve), %s, kb. %.2f USD', $found, $checked, $model, $usd));
        return ['found' => $found, 'checked' => $checked, 'failed' => $failed, 'pending' => $this->pending($a), 'count' => $count];
    }

    /** A javaslat felvétele figyelt forrásnak (aktív, „új forrás” próbaidős jelöléssel). @return array<string,mixed> */
    public function accept(Access $a, int $id, SourceService $sources): array
    {
        $a->require('sources');
        $s = $this->get($a, $id);
        $data = $sources->saveSource($a, null, ['url' => $s['url'], 'name' => $s['name'], 'type' => $s['type'], 'topics' => $s['topics'], 'active' => true, 'is_new' => true,
            'note' => 'Új-forrás-figyelő javaslata (' . date('Y. m. d.') . ')']);
        $this->decide($a, $id, 'felveve');
        return $data;
    }

    public function dismiss(Access $a, int $id): void
    {
        $a->require('sources');
        $s = $this->get($a, $id);
        $this->decide($a, $id, 'elvetve');
        $this->audit->add((int) $a->clientId(), $a->userId(), 'Forrásjavaslat elvetve', $s['name'] . ' · ' . $s['url']);
    }

    /** @return array<string,mixed> */
    private function get(Access $a, int $id): array
    {
        $cid = $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
        return $this->db->one("SELECT * FROM source_suggestions WHERE id = ? AND client_id = ? AND status = 'javasolt'", [$id, $cid])
            ?? throw new \InvalidArgumentException('Ez a javaslat már nem vár döntésre.');
    }

    private function decide(Access $a, int $id, string $status): void
    {
        $this->db->run('UPDATE source_suggestions SET status = ?, decided_at = ?, decided_by = ? WHERE id = ? AND client_id = ?', [$status, Database::now(), $a->userId(), $id, (int) $a->clientId()]);
    }

    /** @return array<string,bool> a már figyelt, javasolt vagy elvetett webhelyek (és az ügyfél saját webhelye) */
    private function knownHosts(int $cid, string $website): array
    {
        $out = [];
        foreach ([$website, ...array_column($this->db->all('SELECT url FROM sources WHERE client_id = ? UNION SELECT url FROM source_suggestions WHERE client_id = ?', [$cid, $cid]), 'url')] as $u) {
            $h = self::host(str_contains((string) $u, '://') ? (string) $u : 'https://' . $u);
            if ($h !== '') {
                $out[$h] = true;
            }
        }
        return $out;
    }

    /** @param array<string,bool> $known */
    private function prompt(int $cid, string $name, string $website, array $known, int $count): string
    {
        $kw = $this->db->all('SELECT keyword, topic, is_exclude FROM keywords WHERE client_id = ? ORDER BY is_exclude, topic, keyword', [$cid]);
        $topics = array_values(array_unique(array_filter(array_merge(
            array_map(static fn ($r) => trim((string) $r['topic']), array_filter($kw, static fn ($r) => !$r['is_exclude'])),
            array_merge(...array_map(static fn ($r) => array_map('trim', explode(',', (string) $r['topics'])), $this->db->all('SELECT topics FROM sources WHERE client_id = ? AND active = 1', [$cid]) ?: [['topics' => '']])),
        ))));
        $words = array_map(static fn ($r) => (string) $r['keyword'], array_filter($kw, static fn ($r) => !$r['is_exclude']));
        $exclude = array_map(static fn ($r) => (string) $r['keyword'], array_filter($kw, static fn ($r) => (bool) $r['is_exclude']));
        $n = $count + 2;
        return "Ügyfél: {$name}" . ($website !== '' ? " ({$website})" : '') . "\n"
            . 'Témák: ' . (implode(', ', array_slice($topics, 0, 30)) ?: '–') . "\n"
            . 'Figyelt kulcsszavak: ' . (implode(', ', array_slice($words, 0, 40)) ?: '–') . "\n"
            . 'Kizárt témák (ilyen forrás ne legyen): ' . (implode(', ', array_slice($exclude, 0, 20)) ?: '–') . "\n"
            . 'Már figyelt webhelyek (ezeket és aloldalaikat ne javasold): ' . (implode(', ', array_slice(array_keys($known), 0, 150)) ?: '–') . "\n\n"
            . "Keress {$n} új forrást (magyar és nemzetközi, angol nyelvű is lehet). Sorrend: a legrelevánsabb elöl.";
    }

    private static function host(string $url): string
    {
        return strtolower((string) preg_replace('/^www\./i', '', (string) parse_url($url, PHP_URL_HOST)));
    }
}

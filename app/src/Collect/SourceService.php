<?php
declare(strict_types=1);

namespace Xperion\Collect;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Db\Database;
use Xperion\Import\Url;

/**
 * Figyelt források és kulcsszavak ügyfelenként (a havi gyűjtés ezekből dolgozik). Szerkesztheti az
 * üzemeltető és az ügyfél-adminisztrátor („sources” jog); minden módosítás a műveleti naplóba kerül.
 */
final class SourceService
{
    public function __construct(private Database $db, private AuditLog $audit, private Collector $collector)
    {
    }

    /** @return array{sources:list<array<string,mixed>>, keywords:list<array<string,mixed>>} */
    public function all(Access $a): array
    {
        $cid = $a->clientId();
        if ($cid === null) {
            return ['sources' => [], 'keywords' => []];
        }
        return [
            'sources' => array_map(static fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'type' => (string) $r['type'], 'url' => (string) $r['url'], 'topics' => (string) $r['topics'],
                'active' => (bool) $r['active'], 'is_new' => (bool) $r['is_new'], 'note' => (string) $r['note'], 'articles' => (int) $r['articles'], 'last_month' => (string) ($r['last_month'] ?? '')],
                $this->db->all('SELECT s.*, (SELECT COUNT(*) FROM articles a WHERE a.client_id = s.client_id AND a.source_name = s.name) AS articles,
                    (SELECT MAX(a.month) FROM articles a WHERE a.client_id = s.client_id AND a.source_name = s.name) AS last_month
                    FROM sources s WHERE s.client_id = ? ORDER BY s.active DESC, s.name', [$cid])),
            'keywords' => array_map(static fn ($r) => ['id' => (int) $r['id'], 'keyword' => (string) $r['keyword'], 'topic' => (string) $r['topic'], 'is_exclude' => (bool) $r['is_exclude'], 'language' => (string) $r['language']],
                $this->db->all('SELECT * FROM keywords WHERE client_id = ? ORDER BY is_exclude, topic, keyword', [$cid])),
        ];
    }

    /** @param array<string,mixed> $in @return array<string,mixed> */
    public function saveSource(Access $a, ?int $id, array $in): array
    {
        $a->require('sources');
        $cid = $this->client($a);
        $url = trim((string) ($in['url'] ?? ''));
        if (!preg_match('~^https?://[^\s/]+~i', $url)) {
            throw new \InvalidArgumentException('A forrás címe http(s)://-sel kezdődő webcím legyen (a hírlista vagy az RSS címe).');
        }
        $f = [
            'name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 190),
            'type' => mb_substr(trim((string) ($in['type'] ?? '')), 0, 100),
            'url' => mb_substr($url, 0, 500),
            'url_hash' => Url::hash($url),
            'topics' => mb_substr(trim((string) ($in['topics'] ?? '')), 0, 500),
            'active' => !empty($in['active']) ? 1 : 0,
            'is_new' => !empty($in['is_new']) ? 1 : 0,
            'note' => mb_substr(trim((string) ($in['note'] ?? '')), 0, 500),
            'updated_at' => Database::now(),
        ];
        if ($f['name'] === '') {
            $f['name'] = (string) preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));
        }
        $dup = $this->db->value('SELECT id FROM sources WHERE client_id = ? AND url_hash = ?' . ($id !== null ? ' AND id <> ?' : ''), $id !== null ? [$cid, $f['url_hash'], $id] : [$cid, $f['url_hash']]);
        if ($dup) {
            throw new \InvalidArgumentException('Ez a forrás már szerepel a listában.');
        }
        if ($id === null) {
            $id = $this->db->insert('sources', $f + ['client_id' => $cid]);
            $this->audit->add($cid, $a->userId(), 'Forrás felvéve', $f['name'] . ' · ' . $url);
        } else {
            $this->db->one('SELECT id FROM sources WHERE id = ? AND client_id = ?', [$id, $cid]) ?? throw new \InvalidArgumentException('Nincs ilyen forrás.');
            $this->db->run('UPDATE sources SET ' . implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($f))) . ' WHERE id = ? AND client_id = ?', [...array_values($f), $id, $cid]);
            $this->audit->add($cid, $a->userId(), 'Forrás módosítva', $f['name'] . ($f['active'] ? '' : ' (szünetel)'));
        }
        return $this->all($a);
    }

    /** @return array<string,mixed> */
    public function deleteSource(Access $a, int $id): array
    {
        $a->require('sources');
        $cid = $this->client($a);
        $s = $this->db->one('SELECT name FROM sources WHERE id = ? AND client_id = ?', [$id, $cid]) ?? throw new \InvalidArgumentException('Nincs ilyen forrás.');
        $this->db->run('DELETE FROM sources WHERE id = ? AND client_id = ?', [$id, $cid]);
        $this->audit->add($cid, $a->userId(), 'Forrás törölve', (string) $s['name'] . ' (a korábbi cikkei megmaradnak)');
        return $this->all($a);
    }

    /** Egy forrás próbagyűjtése (semmit nem ment): mód, találatszám, a legutóbbi néhány cím. @return array<string,mixed> */
    public function test(Access $a, string $url, string $type): array
    {
        $a->require('sources');
        if (!preg_match('~^https?://~i', trim($url))) {
            throw new \InvalidArgumentException('Adjon meg egy http(s)://-sel kezdődő címet.');
        }
        $r = $this->collector->collect(trim($url), $type);
        $items = array_slice($r['items'], 0, 5);
        return ['method' => $r['method'], 'error' => $r['error'], 'count' => count($r['items']),
            'items' => array_map(static fn ($i) => ['title' => $i->title, 'date' => $i->date, 'url' => $i->url], $items)];
    }

    /** @param array<string,mixed> $in @return array<string,mixed> */
    public function saveKeyword(Access $a, ?int $id, array $in): array
    {
        $a->require('sources');
        $cid = $this->client($a);
        $f = ['keyword' => mb_substr(trim((string) ($in['keyword'] ?? '')), 0, 190), 'topic' => mb_substr(trim((string) ($in['topic'] ?? '')), 0, 190),
            'is_exclude' => !empty($in['is_exclude']) ? 1 : 0, 'language' => mb_substr(strtolower(trim((string) ($in['language'] ?? ''))), 0, 5), 'updated_at' => Database::now()];
        if ($f['keyword'] === '') {
            throw new \InvalidArgumentException('Adja meg a kulcsszót.');
        }
        $dup = $this->db->value('SELECT id FROM keywords WHERE client_id = ? AND keyword = ? AND language = ?' . ($id !== null ? ' AND id <> ?' : ''), $id !== null ? [$cid, $f['keyword'], $f['language'], $id] : [$cid, $f['keyword'], $f['language']]);
        if ($dup) {
            throw new \InvalidArgumentException('Ez a kulcsszó már szerepel a listában.');
        }
        if ($id === null) {
            $this->db->insert('keywords', $f + ['client_id' => $cid]);
        } else {
            $this->db->one('SELECT id FROM keywords WHERE id = ? AND client_id = ?', [$id, $cid]) ?? throw new \InvalidArgumentException('Nincs ilyen kulcsszó.');
            $this->db->run('UPDATE keywords SET ' . implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($f))) . ' WHERE id = ? AND client_id = ?', [...array_values($f), $id, $cid]);
        }
        $this->audit->add($cid, $a->userId(), $id === null ? 'Kulcsszó felvéve' : 'Kulcsszó módosítva', $f['keyword'] . ($f['is_exclude'] ? ' (kizáró)' : ''));
        return $this->all($a);
    }

    /** @return array<string,mixed> */
    public function deleteKeyword(Access $a, int $id): array
    {
        $a->require('sources');
        $cid = $this->client($a);
        $k = $this->db->one('SELECT keyword FROM keywords WHERE id = ? AND client_id = ?', [$id, $cid]) ?? throw new \InvalidArgumentException('Nincs ilyen kulcsszó.');
        $this->db->run('DELETE FROM keywords WHERE id = ? AND client_id = ?', [$id, $cid]);
        $this->audit->add($cid, $a->userId(), 'Kulcsszó törölve', (string) $k['keyword']);
        return $this->all($a);
    }

    private function client(Access $a): int
    {
        return $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
    }
}

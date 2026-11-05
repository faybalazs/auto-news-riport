<?php
declare(strict_types=1);

namespace Xperion\Web;

use Xperion\Auth\Access;
use Xperion\Db\Database;

/**
 * Globális kereső a felső sávban (⌘K): az aktuális ügyfél cikkei, posztjai, posztötletei, forrásai és a
 * menüpontok között, a felhasználó jogosultsága szerint. Csak olvas; találatcsoportonként legfeljebb 6 elem.
 */
final class GlobalSearch
{
    public const PER_GROUP = 6;

    /** Menüpontok: [cím, oldal, jog (null = mindenki), kulcsszavak]. */
    private const PAGES = [
        ['Áttekintés', 'attekintes', null, 'kezdőlap teendők összefoglaló'],
        ['Havi cikkek', 'cikkek', null, 'döntés mehet feltöltés publikálva'],
        ['LinkedIn-posztok', 'posztok', null, 'poszt jóváhagyás'],
        ['Posztötletek', 'otletek', 'ideas', 'ötlet új ötlet'],
        ['Posztszabályok', 'szabalyok', 'rules', 'szabály hangnem'],
        ['Források és kulcsszavak', 'forrasok', 'sources', 'forrás kulcsszó rss új-forrás-figyelő javasolt'],
        ['Felhasználók', 'felhasznalok', 'users', 'meghívás szerepkör jogosultság'],
        ['Ügyfelek', 'ugyfelek', 'ops', 'ügyfél adatátvétel beállítás'],
        ['Vezérlőpult', 'vezerlo', 'ops', 'futás havi e-mail cron feltöltés'],
        ['Napló', 'naplo', 'log', 'művelet futás csv'],
        ['Profil', 'profil', null, 'jelszó fiók kilépés'],
    ];

    public function __construct(private Database $db)
    {
    }

    /**
     * @return list<array{group:string, items:list<array{title:string, sub:string, href:string}>}>
     */
    public function search(Access $a, string $q): array
    {
        $q = trim(preg_replace('/\s+/u', ' ', $q) ?? '');
        if (mb_strlen($q) < 2) {
            return [];
        }
        $q = mb_substr($q, 0, 80);
        $like = '%' . strtr($q, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
        $out = [];

        $norm = static fn (string $s): string => mb_strtolower($s);
        $pages = [];
        foreach (self::PAGES as [$t, $p, $perm, $kw]) {
            if (($perm === null || $a->can($perm)) && str_contains($norm($t . ' ' . $kw), $norm($q))) {
                $pages[] = ['title' => $t, 'sub' => 'Menüpont', 'href' => 'index.php?p=' . $p];
            }
        }
        if ($pages) {
            $out[] = ['group' => 'Oldalak', 'items' => array_slice($pages, 0, self::PER_GROUP)];
        }

        $cid = $a->clientId();
        if ($cid === null) {
            return $out;
        }
        $rows = $this->db->all('SELECT title, source_name, month, url FROM articles WHERE client_id = ? AND (title LIKE ? OR description LIKE ? OR source_name LIKE ?) ORDER BY month DESC, no LIMIT ' . self::PER_GROUP,
            [$cid, $like, $like, $like]);
        if ($rows) {
            $out[] = ['group' => 'Cikkek', 'items' => array_map(static fn ($r) => ['title' => (string) $r['title'],
                'sub' => $r['source_name'] . ' · ' . View::monthHu(new \DateTimeImmutable(sprintf('20%s-%s-01', substr((string) $r['month'], 0, 2), substr((string) $r['month'], 2, 2)))),
                'href' => 'index.php?p=cikkek&honap=' . rawurlencode((string) $r['month']) . '&q=' . rawurlencode((string) $r['title'])], $rows)];
        }
        $rows = $this->db->all('SELECT code, topic, opening FROM posts WHERE client_id = ? AND (code LIKE ? OR topic LIKE ? OR opening LIKE ? OR context LIKE ?) ORDER BY code DESC LIMIT ' . self::PER_GROUP,
            [$cid, $like, $like, $like, $like]);
        if ($rows) {
            $out[] = ['group' => 'LinkedIn-posztok', 'items' => array_map(static fn ($r) => ['title' => $r['code'] . ' · ' . ($r['topic'] !== '' ? $r['topic'] : '(téma nélkül)'),
                'sub' => mb_strimwidth((string) $r['opening'], 0, 90, '…'), 'href' => 'index.php?p=poszt&kod=' . rawurlencode((string) $r['code'])], $rows)];
        }
        if ($a->can('ideas')) {
            $rows = $this->db->all('SELECT code, topic, submitter FROM post_ideas WHERE client_id = ? AND (code LIKE ? OR topic LIKE ? OR message LIKE ?) ORDER BY code DESC LIMIT ' . self::PER_GROUP,
                [$cid, $like, $like, $like]);
            if ($rows) {
                $out[] = ['group' => 'Posztötletek', 'items' => array_map(static fn ($r) => ['title' => $r['code'] . ' · ' . $r['topic'], 'sub' => 'Beküldte: ' . $r['submitter'],
                    'href' => 'index.php?p=otlet&kod=' . rawurlencode((string) $r['code'])], $rows)];
            }
        }
        if ($a->can('sources')) {
            $rows = $this->db->all('SELECT name, url FROM sources WHERE client_id = ? AND (name LIKE ? OR url LIKE ? OR topics LIKE ?) ORDER BY name LIMIT ' . self::PER_GROUP,
                [$cid, $like, $like, $like]);
            if ($rows) {
                $out[] = ['group' => 'Források', 'items' => array_map(static fn ($r) => ['title' => (string) $r['name'], 'sub' => (string) $r['url'],
                    'href' => 'index.php?p=forrasok&q=' . rawurlencode((string) $r['name'])], $rows)];
            }
        }
        return $out;
    }
}

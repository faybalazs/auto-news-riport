<?php
declare(strict_types=1);

namespace Xperion\Monthly;

use Xperion\Auth\Access;
use Xperion\Db\Database;

/**
 * Az Áttekintés adatai az aktuális ügyfélre: hónapok, a választott hónap számai az előző hónaphoz
 * és az utolsó hat hónaphoz mérve, döntések megoszlása, cikkek forrásonként, összefoglaló, teendők.
 */
final class OverviewService
{
    /** A döntések megoszlásának kategóriái (egymást kizárók): kulcs → felirat. */
    public const DECISION_PARTS = ['mehet' => 'Mehet', 'li' => 'LinkedIn poszt', 'nem' => 'Nem kell', 'open' => 'Nincs döntés', 'notrec' => 'Nem javasolt'];

    public function __construct(private Database $db, private ArticleService $articles)
    {
    }

    /**
     * @return array{months:list<string>, month:string, stats:array<string,int>|null, prev:array<string,int>|null,
     *   series:array<string,list<int>>, sources:list<array{name:string, n:int}>, report:array<string,mixed>|null, todo:array<string,mixed>}
     */
    public function build(Access $a, string $month = ''): array
    {
        $cid = $a->clientId();
        $out = ['months' => [], 'month' => '', 'stats' => null, 'prev' => null, 'series' => [], 'sources' => [], 'report' => null, 'todo' => $this->todo($a)];
        if ($cid === null) {
            return $out;
        }
        $byMonth = [];
        foreach ($this->db->all(
            "SELECT month, COUNT(*) AS n,
                SUM(recommendation = 'Erősen javasolt') AS strong,
                SUM(uploaded = 1) AS up,
                SUM(decision = 'mehet' OR (decision = '' AND uploaded = 1)) AS mehet,
                SUM(decision = 'linkedin') AS li,
                SUM(decision = 'nemkell') AS nem,
                SUM(decision = '' AND uploaded = 0 AND recommendation NOT LIKE 'Nem javasolt%') AS open,
                SUM(decision = '' AND uploaded = 0 AND recommendation LIKE 'Nem javasolt%') AS notrec
             FROM articles WHERE client_id = ? GROUP BY month", [$cid]) as $r) {
            $byMonth[(string) $r['month']] = array_map('intval', array_diff_key($r, ['month' => 1]));
        }
        $months = array_keys($byMonth);
        foreach ($this->db->all('SELECT month FROM month_reports WHERE client_id = ?', [$cid]) as $r) {
            $months[] = (string) $r['month'];
        }
        $months = array_values(array_unique(array_map('strval', $months)));
        rsort($months, SORT_STRING);
        $out['months'] = $months;
        if (!$months) {
            return $out;
        }
        $month = in_array($month, $months, true) ? $month : $months[0];
        $empty = array_fill_keys(['n', 'strong', 'up', 'mehet', 'li', 'nem', 'open', 'notrec'], 0);
        $out['month'] = $month;
        $out['stats'] = $byMonth[$month] ?? $empty;
        $prev = self::shift($month, -1);
        $out['prev'] = $byMonth[$prev] ?? null;
        // Trend: az utolsó hat naptári hónap, amelyikben van adat (a választott hónappal bezárólag).
        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = self::shift($month, -$i);
            if (isset($byMonth[$m])) {
                foreach ($byMonth[$m] as $k => $v) {
                    $series[$k][] = $v;
                }
            }
        }
        $out['series'] = $series;
        $out['sources'] = array_map(static fn ($r) => ['name' => (string) $r['name'] !== '' ? (string) $r['name'] : 'Ismeretlen forrás', 'n' => (int) $r['n']], $this->db->all(
            'SELECT source_name AS name, COUNT(*) AS n FROM articles WHERE client_id = ? AND month = ? GROUP BY source_name ORDER BY n DESC, source_name', [$cid, $month]));
        $out['report'] = $this->articles->report($a, $month);
        return $out;
    }

    /**
     * Teendők számai az aktuális ügyfélre (minden hónapra), szerepkör szerint szűrve.
     *
     * @return array<string,mixed>
     */
    private function todo(Access $a): array
    {
        $cid = $a->clientId();
        $t = ['decide' => 0, 'decideMonth' => '', 'upload' => 0, 'postsClient' => [], 'postsApproved' => [], 'postsDraft' => [], 'ideas' => 0, 'missingRun' => '', 'members' => 0, 'mail' => [], 'uploadErr' => 0];
        if ($cid === null) {
            return $t;
        }
        $t['members'] = (int) $this->db->value('SELECT COUNT(*) FROM memberships WHERE client_id = ?', [$cid]);
        if ($a->can('decide')) {
            $open = $this->db->all("SELECT month, COUNT(*) AS n FROM articles WHERE client_id = ? AND decision = '' AND uploaded = 0 AND recommendation NOT LIKE 'Nem javasolt%' GROUP BY month ORDER BY month DESC", [$cid]);
            // a csempén a legutóbbi hónap száma áll (a felirat is azt a hónapot nevezi meg); a korábbi hónapok külön
            $t['decide'] = (int) ($open[0]['n'] ?? 0);
            $t['decideMonth'] = (string) ($open[0]['month'] ?? '');
            $t['decideOlder'] = array_sum(array_map(static fn ($r) => (int) $r['n'], array_slice($open, 1)));
        }
        $t['upload'] = (int) $this->db->value("SELECT COUNT(*) FROM articles WHERE client_id = ? AND decision = 'mehet' AND uploaded = 0 AND wp_state <> 'hiba'", [$cid]);
        if ($a->can('ops')) {
            $t['uploadErr'] = (int) $this->db->value("SELECT COUNT(*) FROM articles WHERE client_id = ? AND decision = 'mehet' AND uploaded = 0 AND wp_state = 'hiba'", [$cid]);
        }
        $posts = static fn (Database $db, string $status) => array_map(static fn ($r) => $r['code'] . ($r['topic'] !== '' ? ' · ' . $r['topic'] : ''), $db->all(
            'SELECT code, topic FROM posts WHERE client_id = ? AND status = ? ORDER BY code DESC LIMIT 5', [$cid, $status]));
        if ($a->can('approve')) {
            $t['postsClient'] = $posts($this->db, 'ugyfelnel');
        }
        if ($a->can('publish')) {
            $t['postsApproved'] = $posts($this->db, 'jovahagyva');
        }
        if ($a->can('ops')) {
            $t['postsDraft'] = $posts($this->db, 'tervezet');
            $t['mail'] = array_map(static fn ($r) => ['month' => (string) $r['month'], 'subject' => (string) $r['subject'], 'error' => $r['status'] === 'hiba'],
                $this->db->all("SELECT month, subject, status FROM month_emails WHERE client_id = ? AND status IN ('jovahagyasra_var','hiba') ORDER BY month DESC", [$cid]));
            // Az előző naptári hónap havi futása (csak aktív ügyfélnél, ha még nincs listája).
            $prev = (new \DateTimeImmutable('first day of previous month', new \DateTimeZone('Europe/Budapest')))->format('ym');
            $client = $a->client();
            if (($client['status'] ?? '') === 'aktiv'
                && (int) $this->db->value('SELECT COUNT(*) FROM month_reports WHERE client_id = ? AND month = ?', [$cid, $prev]) === 0
                && (int) $this->db->value('SELECT COUNT(*) FROM articles WHERE client_id = ? AND month = ?', [$cid, $prev]) === 0) {
                $t['missingRun'] = $prev;
            }
        }
        if ($a->can('ideas')) {
            $t['ideas'] = (int) $this->db->value("SELECT COUNT(*) FROM post_ideas WHERE client_id = ? AND status = 'uj'", [$cid]);
        }
        return $t;
    }

    /** „yymm” hónap eltolása. */
    public static function shift(string $ym, int $by): string
    {
        $d = new \DateTimeImmutable(sprintf('20%s-%s-01', substr($ym, 0, 2), substr($ym, 2, 2)));
        return $d->modify(($by >= 0 ? '+' : '') . $by . ' month')->format('ym');
    }
}

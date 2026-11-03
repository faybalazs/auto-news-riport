<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\TokenService;
use Xperion\Auth\UserAdmin;
use Xperion\Db\Database;
use Xperion\Import\Url;
use Xperion\Monthly\ArticleService;
use Xperion\Monthly\OverviewService;
use Xperion\Tests\DbTestCase;

final class OverviewServiceTest extends DbTestCase
{
    private OverviewService $svc;
    private UserAdmin $users;
    private int $op;
    private int $x;
    private int $other;

    protected function setUp(): void
    {
        parent::setUp();
        $audit = new AuditLog($this->db);
        $this->users = new UserAdmin($this->db, new TokenService($this->db), $audit);
        $clients = new ClientAdmin($this->db, $audit);
        $this->op = $this->users->createFirstOperator('B', 'b@pelda.hu', 'eleg-hosszu-jelszo');
        $this->x = $clients->create(Access::forUser($this->db, $this->op, null), 'Xperion', '');
        $this->other = $clients->create(Access::forUser($this->db, $this->op, null), 'Másik', '');
        $this->svc = new OverviewService($this->db, new ArticleService($this->db, $audit));
    }

    private function art(int $c, string $m, string $src, string $rec, string $dec = '', int $up = 0): void
    {
        static $i = 0;
        $u = 'https://a.test/' . (++$i);
        $now = Database::now();
        $this->db->insert('articles', ['client_id' => $c, 'month' => $m, 'source_name' => $src, 'title' => 'T' . $i, 'url' => $u, 'url_hash' => Url::hash($u),
            'recommendation' => $rec, 'decision' => $dec, 'uploaded' => $up, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function testStatsDeltaSeriesSourcesAndClientIsolation(): void
    {
        $this->art($this->x, '2609', 'Vemco', 'Javasolt', 'mehet', 1);
        $this->art($this->x, '2610', 'Vemco', 'Erősen javasolt', 'mehet');
        $this->art($this->x, '2610', 'Vemco', 'Erősen javasolt', 'linkedin');
        $this->art($this->x, '2610', 'Acrelec', 'Javasolt');
        $this->art($this->x, '2610', 'Acrelec', 'Nem javasolt');
        $this->art($this->x, '2610', 'Qmatic', 'Javasolt', 'nemkell');
        $this->art($this->other, '2611', 'Más', 'Javasolt');
        $this->db->insert('month_reports', ['client_id' => $this->x, 'month' => '2610', 'summary_rows' => '[{"label":"Cikkek száma","value":"5 db"}]', 'details' => 'Rövid.', 'stats' => '{}', 'model' => 'm', 'created_at' => Database::now()]);

        $d = $this->svc->build(Access::forUser($this->db, $this->op, $this->x));
        self::assertSame(['2610', '2609'], $d['months'], 'csak a saját ügyfél hónapjai, a legfrissebb elöl');
        self::assertSame('2610', $d['month']);
        self::assertSame(['n' => 5, 'strong' => 2, 'up' => 0, 'mehet' => 1, 'li' => 1, 'nem' => 1, 'open' => 1, 'notrec' => 1], $d['stats']);
        $parts = array_sum(array_map(fn ($k) => $d['stats'][$k], array_keys(OverviewService::DECISION_PARTS)));
        self::assertSame(5, $parts, 'a megoszlás kategóriái lefedik a hónap cikkeit');
        self::assertSame(1, $d['prev']['mehet'], 'a feltöltött, döntés nélküli átvett cikk Mehetnek számít');
        self::assertSame([1, 5], $d['series']['n']);
        self::assertSame([['name' => 'Acrelec', 'n' => 2], ['name' => 'Vemco', 'n' => 2], ['name' => 'Qmatic', 'n' => 1]], $d['sources']);
        self::assertSame('Rövid.', $d['report']['details']);
        self::assertSame(1, $d['todo']['decide']);
        self::assertSame('2610', $d['todo']['decideMonth']);
        self::assertSame(0, $d['todo']['decideOlder']);
        // régebbi hónap nyitott cikkei nem adódnak a csempén megnevezett hónap számához
        $this->art($this->x, '2608', 'Vemco', 'Javasolt');
        $this->art($this->x, '2608', 'Vemco', 'Erősen javasolt');
        $t = $this->svc->build(Access::forUser($this->db, $this->op, $this->x))['todo'];
        self::assertSame([1, '2610', 2], [$t['decide'], $t['decideMonth'], $t['decideOlder']]);
        $this->db->run("DELETE FROM articles WHERE month = '2608'");
        self::assertSame(1, $d['todo']['upload']);

        $old = $this->svc->build(Access::forUser($this->db, $this->op, $this->x), '2609');
        self::assertNull($old['prev'], 'a 2608-as hónapról nincs adat');
        self::assertNull($old['report']);
        self::assertSame('2610', $this->svc->build(Access::forUser($this->db, $this->op, $this->x), '2611')['month'], 'más ügyfél hónapja nem választható');
    }

    public function testTodosFollowRoles(): void
    {
        $now = Database::now();
        $this->art($this->x, '2610', 'Vemco', 'Javasolt');
        foreach ([['LI-2610-01', 'ugyfelnel'], ['LI-2610-02', 'jovahagyva'], ['LI-2610-03', 'tervezet']] as [$c, $s]) {
            $this->db->insert('posts', ['client_id' => $this->x, 'code' => $c, 'month' => '2610', 'status' => $s, 'topic' => 'Téma', 'created_at' => $now, 'updated_at' => $now]);
        }
        $this->db->insert('post_ideas', ['client_id' => $this->x, 'code' => 'O-1', 'status' => 'uj', 'created_at' => $now, 'updated_at' => $now]);

        $emi = $this->users->invite(Access::forUser($this->db, $this->op, $this->x), 'Emi', 'e@pelda.hu', $this->x, 'tartalom');
        $this->users->setPasswordWithToken((string) $emi['token'], TokenService::INVITE, 'emi-jelszava-1');
        $t = $this->svc->build(Access::forUser($this->db, $emi['user_id'], null))['todo'];
        self::assertSame(0, $t['decide'], 'a tartalomkezelő nem dönt');
        self::assertSame([], $t['postsClient']);
        self::assertSame(['LI-2610-02 · Téma'], $t['postsApproved']);
        self::assertSame([], $t['postsDraft']);
        self::assertSame(1, $t['ideas']);

        $t = $this->svc->build(Access::forUser($this->db, $this->op, $this->x))['todo'];
        self::assertSame(1, $t['decide']);
        self::assertSame(['LI-2610-01 · Téma'], $t['postsClient']);
        self::assertSame(['LI-2610-03 · Téma'], $t['postsDraft']);
        self::assertSame(1, $t['members']);
    }

    public function testShift(): void
    {
        self::assertSame('2512', OverviewService::shift('2601', -1));
        self::assertSame('2601', OverviewService::shift('2512', 1));
    }
}

<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use Xperion\Auth\Access;
use Xperion\Auth\AccessDenied;
use Xperion\Auth\AuditLog;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\TokenService;
use Xperion\Auth\UserAdmin;
use Xperion\Claude\ClaudeClient;
use Xperion\Collect\CollectService;
use Xperion\Collect\Collector;
use Xperion\Db\Database;
use Xperion\Import\Url;
use Xperion\Log\LocalLog;
use Xperion\Monthly\ArticleService;
use Xperion\Monthly\MonthlyService;
use Xperion\Tests\DbTestCase;
use Xperion\Tests\FakeClaude;
use Xperion\Tests\FakeWeb;

final class MonthlyServiceTest extends DbTestCase
{
    private FakeClaude $claude;
    /** @var list<array{0:int,1:string}> */
    private array $hookCalls = [];
    private bool $hookFail = false;
    private MonthlyService $svc;
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
        $now = Database::now();
        foreach ([[$this->x, 'Vemco', 'https://src.test/rss.xml', 0], [$this->x, 'Acrelec', 'https://new.test/rss.xml', 1]] as [$c, $n, $u, $new]) {
            $this->db->insert('sources', ['client_id' => $c, 'name' => $n, 'url' => $u, 'url_hash' => Url::hash($u), 'active' => 1, 'is_new' => $new, 'updated_at' => $now]);
        }
        $this->db->insert('post_rules', ['client_id' => $this->x, 'sort' => 1, 'name' => 'Cég', 'value' => 'Xperion Ltd. ügyféltér-analitika', 'updated_at' => $now]);
        $rss = fn (string $items) => '<?xml version="1.0"?><rss version="2.0"><channel>' . $items . '</channel></rss>';
        $item = fn (string $t, string $u, string $d) => "<item><title>$t</title><link>$u</link><pubDate>$d</pubDate></item>";
        $web = new FakeWeb([
            'https://src.test/rss.xml' => $rss($item('How a bank cut wait times', 'https://src.test/a', 'Thu, 02 Oct 2026 08:00:00 +0000') . $item('Company plans IPO', 'https://src.test/b', 'Fri, 10 Oct 2026 08:00:00 +0000') . $item('Old', 'https://src.test/old', 'Mon, 01 Sep 2026 08:00:00 +0000')),
            'https://new.test/rss.xml' => $rss($item('Kiosk ordering lifts basket size', 'https://new.test/k', 'Wed, 15 Oct 2026 08:00:00 +0000')),
        ]);
        $dir = sys_get_temp_dir() . '/xp-mon-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $log = new LocalLog($dir . '/n.jsonl');
        $this->claude = new FakeClaude();
        $this->svc = new MonthlyService($this->db, new CollectService($this->db, new Collector($web), $log, $dir . '/l.lock'), new ClaudeClient($this->claude, 'k'), $log, $audit, $dir . '/l.lock',
            function (int $c, string $m): void {
                $this->hookCalls[] = [$c, $m];
                if ($this->hookFail) {
                    throw new \RuntimeException('SMTP nincs');
                }
            });
    }

    private function op(?int $c = null): Access
    {
        return Access::forUser($this->db, $this->op, $c ?? $this->x);
    }

    public function testDryRunEvaluatesButWritesNothing(): void
    {
        $r = $this->svc->runForAccess($this->op(), '2610', true);
        self::assertTrue($r->ok, $r->summary());
        self::assertCount(3, $r->data['items']);
        self::assertSame('Erősen javasolt', $r->data['items'][0]['recommendation']);
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM articles'));
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM month_reports'));
        self::assertCount(2, $this->claude->requests, 'egy értékelő (3 cikk ≤ 20) és egy összefoglaló kérés');
        self::assertStringContainsString('Xperion Ltd. ügyféltér-analitika', $this->claude->requests[0]['body']['system'][0]['text']);
        self::assertSame('claude-haiku-5-5', $this->claude->requests[0]['body']['model']);
        self::assertSame('claude-sonnet-5-5', $this->claude->requests[1]['body']['model']);
    }

    public function testLiveRunSavesListAndSummaryOnceAndUsesClientModels(): void
    {
        $this->db->insert('client_settings', ['client_id' => $this->x, 'name' => 'modell_ertekeles', 'value' => 'claude-sonnet-5-5', 'updated_at' => Database::now()]);
        $r = $this->svc->runForAccess($this->op(), '2610', false);
        self::assertTrue($r->ok, $r->summary());
        self::assertSame('claude-sonnet-5-5', $this->claude->requests[0]['body']['model'], 'az ügyfél beállítása felülírja az alapértelmezett modellt');
        $rows = $this->db->all('SELECT no, title, recommendation, description, from_new_source, imported FROM articles WHERE client_id = ? ORDER BY no', [$this->x]);
        self::assertSame(['How a bank cut wait times', 'Company plans IPO', 'Kiosk ordering lifts basket size'], array_column($rows, 'title'), 'előbb a rendes források, a végén az új forrás');
        self::assertSame([1, 2, 3], array_map('intval', array_column($rows, 'no')));
        self::assertSame('Nem javasolt', $rows[1]['recommendation']);
        self::assertSame('Magyar leírás: How a bank cut wait times', $rows[0]['description']);
        self::assertSame(1, (int) $rows[2]['from_new_source']);
        $rep = (new ArticleService($this->db, new AuditLog($this->db)))->report($this->op(), '2610');
        // tagolt összefoglaló: rögzített sorrend, csoportonként legfeljebb 3 pont, ismeretlen csoport és üres pont nélkül
        self::assertSame('Októberben a várakozás mérése volt a fő téma.', $rep['details']);
        self::assertSame(['Újdonságok', 'Tények, számok', 'Trendek'], array_column($rep['sections'], 'title'));
        self::assertCount(3, $rep['sections'][1]['points']);
        self::assertSame(['A bankok mérik a várakozást.'], $rep['sections'][2]['points']);
        self::assertSame([], $rep['summary_rows']);
        // a Claude a cikkek leírását is megkapja, statisztikát nem kér
        self::assertStringContainsString('Magyar leírás: How a bank cut wait times', $this->claude->lastSummaryInput);

        $calls = count($this->claude->requests);
        $r2 = $this->svc->runForAccess($this->op(), '2610', false);
        self::assertTrue($r2->ok);
        self::assertStringContainsString('már elkészült', $r2->summary());
        self::assertCount($calls, $this->claude->requests, 'második futás nem hív Claude-ot');
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM articles WHERE client_id = ?', [$this->other]));
    }

    public function testEmailHookRunsOnlyAfterLiveSave(): void
    {
        $dry = $this->svc->runForAccess($this->op(), '2610', true);
        self::assertSame([], $this->hookCalls);
        self::assertStringContainsString('havi e-mail is elkészülne', implode("\n", array_column($dry->lines, 'text')));
        $this->hookFail = true;
        $r = $this->svc->runForAccess($this->op(), '2610', false);
        self::assertTrue($r->ok, 'az e-mail hibája nem bontja meg a mentett havi listát');
        self::assertSame([[$this->x, '2610']], $this->hookCalls);
        self::assertStringContainsString('a havi e-mail nem: SMTP nincs', implode("\n", array_column($r->lines, 'text')));
        self::assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM month_reports WHERE client_id = ?', [$this->x]));
        $this->svc->runForAccess($this->op(), '2610', false);
        self::assertCount(1, $this->hookCalls, 'meglévő hónapnál nem ír új levelet');
    }

    public function testMissingKeyFailsFast(): void
    {
        $dir = sys_get_temp_dir() . '/xp-mk-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $log = new LocalLog($dir . '/n.jsonl');
        $web = new FakeWeb([]);
        $svc = new MonthlyService($this->db, new CollectService($this->db, new Collector($web), $log, $dir . '/l.lock'), new ClaudeClient($this->claude, ''), $log, new AuditLog($this->db), $dir . '/l.lock');
        $r = $svc->runForAccess($this->op(), '2610', true);
        self::assertFalse($r->ok);
        self::assertStringContainsString('Claude API-kulcs', $r->summary());
        self::assertSame([], $web->requested, 'gyűjtés sem indul');
    }

    public function testImportedMonthCountsAsExisting(): void
    {
        $now = Database::now();
        $this->db->insert('articles', ['client_id' => $this->x, 'month' => '2610', 'url' => 'https://imp.test', 'url_hash' => sha1('imp'), 'imported' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $r = $this->svc->runForAccess($this->op(), '2610', false);
        self::assertTrue($r->ok);
        self::assertTrue($r->data['exists']);
        self::assertSame([], $this->claude->requests);
    }

    public function testIncompleteEvaluationSavesNothing(): void
    {
        $this->claude->dropLast = true;
        $r = $this->svc->runForAccess($this->op(), '2610', false);
        self::assertFalse($r->ok);
        self::assertStringContainsString('hiányos', $r->summary());
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM articles'));
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM month_reports'));
    }

    public function testDecisionsAreScopedAndPermissioned(): void
    {
        $this->svc->runForAccess($this->op(), '2610', false);
        $now = Database::now();
        $foreign = $this->db->insert('articles', ['client_id' => $this->other, 'month' => '2610', 'url' => 'https://f.test', 'url_hash' => sha1('f'), 'created_at' => $now, 'updated_at' => $now]);
        $ids = array_map('intval', array_column($this->db->all('SELECT id FROM articles WHERE client_id = ? ORDER BY no', [$this->x]), 'id'));
        $this->db->run('UPDATE articles SET uploaded = 1 WHERE id = ?', [$ids[2]]);
        $art = new ArticleService($this->db, new AuditLog($this->db));

        $inv = $this->users->invite($this->op(), 'Attila', 'a@pelda.hu', $this->x, 'ugyfeladmin');
        $this->users->setPasswordWithToken((string) $inv['token'], TokenService::INVITE, 'attila-jelszo-1');
        $attila = Access::forUser($this->db, $inv['user_id'], null);
        self::assertSame(2, $art->decide($attila, [['id' => $ids[0], 'decision' => 'mehet'], ['id' => $ids[1], 'decision' => 'nemkell'], ['id' => $ids[2], 'decision' => 'linkedin']]), 'a feltöltött cikk kimarad');
        self::assertSame(['mehet', 'nemkell', ''], array_column($this->db->all('SELECT decision FROM articles WHERE client_id = ? ORDER BY no', [$this->x]), 'decision'));
        self::assertSame([['month' => '2610', 'total' => 3, 'open' => 0]], $art->months($attila));

        try {
            $art->decide($attila, [['id' => $foreign, 'decision' => 'mehet']]);
            self::fail('idegen ügyfél cikke');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Nincs ilyen cikk.', $e->getMessage());
        }
        self::assertSame('', $this->db->value('SELECT decision FROM articles WHERE id = ?', [$foreign]));
        try {
            $art->decide($attila, [['id' => $ids[0], 'decision' => 'barmi']]);
            self::fail('ismeretlen döntés');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
        $emi = $this->users->invite($this->op(), 'Emi', 'e@pelda.hu', $this->x, 'tartalom');
        $this->users->setPasswordWithToken((string) $emi['token'], TokenService::INVITE, 'emi-jelszava-1');
        $this->expectException(AccessDenied::class);
        $art->decide(Access::forUser($this->db, $emi['user_id'], null), [['id' => $ids[0], 'decision' => '']]);
    }

    public function testSummaryCanBeRegeneratedFromSavedArticles(): void
    {
        $this->svc->runForAccess($this->op(), '2610', false);
        $this->db->run("UPDATE month_reports SET sections = NULL, details = 'Régi.', summary_rows = '[{\"label\":\"Cikkek száma\",\"value\":\"3\"}]' WHERE client_id = ?", [$this->x]);
        $this->db->run("UPDATE articles SET decision = 'mehet' WHERE client_id = ? AND no = 1", [$this->x]);
        $rep = $this->svc->regenerateSummary($this->op(), '2610');
        self::assertSame('Októberben a várakozás mérése volt a fő téma.', $rep['details']);
        self::assertCount(3, $rep['sections']);
        self::assertNotSame('', $rep['updated_at']);
        self::assertStringContainsString('"decision": "mehet"', $this->claude->lastSummaryInput, 'a mostani döntéseket is megkapja');
        self::assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM audit_log WHERE action = 'Vezetői összefoglaló újragenerálva'"));
        // a Sheetből átvett, összefoglaló nélküli hónaphoz is elkészíthető
        $this->db->run('DELETE FROM month_reports WHERE client_id = ?', [$this->x]);
        self::assertCount(3, $this->svc->regenerateSummary($this->op(), '2610')['sections']);
        // üres hónap: érthető hiba
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->regenerateSummary($this->op(), '2601');
    }
}

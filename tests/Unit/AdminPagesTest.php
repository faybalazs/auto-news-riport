<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use Xperion\Auth\Access;
use Xperion\Auth\AccessDenied;
use Xperion\Auth\AuditLog;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\TokenService;
use Xperion\Auth\UserAdmin;
use Xperion\Collect\Collector;
use Xperion\Collect\SourceService;
use Xperion\Db\Database;
use Xperion\Log\LocalLog;
use Xperion\Log\LogEntry;
use Xperion\Task\RunResult;
use Xperion\Task\Runner;
use Xperion\Task\Task;
use Xperion\Task\TaskContext;
use Xperion\Tests\DbTestCase;
use Xperion\Tests\FakeWeb;

final class AdminPagesTest extends DbTestCase
{
    private UserAdmin $users;
    private ClientAdmin $clients;
    private AuditLog $audit;
    private SourceService $sources;
    private int $op;
    private int $x;
    private int $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->audit = new AuditLog($this->db);
        $this->users = new UserAdmin($this->db, new TokenService($this->db), $this->audit);
        $this->clients = new ClientAdmin($this->db, $this->audit);
        $this->op = $this->users->createFirstOperator('Balázs', 'b@pelda.hu', 'eleg-hosszu-jelszo');
        $this->x = $this->clients->create(Access::forUser($this->db, $this->op, null), 'Xperion', 'xperion.hu');
        $this->other = $this->clients->create(Access::forUser($this->db, $this->op, null), 'Másik', '');
        $web = new FakeWeb(['https://src.test/rss.xml' => '<?xml version="1.0"?><rss version="2.0"><channel><item><title>Kiosk news</title><link>https://src.test/a</link><pubDate>Thu, 02 Oct 2026 08:00:00 +0000</pubDate></item></channel></rss>']);
        $this->sources = new SourceService($this->db, $this->audit, new Collector($web));
    }

    private function op(?int $c = null): Access
    {
        return Access::forUser($this->db, $this->op, $c ?? $this->x);
    }

    private function member(string $email, string $role, ?int $client = null): Access
    {
        $inv = $this->users->invite($this->op(), $email, $email, $client ?? $this->x, $role);
        $this->users->setPasswordWithToken((string) $inv['token'], TokenService::INVITE, 'jelszo-' . $role . '-12');
        return Access::forUser($this->db, $inv['user_id'], null);
    }

    public function testSourcesAndKeywordsCrudScopeAndPermissions(): void
    {
        $attila = $this->member('attila@x.hu', 'ugyfeladmin');
        $d = $this->sources->saveSource($attila, null, ['url' => 'https://src.test/rss.xml', 'type' => 'RSS', 'active' => true]);
        self::assertSame('src.test', $d['sources'][0]['name'], 'név nélkül a webhely neve');
        $id = $d['sources'][0]['id'];
        try {
            $this->sources->saveSource($attila, null, ['url' => 'https://src.test/rss.xml?utm_source=x']);
            self::fail('duplikátum');
        } catch (\InvalidArgumentException) {
        }
        $d = $this->sources->saveSource($attila, $id, ['url' => 'https://src.test/rss.xml', 'name' => 'Teszt', 'active' => false, 'is_new' => true]);
        self::assertSame([false, true, 'Teszt'], [$d['sources'][0]['active'], $d['sources'][0]['is_new'], $d['sources'][0]['name']]);
        $t = $this->sources->test($attila, 'https://src.test/rss.xml', 'RSS');
        self::assertSame([1, 'Kiosk news'], [$t['count'], $t['items'][0]['title']]);

        $d = $this->sources->saveKeyword($attila, null, ['keyword' => 'IPO', 'is_exclude' => true]);
        self::assertTrue($d['keywords'][0]['is_exclude']);
        self::assertSame(['sources' => [], 'keywords' => []], $this->sources->all($this->op($this->other)), 'ügyfélhatár');
        try {
            $this->sources->deleteSource($this->op($this->other), $id);
            self::fail('más ügyfél forrása');
        } catch (\InvalidArgumentException) {
        }
        self::assertSame([], $this->sources->deleteKeyword($attila, $d['keywords'][0]['id'])['keywords']);
        self::assertSame(['Kulcsszó törölve', 'Kulcsszó felvéve', 'Forrás módosítva', 'Forrás felvéve'], array_slice(array_column($this->audit->recent($this->x), 'action'), 0, 4));
        $this->expectException(AccessDenied::class);
        $this->sources->saveSource($this->member('emi@x.hu', 'tartalom'), null, ['url' => 'https://a.hu']);
    }

    public function testLogCategoriesAndClientScope(): void
    {
        self::assertSame('Döntések', AuditLog::category('Döntés: 2 cikk → Mehet'));
        self::assertSame('Posztok', AuditLog::category('Posztötlet beküldve'));
        self::assertSame('Felhasználók', AuditLog::category('Saját jelszó módosítva'));
        self::assertSame('Cikkek', AuditLog::category('Cikk törölve'));
        self::assertSame('Feltöltés', AuditLog::category('Feltöltés visszavonva'));
        self::assertSame('Futások', AuditLog::category('Vezetői összefoglaló újragenerálva'));
        $this->audit->add($this->other, $this->op, 'Ügyfél módosítva', 'titkos');
        $this->audit->add(null, $this->op, 'Belépés');
        $mine = $this->audit->recent($this->x);
        self::assertNotContains('titkos', array_column($mine, 'details'), 'más ügyfél sora nem látszik');
        self::assertNotContains('Belépés', array_column($mine, 'action'), 'globális sor nem látszik');
        self::assertArrayHasKey('category', $mine[0]);
        $this->db->run("UPDATE audit_log SET created_at = '2023-01-01 00:00:00' WHERE action = 'Belépés'");
        self::assertSame(1, $this->audit->prune());
        $attila = $this->member('attila2@x.hu', 'ugyfeladmin');
        self::assertTrue($attila->can('log'));
        self::assertFalse($this->member('emi2@x.hu', 'tartalom')->can('log'));
    }

    public function testOnboardingAndSettings(): void
    {
        $steps = $this->clients->onboarding($this->x, null);
        self::assertCount(8, $steps);
        self::assertSame([true, false], [$steps[0]['done'], $steps[1]['done']]);
        $this->member('attila@x.hu', 'ugyfeladmin');
        $this->db->run("INSERT INTO client_settings (client_id, name, value, updated_at) VALUES (?, 'ugyfel_email', 'a@x.hu', ?)", [$this->x, Database::now()]);
        $steps = $this->clients->onboarding($this->x, ['base_url' => 'https://xperion.hu', 'user' => 'u', 'app_password' => 'p']);
        self::assertSame([true, true, true], [$steps[1]['done'], $steps[4]['done'], $steps[5]['done']]);
        $s = $this->clients->saveSettings($this->op(), ['linkedin_kovetok' => '1 240', 'modell_linkedin' => 'claude-sonnet-5-5']);
        self::assertSame(['1 240', 'claude-sonnet-5-5'], [$s['linkedin_kovetok'], $s['modell_linkedin']]);
        $this->expectException(\InvalidArgumentException::class);
        $this->clients->saveSettings($this->op(), ['modell_poszt' => 'gpt-9']);
    }

    public function testRunLogPruneAndFailureHook(): void
    {
        $dir = sys_get_temp_dir() . '/xp-adm-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $log = new LocalLog($dir . '/n.jsonl');
        $log->append(new LogEntry(new \DateTimeImmutable('-200 days'), 'havi', 'Régi', false, 'cron', true, 'x', 1));
        $log->append(new LogEntry(new \DateTimeImmutable('-1 day'), 'havi', 'Új', false, 'cron', true, 'x', 1));
        self::assertSame(1, $log->prune());
        self::assertSame(['Új'], array_map(static fn ($e) => $e->label, $log->recent()));

        $failing = new class implements Task {
            public bool $fail = true;
            public function id(): string { return 'orankenti'; }
            public function label(): string { return 'Óránkénti futás'; }
            public function readOnly(): bool { return false; }
            public function run(TaskContext $ctx): void { if ($this->fail) { $ctx->error('WordPress: 401'); } }
        };
        $runner = new Runner([$failing], static fn () => throw new \LogicException(), $log, $dir . '/l.lock');
        $seen = [];
        $runner->onFailure = function (RunResult $r, string $trigger) use (&$seen): void { $seen[] = [$r->task, $trigger, $r->lines[0]['text']]; };
        $runner->run('orankenti', false, 'cron');
        $failing->fail = false;
        $runner->run('orankenti', false, 'cron');
        self::assertSame([['orankenti', 'cron', 'WordPress: 401']], $seen, 'csak a hibás futás után');
    }
}

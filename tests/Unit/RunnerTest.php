<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Lock;
use Xperion\Log\LocalLog;
use Xperion\Sheets\SheetNames;
use Xperion\Task\Runner;
use Xperion\Task\Task;
use Xperion\Task\TaskContext;
use Xperion\Tests\SheetsFixture;

final class RunnerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/xp-run-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    private function task(callable $fn, bool $readOnly = false): Task
    {
        return new class ($fn, $readOnly) implements Task {
            public function __construct(private $fn, private bool $ro) {}
            public function id(): string { return 'proba'; }
            public function label(): string { return 'Próba'; }
            public function readOnly(): bool { return $this->ro; }
            public function run(TaskContext $ctx): void { ($this->fn)($ctx); }
        };
    }

    private function runner(Task $t, SheetsFixture $f): Runner
    {
        return new Runner([$t], static fn () => $f->client, new LocalLog($this->dir . '/naplo.jsonl'), $this->dir . '/futas.lock');
    }

    public function testExceptionIsCaughtAndLogged(): void
    {
        $f = new SheetsFixture([SheetNames::LOG]);
        $r = $this->runner($this->task(static function (): void { throw new \RuntimeException('Elromlott'); }), $f)->run('proba', false, 'admin');
        self::assertFalse($r->ok);
        self::assertSame('Elromlott', $r->summary());
        $entry = (new LocalLog($this->dir . '/naplo.jsonl'))->last('proba');
        self::assertNotNull($entry);
        self::assertFalse($entry->ok);
        // éles futás: a Napló lapra is kerül
        $append = $f->writes()[0];
        self::assertStringContainsString(rawurlencode("'Napló'!A:D"), $append['url']);
        self::assertSame('Hiba', json_decode((string) $append['body'], true)['values'][0][2]);
    }

    public function testDryRunNeverWritesToSheet(): void
    {
        $f = new SheetsFixture([SheetNames::LOG]);
        $r = $this->runner($this->task(static function (TaskContext $c): void { $c->write('valami', static function (): void { throw new \LogicException('nem futhat'); }); }), $f)->run('proba', true, 'admin');
        self::assertTrue($r->ok);
        self::assertSame('dry', $r->lines[0]['level']);
        self::assertSame([], $f->writes());
        self::assertTrue((new LocalLog($this->dir . '/naplo.jsonl'))->last('proba')->dry);
    }

    public function testReadOnlyTaskIsNotLoggedToSheet(): void
    {
        $f = new SheetsFixture([SheetNames::LOG]);
        $this->runner($this->task(static fn (TaskContext $c) => $c->info('ok'), true), $f)->run('proba', false, 'admin');
        self::assertSame([], $f->writes());
    }

    public function testConcurrentRunIsRejected(): void
    {
        $f = new SheetsFixture([]);
        $held = Lock::acquire($this->dir . '/futas.lock');
        self::assertNotNull($held);
        // flock ugyanabban a folyamatban külön leíróval is ütközik
        $ran = false;
        $r = $this->runner($this->task(static function () use (&$ran): void { $ran = true; }), $f)->run('proba', true, 'cron');
        self::assertFalse($ran);
        self::assertFalse($r->ok);
        self::assertStringContainsString('másik futás', $r->summary());
        $held->release();
    }

    public function testTextOutputForCron(): void
    {
        $f = new SheetsFixture([]);
        $r = $this->runner($this->task(static fn (TaskContext $c) => $c->warn('Figyelem')), $f)->run('proba', true, 'cli');
        self::assertStringContainsString("Próba (szárazfuttatás): OK", $r->toText());
        self::assertStringContainsString("! Figyelem", $r->toText());
    }
}

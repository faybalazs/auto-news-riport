<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Lock;
use Xperion\Log\LocalLog;
use Xperion\Task\RunResult;
use Xperion\Task\TaskContext;

final class RunStateTest extends TestCase
{
    public function testLockExposesRunningTaskStepAndBusyMessage(): void
    {
        $dir = sys_get_temp_dir() . '/xp-rs-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $file = $dir . '/futas.lock';
        self::assertNull(Lock::current($file), 'nincs futás');
        $lock = Lock::acquire($file, ['task' => 'havi', 'label' => 'Havi futás: Xperion (2610)', 'dry' => true, 'user' => 7]);
        self::assertNotNull($lock);
        $ctx = new TaskContext(true, new \DateTimeImmutable(), static fn () => throw new \LogicException());
        $ctx->onLine = [$lock, 'progress'];
        $ctx->info('Értékelés: 26 cikk');
        $cur = Lock::current($file);
        self::assertSame(['havi', 7, 'Értékelés: 26 cikk'], [$cur['task'], $cur['user'], $cur['step']]);
        self::assertGreaterThanOrEqual(0, $cur['elapsed']);
        self::assertNull(Lock::acquire($file), 'két futás nem mehet egyszerre');
        $msg = Lock::busyMessage($file);
        self::assertStringContainsString('Már fut: Havi futás: Xperion (2610) (szárazon)', $msg);
        self::assertStringContainsString('most: Értékelés: 26 cikk', $msg);
        $lock->release();
        self::assertNull(Lock::current($file), 'a futás végén üres');
        self::assertSame('2 perc 5 mp', Lock::duration(125));
    }

    public function testResultIsSavedForLaterViewing(): void
    {
        $dir = sys_get_temp_dir() . '/xp-rs-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $log = new LocalLog($dir . '/naplo.jsonl');
        $log->saveResult((new RunResult('havi', 'Havi futás', true, false, [['level' => 'error', 'text' => 'Claude: 401']], 65000, ['items' => [1, 2], 'stats' => ['n' => 2]]))->toArray(), 3, 1);
        $log->saveResult((new RunResult('gyujtes', 'Gyűjtés', true, true, [], 1000))->toArray(), 3, 1);
        $r = $log->results();
        self::assertCount(2, $r);
        $havi = array_values(array_filter($r, static fn ($x) => $x['task'] === 'havi'))[0];
        self::assertSame(['Claude: 401', 3, 65000], [$havi['lines'][0]['text'], $havi['user'], $havi['duration_ms']]);
        self::assertArrayNotHasKey('items', $havi['data'], 'a hosszú lista nem kerül bele');
    }
}

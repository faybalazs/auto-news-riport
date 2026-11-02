<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Sheets\SheetNames;
use Xperion\Task\HourlyTask;
use Xperion\Task\MonthlyTask;
use Xperion\Task\TaskContext;
use Xperion\Tests\SheetsFixture;

final class TasksTest extends TestCase
{
    private function ctx(SheetsFixture $f, bool $dry, string $now = '2026-11-01 06:00'): TaskContext
    {
        return new TaskContext($dry, new \DateTimeImmutable($now), static fn () => $f->client);
    }

    private static function texts(TaskContext $c, ?string $level = null): string
    {
        return implode("\n", array_map(static fn ($l) => $l['text'], array_filter($c->lines(), static fn ($l) => $level === null || $l['level'] === $level)));
    }

    public function testTargetMonthIsPreviousMonth(): void
    {
        self::assertSame('2610', SheetNames::monthly(MonthlyTask::targetMonth(new \DateTimeImmutable('2026-11-01 06:00'))));
        self::assertSame('2612', SheetNames::monthly(MonthlyTask::targetMonth(new \DateTimeImmutable('2027-01-31 23:59'))));
        self::assertSame('2602', SheetNames::monthly(MonthlyTask::targetMonth(new \DateTimeImmutable('2026-03-31'))));
    }

    public function testStubsDoNotWrite(): void
    {
        $f = new SheetsFixture([]);
        foreach ([new MonthlyTask(), new HourlyTask()] as $t) {
            $c = $this->ctx($f, false);
            $t->run($c);
            self::assertTrue($t->readOnly());
            self::assertFalse($c->hasErrors());
        }
        self::assertSame([], $f->http->requests);
        self::assertSame('2610', MonthlyTask::monthKey(MonthlyTask::targetMonth(new \DateTimeImmutable('2026-11-01'))));
    }
}

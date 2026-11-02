<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Http\CurlHttpClient;
use Xperion\Log\LocalLog;
use Xperion\Log\LogEntry;

final class LocalLogAndHttpTest extends TestCase
{
    public function testRecentIsNewestFirst(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'log');
        $log = new LocalLog($file);
        foreach (['havi', 'orankenti', 'havi'] as $i => $t) {
            $log->append(new LogEntry(new \DateTimeImmutable('2026-11-01 0' . $i . ':00'), $t, $t, $i === 0, 'cron', $i !== 2, "r$i", 5));
        }
        $recent = $log->recent();
        self::assertCount(3, $recent);
        self::assertSame('r2', $recent[0]->details);
        self::assertSame('r2', $log->last('havi')->details);
        self::assertNull($log->last('nincs'));
        self::assertSame(['2026.11.01. 02:00:00', 'havi (cron)', 'Hiba', 'r2'], $recent[0]->toSheetRow());
    }

    public function testRetryableStatuses(): void
    {
        self::assertTrue(CurlHttpClient::retryable(429));
        self::assertTrue(CurlHttpClient::retryable(503));
        self::assertFalse(CurlHttpClient::retryable(403));
        self::assertFalse(CurlHttpClient::retryable(200));
    }
}

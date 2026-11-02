<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Sheets\SheetNames;
use Xperion\Task\ConnectionCheckTask;
use Xperion\Task\HourlyTask;
use Xperion\Task\MonthlyTask;
use Xperion\Task\SetupSheetsTask;
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

    public function testMonthlyIsIdempotentWhenSheetExists(): void
    {
        $f = new SheetsFixture(['2609', '2610']);
        $c = $this->ctx($f, false);
        (new MonthlyTask())->run($c);
        self::assertStringContainsString('„2610” lap már létezik', self::texts($c));
        self::assertSame([], $f->writes());
    }

    public function testMonthlyReadsSourcesWithoutWriting(): void
    {
        $f = new SheetsFixture(['2609'], [], [
            [['Forrás'], ['A', 't', 'https://a', '', 'igen', ''], ['B', 't', 'https://b', '', 'igen', 'próbaidőszak'], ['C', 't', 'https://c', '', 'nem', '']],
            [['Kulcsszó'], ['queue', '', 'Pozitív', 'EN'], ['IPO', '', 'Kizáró', 'EN']],
        ]);
        $c = $this->ctx($f, false);
        (new MonthlyTask())->run($c);
        self::assertStringContainsString('Aktív források: 2 (ebből próbaidőszakos: 1)', self::texts($c));
        self::assertStringContainsString('Kulcsszavak: 1 pozitív, 1 kizáró', self::texts($c));
        self::assertSame([], $f->writes());
        self::assertFalse($c->hasErrors());
    }

    public function testSetupDryRunWritesNothing(): void
    {
        $f = new SheetsFixture(SheetNames::REQUIRED);
        $c = $this->ctx($f, true);
        (new SetupSheetsTask())->run($c);
        self::assertSame([], $f->writes());
        self::assertCount(3, array_filter($c->lines(), static fn ($l) => $l['level'] === 'dry'));
    }

    public function testSetupCreatesMissingSheetsWithHeaderAndDropdowns(): void
    {
        $f = new SheetsFixture(array_merge(SheetNames::REQUIRED, [SheetNames::LOG]));
        $c = $this->ctx($f, false);
        (new SetupSheetsTask())->run($c);
        self::assertStringContainsString('„Napló” már létezik', self::texts($c));
        $bodies = array_map(static fn ($r) => json_decode((string) $r['body'], true), $f->writes());
        $added = array_column(array_column(array_column(array_filter(array_merge(...array_map(static fn ($b) => $b['requests'] ?? [], $bodies)), static fn ($r) => isset($r['addSheet'])), 'addSheet'), 'properties'), 'title');
        self::assertSame([SheetNames::SETTINGS, SheetNames::POST_IDEAS], $added);
        $values = array_values(array_filter($bodies, static fn ($b) => isset($b['values'])));
        self::assertSame(['Kulcs', 'Érték'], $values[0]['values'][0]);
        self::assertContains(['modell_ertekeles', 'claude-haiku-5-5'], $values[0]['values']);
        self::assertSame('ID', $values[1]['values'][0][0]);
        $validations = array_filter(array_merge(...array_map(static fn ($b) => $b['requests'] ?? [], $bodies)), static fn ($r) => isset($r['setDataValidation']));
        self::assertCount(2, $validations);
    }

    public function testSetupOnlyAppendsMissingSettingKeys(): void
    {
        $f = new SheetsFixture(array_merge(SheetNames::REQUIRED, SheetNames::CREATED), [
            SheetNames::SETTINGS => [['Kulcs', 'Érték'], ['ugyfel_email', 'a@b.hu'], ['modell_poszt', 'sajat-modell']],
        ]);
        (new SetupSheetsTask())->run($this->ctx($f, false));
        $writes = $f->writes();
        self::assertCount(1, $writes);
        $rows = json_decode((string) $writes[0]['body'], true)['values'];
        $keys = array_column($rows, 0);
        self::assertNotContains('ugyfel_email', $keys);
        self::assertNotContains('modell_poszt', $keys, 'meglévő értéket nem ír felül');
        self::assertContains('honap_email_allapot', $keys);
    }

    public function testConnectionCheckReportsMissingSheets(): void
    {
        $f = new SheetsFixture([SheetNames::SOURCES, '2608', '2609']);
        $c = $this->ctx($f, false);
        (new ConnectionCheckTask('xp@proj.iam'))->run($c);
        self::assertTrue($c->hasErrors());
        self::assertStringContainsString('Kulcsszavak', self::texts($c, 'error'));
        self::assertStringContainsString('Munkalapok előkészítése', self::texts($c, 'warn'));
        self::assertStringContainsString('2608 – 2609', self::texts($c));
    }

    public function testHourlyWarnsWithoutSettingsSheet(): void
    {
        $f = new SheetsFixture(SheetNames::REQUIRED);
        $c = $this->ctx($f, false);
        (new HourlyTask())->run($c);
        self::assertStringContainsString('Nincs „Beállítások” lap', self::texts($c, 'warn'));
        self::assertSame([], $f->writes());
    }
}

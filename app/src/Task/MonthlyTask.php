<?php
declare(strict_types=1);

namespace Xperion\Task;

use Xperion\Google\SheetsClient;
use Xperion\Sheets\Keyword;
use Xperion\Sheets\SheetNames;
use Xperion\Sheets\Source;

/**
 * Havi futás (product.md 6.1). Az 1. lépésben csak az előkészítés van kész:
 * célhónap, idempotencia-ellenőrzés, források és kulcsszavak beolvasása.
 * A gyűjtés (2. lépés), az értékelés és a lap létrehozása (3. lépés) később jön.
 */
final class MonthlyTask implements Task
{
    public function id(): string
    {
        return 'havi';
    }

    public function label(): string
    {
        return 'Havi futás';
    }

    public function readOnly(): bool
    {
        return false;
    }

    /** Az előző hónap első napja (a havi futás az előző hónap cikkeit dolgozza fel). */
    public static function targetMonth(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->modify('first day of previous month')->setTime(0, 0);
    }

    public function run(TaskContext $ctx): void
    {
        $month = self::targetMonth($ctx->now);
        $title = SheetNames::monthly($month);
        $ctx->info('Célhónap: ' . $month->format('Y. m.') . ' → „' . $title . '” lap.');

        $sheets = $ctx->sheets();
        if (isset($sheets->sheets()[$title])) {
            $ctx->info('A „' . $title . '” lap már létezik, új lapot nem hoz létre.');
            return;
        }

        [$srcRows, $kwRows] = $sheets->batchGetValues([
            SheetsClient::range(SheetNames::SOURCES, 'A:F'),
            SheetsClient::range(SheetNames::KEYWORDS, 'A:D'),
        ]);
        $active = array_values(array_filter(Source::fromRows($srcRows), static fn (Source $s) => $s->active));
        $trial = count(array_filter($active, static fn (Source $s) => $s->isTrial()));
        $keywords = Keyword::fromRows($kwRows);
        $excl = count(array_filter($keywords, static fn (Keyword $k) => $k->exclude));
        $ctx->info(sprintf('Aktív források: %d (ebből próbaidőszakos: %d).', count($active), $trial));
        $ctx->info(sprintf('Kulcsszavak: %d pozitív, %d kizáró.', count($keywords) - $excl, $excl));

        $ctx->warn('A cikkgyűjtés, az értékelés és a havi lap létrehozása a következő lépésekben készül el; most nem történt írás.');
    }
}

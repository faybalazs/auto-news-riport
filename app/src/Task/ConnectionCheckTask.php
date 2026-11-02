<?php
declare(strict_types=1);

namespace Xperion\Task;

use Xperion\Google\SheetsClient;
use Xperion\Sheets\Keyword;
use Xperion\Sheets\SheetNames;
use Xperion\Sheets\Source;

/** Kapcsolat ellenőrzése: eléri-e a táblát, és megvannak-e a szükséges lapok. Csak olvas. */
final class ConnectionCheckTask implements Task
{
    public function __construct(private string $serviceAccountEmail = '')
    {
    }

    public function id(): string
    {
        return 'kapcsolat';
    }

    public function label(): string
    {
        return 'Kapcsolat ellenőrzése';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function run(TaskContext $ctx): void
    {
        $sheets = $ctx->sheets();
        if ($this->serviceAccountEmail !== '') {
            $ctx->info('Szolgáltatásfiók: ' . $this->serviceAccountEmail);
        }
        $titles = array_keys($sheets->sheets());
        $ctx->done('A tábla elérhető, ' . count($titles) . ' munkalap.');

        $missing = array_values(array_diff(SheetNames::REQUIRED, $titles));
        if ($missing) {
            $ctx->error('Hiányzó munkalap: ' . implode(', ', $missing) . '.');
        }
        $monthly = array_values(array_filter($titles, [SheetNames::class, 'isMonthly']));
        sort($monthly);
        if ($monthly) {
            $ctx->info('Havi lapok: ' . count($monthly) . ' db (' . $monthly[0] . ' – ' . $monthly[count($monthly) - 1] . ').');
        }

        if (in_array(SheetNames::SOURCES, $titles, true) && in_array(SheetNames::KEYWORDS, $titles, true)) {
            [$srcRows, $kwRows] = $sheets->batchGetValues([
                SheetsClient::range(SheetNames::SOURCES, 'A:F'),
                SheetsClient::range(SheetNames::KEYWORDS, 'A:D'),
            ]);
            $sources = Source::fromRows($srcRows);
            $active = array_filter($sources, static fn (Source $s) => $s->active);
            $ctx->info(sprintf('Figyelendő oldalak: %d forrás, ebből %d aktív.', count($sources), count($active)));
            $keywords = Keyword::fromRows($kwRows);
            $excl = count(array_filter($keywords, static fn (Keyword $k) => $k->exclude));
            $ctx->info(sprintf('Kulcsszavak: %d pozitív, %d kizáró.', count($keywords) - $excl, $excl));
        }
    }
}

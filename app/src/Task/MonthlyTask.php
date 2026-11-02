<?php
declare(strict_types=1);

namespace Xperion\Task;

/**
 * Havi futás (product.md 6.1). Most a célhónapot határozza meg; a gyűjtés
 * (4. lépés) és az értékelés (5. lépés) később kerül bele, ügyfelenként.
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
        return true;
    }

    /** Az előző hónap első napja (a havi futás az előző hónap cikkeit dolgozza fel). */
    public static function targetMonth(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->modify('first day of previous month')->setTime(0, 0);
    }

    /** Hónapazonosító: 2026. október → "2610". */
    public static function monthKey(\DateTimeInterface $month): string
    {
        return $month->format('ym');
    }

    public function run(TaskContext $ctx): void
    {
        $month = self::targetMonth($ctx->now);
        $ctx->info('Célhónap: ' . $month->format('Y. m.') . ' (' . self::monthKey($month) . ').');
        $ctx->warn('A cikkgyűjtés és az értékelés a 4–5. lépésben készül el; most nem történt írás.');
    }
}

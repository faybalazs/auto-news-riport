<?php
declare(strict_types=1);

namespace Xperion\Task;

/**
 * Havi futás (product.md 6.1): minden „Aktív” ügyfélnek az előző hónap cikkeinek
 * gyűjtése, Claude-értékelése, és élesben a havi lista és az összefoglaló mentése.
 */
final class MonthlyTask implements Task
{
    /** @var (callable(TaskContext, string):void)|null a havi futás minden aktív ügyfélre */
    private $collectAll;

    public function __construct(?callable $collectAll = null)
    {
        $this->collectAll = $collectAll;
    }

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

    /** Hónapazonosító: 2026. október → "2610". */
    public static function monthKey(\DateTimeInterface $month): string
    {
        return $month->format('ym');
    }

    public function run(TaskContext $ctx): void
    {
        $month = self::targetMonth($ctx->now);
        $ctx->info('Célhónap: ' . $month->format('Y. m.') . ' (' . self::monthKey($month) . ').');
        if ($this->collectAll === null) {
            $ctx->warn('A cikkgyűjtés nincs bekötve; most nem történt semmi.');
            return;
        }
        ($this->collectAll)($ctx, self::monthKey($month));
    }
}

<?php
declare(strict_types=1);

namespace Xperion\Task;

/**
 * Óránkénti futás (product.md 6.2): a „Mehet” cikkek élő feltöltése (8. lépés).
 * A posztgenerálás a 9. lépésben kerül bele; a havi e-mail a jóváhagyáskor azonnal megy.
 */
final class HourlyTask implements Task
{
    /** @var (callable(TaskContext):void)|null */
    private $upload;

    public function __construct(?callable $upload = null)
    {
        $this->upload = $upload;
    }

    public function id(): string
    {
        return 'orankenti';
    }

    public function label(): string
    {
        return 'Óránkénti futás';
    }

    public function readOnly(): bool
    {
        return false;
    }

    public function run(TaskContext $ctx): void
    {
        $ctx->info('Időpont: ' . $ctx->now->format('Y. m. d. H:i') . '.');
        if ($this->upload === null) {
            $ctx->warn('A feltöltés nincs bekötve; most nem történt semmi.');
            return;
        }
        ($this->upload)($ctx);
    }
}

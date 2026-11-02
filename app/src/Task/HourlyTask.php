<?php
declare(strict_types=1);

namespace Xperion\Task;

/**
 * Óránkénti futás (product.md 6.2). A teendők (feltöltés, posztgenerálás,
 * jóváhagyott e-mail küldése) a 7–9. lépésben kerülnek bele.
 */
final class HourlyTask implements Task
{
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
        return true;
    }

    public function run(TaskContext $ctx): void
    {
        $ctx->info('Időpont: ' . $ctx->now->format('Y. m. d. H:i') . '.');
        $ctx->warn('Még nincs óránkénti teendő: a feltöltés, a posztgenerálás és az e-mail-küldés a későbbi lépésekben készül el.');
    }
}

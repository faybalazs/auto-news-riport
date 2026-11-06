<?php
declare(strict_types=1);

namespace Xperion\Task;

interface Task
{
    /** Gépi azonosító (URL-ben is): pl. "havi". */
    public function id(): string;

    /** Magyar név a felületen és a naplóban. */
    public function label(): string;

    /** Csak olvas (élesben sem ír a táblába): a Napló lapra sem kerül. */
    public function readOnly(): bool;

    public function run(TaskContext $ctx): void;
}

<?php
declare(strict_types=1);

namespace Xperion\Press;

/**
 * Hiba a WordPress-hívásban. kind: auth (401/403 – a futás megáll), transport (időtúllépés, 5xx –
 * nincs vak ismétlés, legközelebb előbb get_pair), tool (a bővítmény hibaüzenete, result.isError).
 */
final class WpException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $kind, public readonly int $status = 0)
    {
        parent::__construct($message);
    }
}

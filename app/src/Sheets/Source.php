<?php
declare(strict_types=1);

namespace Xperion\Sheets;

/** Egy sor a "Figyelendő oldalak" lapról (A–F). */
final class Source
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $url,
        public readonly string $topics,
        public readonly bool $active,
        public readonly string $note,
    ) {
    }

    /** "próbaidőszak" megjegyzésű forrás: a cikkei külön blokkba kerülnek. */
    public function isTrial(): bool
    {
        return str_contains(mb_strtolower($this->note), 'próbaidőszak');
    }

    /**
     * @param list<list<string>> $rows fejléccel együtt
     * @return list<self>
     */
    public static function fromRows(array $rows): array
    {
        $out = [];
        foreach (array_slice($rows, 1) as $r) {
            $url = trim($r[2] ?? '');
            if ($url === '') {
                continue;
            }
            $out[] = new self(
                trim($r[0] ?? ''),
                trim($r[1] ?? ''),
                $url,
                trim($r[3] ?? ''),
                mb_strtolower(trim($r[4] ?? '')) === 'igen',
                trim($r[5] ?? ''),
            );
        }
        return $out;
    }
}

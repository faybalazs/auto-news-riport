<?php
declare(strict_types=1);

namespace Xperion\Sheets;

/** Egy sor a "Kulcsszavak" lapról (A–D). */
final class Keyword
{
    public function __construct(
        public readonly string $keyword,
        public readonly string $topic,
        public readonly bool $exclude,
        public readonly string $language,
    ) {
    }

    /**
     * @param list<list<string>> $rows fejléccel együtt
     * @return list<self>
     */
    public static function fromRows(array $rows): array
    {
        $out = [];
        foreach (array_slice($rows, 1) as $r) {
            $kw = trim($r[0] ?? '');
            if ($kw === '') {
                continue;
            }
            $out[] = new self($kw, trim($r[1] ?? ''), str_starts_with(mb_strtolower(trim($r[2] ?? '')), 'kizár'), strtoupper(trim($r[3] ?? '')));
        }
        return $out;
    }
}

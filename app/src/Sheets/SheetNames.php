<?php
declare(strict_types=1);

namespace Xperion\Sheets;

/** A Google Sheet munkalapjainak neve (product.md 4.). */
final class SheetNames
{
    public const SOURCES = 'Figyelendő oldalak';
    public const KEYWORDS = 'Kulcsszavak';
    public const LINKEDIN_POSTS = 'LinkedIn posztok';
    public const LINKEDIN_RULES = 'LinkedIn szabályok';
    public const POST_IDEAS = 'Posztötletek';
    public const SETTINGS = 'Beállítások';
    public const LOG = 'Napló';
    /** A havi lapok mintája. */
    public const MONTHLY_TEMPLATE = '2609';

    /** Meglévő lapok, amelyek nélkül nem lehet dolgozni. */
    public const REQUIRED = [self::SOURCES, self::KEYWORDS, self::LINKEDIN_POSTS, self::LINKEDIN_RULES, self::MONTHLY_TEMPLATE];

    /** Új lapok, amelyeket a "Munkalapok előkészítése" hoz létre. */
    public const CREATED = [self::SETTINGS, self::LOG, self::POST_IDEAS];

    /** Havi lap neve egy dátumból: 2026. október → "2610". */
    public static function monthly(\DateTimeInterface $month): string
    {
        return $month->format('ym');
    }

    public static function isMonthly(string $title): bool
    {
        return (bool) preg_match('/^\d{2}(0[1-9]|1[0-2])$/', $title);
    }
}

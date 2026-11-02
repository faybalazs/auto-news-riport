<?php
declare(strict_types=1);

namespace Xperion\Collect;

/** Dátum felismerése szövegből (angol, magyar és gépi formátumok) → Y-m-d, vagy null. */
final class DateText
{
    private const HU = ['január' => 1, 'február' => 2, 'március' => 3, 'április' => 4, 'május' => 5, 'június' => 6, 'július' => 7, 'augusztus' => 8, 'szeptember' => 9, 'október' => 10, 'november' => 11, 'december' => 12,
        'jan' => 1, 'febr' => 2, 'márc' => 3, 'ápr' => 4, 'máj' => 5, 'jún' => 6, 'júl' => 7, 'aug' => 8, 'szept' => 9, 'okt' => 10, 'nov' => 11, 'dec' => 12];
    private const EN = ['january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'sept' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];

    /** Gépi formátum (ISO 8601, RFC 2822): időzónával együtt, Budapest szerinti napra. */
    public static function machine(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return self::valid($s);
        }
        // A hét napját levágjuk: ha nem egyezik a dátummal, a PHP a következő ilyen napra ugrana.
        $s = preg_replace('/^[A-Za-z]{3,9},\s*/', '', $s) ?? $s;
        try {
            $d = new \DateTimeImmutable($s);
        } catch (\Exception) {
            return self::find($s);
        }
        return $d->setTimezone(new \DateTimeZone('Europe/Budapest'))->format('Y-m-d');
    }

    /** Dátum keresése egy szövegrészben. */
    public static function find(string $text): ?string
    {
        $t = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? ''));
        if ($t === '') {
            return null;
        }
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $t, $m)) {
            return self::ymd((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/(\d{4})\.\s?(\d{1,2})\.\s?(\d{1,2})/', $t, $m)) {
            return self::ymd((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        $hu = implode('|', array_map('preg_quote', array_keys(self::HU)));
        if (preg_match('/(\d{4})\.\s?(' . $hu . ')\.?\s?(\d{1,2})/u', $t, $m)) {
            return self::ymd((int) $m[1], self::HU[$m[2]], (int) $m[3]);
        }
        $en = implode('|', array_map('preg_quote', array_keys(self::EN)));
        if (preg_match('/\b(' . $en . ')\.?\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/', $t, $m)) {
            return self::ymd((int) $m[3], self::EN[$m[1]], (int) $m[2]);
        }
        if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(' . $en . ')\.?,?\s+(\d{4})\b/', $t, $m)) {
            return self::ymd((int) $m[3], self::EN[$m[2]], (int) $m[1]);
        }
        if (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/', $t, $m)) {
            // nap/hó/év (európai), ha egyértelmű; különben hó/nap/év
            return (int) $m[1] > 12 ? self::ymd((int) $m[3], (int) $m[2], (int) $m[1]) : self::ymd((int) $m[3], (int) $m[1], (int) $m[2]);
        }
        return null;
    }

    private static function ymd(int $y, int $m, int $d): ?string
    {
        return checkdate($m, $d, $y) && $y > 1990 ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
    }

    private static function valid(string $s): ?string
    {
        [$y, $m, $d] = array_map('intval', explode('-', $s));
        return self::ymd($y, $m, $d);
    }
}

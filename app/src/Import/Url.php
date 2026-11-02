<?php
declare(strict_types=1);

namespace Xperion\Import;

/** Link egységesítése a duplikátumszűréshez: követőkódok, töredék és záró perjel nélkül. */
final class Url
{
    private const TRACKING = '/^(utm_[a-z]+|fbclid|gclid|mc_cid|mc_eid|_hsenc|_hsmi|hsctatracking|ref)$/i';

    public static function normalize(string $url): string
    {
        $url = trim($url);
        $p = parse_url($url);
        if ($p === false || empty($p['host'])) {
            return mb_strtolower(rtrim($url, '/'));
        }
        $host = preg_replace('/^www\./', '', mb_strtolower($p['host'])) ?? '';
        $path = rtrim($p['path'] ?? '', '/');
        $query = '';
        if (!empty($p['query'])) {
            parse_str($p['query'], $q);
            $q = array_filter($q, static fn ($k) => !preg_match(self::TRACKING, (string) $k), ARRAY_FILTER_USE_KEY);
            ksort($q);
            $query = $q ? '?' . http_build_query($q) : '';
        }
        return $host . $path . $query;
    }

    public static function hash(string $url): string
    {
        return sha1(self::normalize($url));
    }

    /** Google Sheet azonosító egy linkből vagy magából az azonosítóból. */
    public static function spreadsheetId(string $input): string
    {
        $input = trim($input);
        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]{20,})#', $input, $m)) {
            return $m[1];
        }
        return preg_match('/^[a-zA-Z0-9_-]{20,}$/', $input) ? $input : '';
    }
}

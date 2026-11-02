<?php
declare(strict_types=1);

namespace Xperion\Press;

/**
 * A sajtószemle-pár kulcsa a bővítmény leírása szerint (PRESS_API.md): a forrás-URL egységesítése
 * (kisbetűs host; utm_*, fbclid, gclid paraméter és #töredék nélkül), kulcs = „p-” + SHA-256 első 20 hexa karaktere.
 * Az egységesített URL megy source_url-ként is.
 */
final class PressKey
{
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $p = parse_url($url);
        if ($p === false || empty($p['host']) || empty($p['scheme'])) {
            return $url;
        }
        $out = strtolower($p['scheme']) . '://' . strtolower($p['host']) . (isset($p['port']) ? ':' . $p['port'] : '') . ($p['path'] ?? '');
        if (isset($p['query']) && $p['query'] !== '') {
            $keep = array_filter(explode('&', $p['query']), static function (string $pair): bool {
                $name = strtolower(urldecode(explode('=', $pair, 2)[0]));
                return $pair !== '' && !str_starts_with($name, 'utm_') && $name !== 'fbclid' && $name !== 'gclid';
            });
            if ($keep) {
                $out .= '?' . implode('&', $keep);
            }
        }
        return $out;
    }

    public static function key(string $url): string
    {
        return 'p-' . substr(hash('sha256', self::normalizeUrl($url)), 0, 20);
    }

    /** A bővítmény csak https forrást fogad el, felhasználónév és jelszó nélkül. */
    public static function acceptable(string $normalized): bool
    {
        $p = parse_url($normalized);
        return is_array($p) && ($p['scheme'] ?? '') === 'https' && !empty($p['host']) && !isset($p['user']) && !isset($p['pass']);
    }
}

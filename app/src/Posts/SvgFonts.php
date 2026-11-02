<?php
declare(strict_types=1);

namespace Xperion\Posts;

/**
 * A Lato betűtípus beágyazása a (már tisztított) SVG-be megjelenítéskor, hogy a kép minden eszközön
 * és a letöltött PNG-ben is ugyanúgy nézzen ki. A betűk a repóból jönnek (app/resources/fonts), nem a modelltől.
 */
final class SvgFonts
{
    public static function embed(string $svg): string
    {
        if ($svg === '' || !preg_match('/<svg\b[^>]*>/', $svg, $m, PREG_OFFSET_CAPTURE)) {
            return $svg;
        }
        static $css = null;
        $css ??= (string) @file_get_contents(dirname(__DIR__, 2) . '/resources/fonts/lato.css');
        if ($css === '') {
            return $svg;
        }
        $at = $m[0][1] + strlen($m[0][0]);
        return substr($svg, 0, $at) . '<defs><style>' . $css . '</style></defs>' . substr($svg, $at);
    }
}

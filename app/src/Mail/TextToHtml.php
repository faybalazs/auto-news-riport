<?php
declare(strict_types=1);

namespace Xperion\Mail;

/**
 * A sima szöveges levél egyszerű HTML-változata: bekezdések, „- ” kezdetű sorokból lista,
 * „Címke: leírás” pontokban félkövér címke, a linkek kattinthatók. Külső kép és stíluslap nincs.
 */
final class TextToHtml
{
    public static function convert(string $text): string
    {
        $blocks = preg_split("/\n\s*\n/", str_replace("\r\n", "\n", trim($text))) ?: [];
        $out = [];
        foreach ($blocks as $block) {
            $lines = explode("\n", trim($block));
            $html = '';
            $list = [];
            $flush = static function () use (&$list, &$html): void {
                if ($list) {
                    $html .= '<ul style="margin:0 0 12px 0;padding-left:20px">' . implode('', array_map(static fn ($i) => '<li style="margin:0 0 6px 0">' . $i . '</li>', $list)) . '</ul>';
                    $list = [];
                }
            };
            $para = [];
            foreach ($lines as $l) {
                if (preg_match('/^\s*[-•*]\s+(.*)$/u', $l, $m)) {
                    if ($para) {
                        $html .= '<p style="margin:0 0 12px 0">' . implode('<br>', $para) . '</p>';
                        $para = [];
                    }
                    $item = self::inline($m[1]);
                    $list[] = preg_replace('/^([^:<]{2,60}):/u', '<b>$1:</b>', $item, 1) ?? $item;
                } else {
                    $flush();
                    $para[] = self::inline($l);
                }
            }
            $flush();
            if ($para) {
                $html .= '<p style="margin:0 0 12px 0">' . implode('<br>', $para) . '</p>';
            }
            $out[] = $html;
        }
        return '<!doctype html><html lang="hu"><head><meta charset="utf-8"></head><body style="margin:0;padding:24px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#1f2937;background:#ffffff">'
            . '<div style="max-width:640px">' . implode('', $out) . '</div></body></html>';
    }

    private static function inline(string $s): string
    {
        $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        return preg_replace('~https?://[^\s<]+[^\s<.,;:!?)]~u', '<a href="$0" style="color:#1d4ed8">$0</a>', $s) ?? $s;
    }
}

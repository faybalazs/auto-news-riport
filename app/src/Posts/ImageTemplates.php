<?php
declare(strict_types=1);

namespace Xperion\Posts;

/**
 * A posztképek rögzített sablonjai a jóváhagyott minta (docs/terv/poszt-kep-minta.png) arculatával:
 * sötétkék rácsos háttér, cián kiemelés, kategóriacímke, nagy cím, alcím, vizuális blokk, elválasztott lábléc.
 * A Claude csak a tartalmat és az elrendezést adja; a rajzot a program készíti, így a minőség mindig azonos.
 */
final class ImageTemplates
{
    public const LAYOUTS = ['osszehasonlitas', 'pontok', 'kulcsszam', 'valogatas', 'kerdes'];

    private const FONT = "Lato, 'Helvetica Neue', Helvetica, Arial, sans-serif";
    private const L = 90;
    private const R = 1110;
    private const W = 1020;

    /** @param array<string,mixed> $s a tartalom (layout, eyebrow, title, subtitle, …, tagline) */
    public static function render(array $s, string $website): string
    {
        $layout = in_array($s['layout'] ?? '', self::LAYOUTS, true) ? (string) $s['layout'] : 'kerdes';
        $ex = (array) ($s['example'] ?? []);
        if ($layout === 'osszehasonlitas' && (trim((string) ($ex['measured_value'] ?? '')) === '' || trim((string) ($ex['real_value'] ?? '')) === '')) {
            $layout = 'kerdes'; // számpélda csak megadott értékekkel
        }
        if ($layout === 'kulcsszam' && trim((string) ($s['number'] ?? '')) === '') {
            $layout = 'kerdes';
        }
        $big = $layout === 'kerdes';
        $o = [];
        // fejléc: címke, cím, alcím
        $o[] = self::text(self::L, 124, mb_strtoupper(self::str($s['eyebrow'] ?? '', 40)), 26, 700, '#00A6EA', ' letter-spacing="0.5"');
        $o[] = '<rect x="90" y="140" width="70" height="6" fill="#00A6EA"/>';
        // a címben a „\n” kötelező sortörés (a Claude értelmes egységekre bontja, mint a mintán)
        $title = implode("\n", array_map(static fn ($p) => self::str($p, 80), explode("\n", str_replace(["\r\n", ' / '], "\n", is_scalar($s['title'] ?? '') ? (string) $s['title'] : ''))));
        [$tl, $ts] = self::fit($title, $big ? 92 : 76, 56, self::W, $big ? 4 : 3, true);
        $y = 196 + $ts;
        $o[] = self::lines(self::L, $y, $tl, $ts, 700, '#FFFFFF', (int) round($ts * 1.26));
        $y += (count($tl) - 1) * (int) round($ts * 1.26);
        $sub = self::str($s['subtitle'] ?? '', 110);
        if ($sub !== '') {
            [$sl, $ss] = self::fit($sub, 36, 28, self::W, 2, false);
            $y += 72;
            $o[] = self::lines(self::L, $y, $sl, $ss, 400, '#9FB3D1', (int) round($ss * 1.3));
            $y += (count($sl) - 1) * (int) round($ss * 1.3);
        }
        $top = max($y + 70, $big ? 640 : 500);
        $o[] = match ($layout) {
            'osszehasonlitas' => self::comparison($ex, (array) ($s['steps'] ?? []), $top),
            'pontok' => self::points((array) ($s['points'] ?? []), $top),
            'kulcsszam' => self::bigNumber((string) $s['number'], self::str($s['number_label'] ?? '', 40), self::str($s['number_note'] ?? '', 90), $top),
            'valogatas' => self::roundup((array) ($s['items'] ?? []), $top),
            default => self::steps((array) ($s['steps'] ?? []), (int) (($top + 1030) / 2 - 36)),
        };
        // lábléc
        $o[] = '<line x1="90" y1="1070" x2="1110" y2="1070" stroke="#2A3F6B" stroke-width="2"/>';
        [$fl, $fs] = self::fit(self::str($s['tagline'] ?? '', 60), 28, 22, 700, 1, false);
        $o[] = self::text(self::L, 1126, $fl[0] ?? '', $fs, 400, '#9FB3D1');
        $o[] = self::text(self::R, 1126, self::str($website, 40), 32, 700, '#FFFFFF', ' text-anchor="end"');
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 1200" width="1200" height="1200">'
            . '<defs><linearGradient id="bg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0A1630"/><stop offset="1" stop-color="#13295A"/></linearGradient>'
            . '<pattern id="grid" width="60" height="60" patternUnits="userSpaceOnUse"><path d="M60 0H0V60" fill="none" stroke="#1E3566" stroke-width="1" opacity="0.45"/></pattern></defs>'
            . '<rect width="1200" height="1200" fill="url(#bg)"/><rect width="1200" height="1200" fill="url(#grid)"/>'
            . '<g font-family="' . htmlspecialchars(self::FONT, ENT_XML1 | ENT_QUOTES) . '">' . implode('', $o) . '</g></svg>';
    }

    /** A: „mért → valós” kártyapár, magyarázat, folyamatlánc (a minta szerint). @param array<string,mixed> $ex @param list<mixed> $steps */
    private static function comparison(array $ex, array $steps, int $top): string
    {
        $top = max($top, 500);
        $o = '<rect x="91" y="' . ($top + 7) . '" width="288" height="42" rx="21" fill="none" stroke="#00A6EA" stroke-width="2"/>'
            . self::text(235, $top + 35, 'ILLUSZTRATÍV PÉLDA', 19, 700, '#00A6EA', ' text-anchor="middle" letter-spacing="0.5"');
        $c = $top + 75;
        foreach ([[90, '#15305E', '#B7C6DE', '#A9BCD9', 700, 'measured'], [670, '#00A6EA', '#0A1A3A', '#0A1A3A', 800, 'real']] as [$x, $bg, $lc, $vc, $vw, $k]) {
            $o .= '<rect x="' . $x . '" y="' . $c . '" width="440" height="230" rx="24" fill="' . $bg . '"/>';
            [$ll, $ls] = self::fit(mb_strtoupper(self::str($ex[$k . '_label'] ?? ($k === 'real' ? 'Valós' : 'Mért'), 30)), 23, 17, 360, 1, true);
            $o .= self::text($x + 40, $c + 67, $ll[0] ?? '', $ls, 700, $lc, ' letter-spacing="0.3"');
            [$vl, $vs] = self::fit(self::str($ex[$k . '_value'] ?? '', 14), 104, 56, 360, 1, true);
            $o .= self::text($x + 40, $c + 185, $vl[0] ?? '', $vs, $vw, $vc);
        }
        $o .= '<line x1="550" y1="' . ($c + 115) . '" x2="622" y2="' . ($c + 115) . '" stroke="#FFFFFF" stroke-width="4"/><polygon points="620,' . ($c + 101) . ' 650,' . ($c + 115) . ' 620,' . ($c + 129) . '" fill="#FFFFFF"/>';
        $note = self::str($ex['note'] ?? '', 90);
        if ($note !== '') {
            [$nl, $ns] = self::fit($note, 28, 22, self::W, 1, false);
            $o .= self::text(600, $c + 287, $nl[0] ?? '', $ns, 400, '#B7C6DE', ' text-anchor="middle"');
        }
        return $o . self::steps($steps, max($c + 364, 939));
    }

    /** Folyamatlánc tablettákból, az utolsó kitöltve. @param list<mixed> $steps */
    private static function steps(array $steps, int $y): string
    {
        $steps = array_values(array_filter(array_map(static fn ($v) => self::str($v, 18), $steps), static fn ($v) => $v !== ''));
        $steps = array_slice($steps, 0, 4);
        if (count($steps) < 2) {
            return '';
        }
        $size = 30;
        do {
            $widths = array_map(static fn ($t) => (int) ceil(self::width($t, $size, true)) + 64, $steps);
            $total = array_sum($widths) + 62 * (count($steps) - 1);
            $size -= 2;
        } while ($total > self::W && $size > 18);
        $size += 2;
        $x = (int) round(600 - $total / 2);
        $o = '';
        foreach ($steps as $i => $t) {
            $w = $widths[$i];
            $last = $i === count($steps) - 1;
            $o .= '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="72" rx="36" fill="' . ($last ? '#00A6EA' : '#12295A') . '"' . ($last ? '' : ' stroke="#00A6EA" stroke-width="3"') . '/>'
                . self::text($x + $w / 2, $y + 47, $t, $size, 700, $last ? '#0A1A3A' : '#FFFFFF', ' text-anchor="middle"');
            if (!$last) {
                $ax = $x + $w + 12;
                $o .= '<line x1="' . $ax . '" y1="' . ($y + 36) . '" x2="' . ($ax + 36) . '" y2="' . ($y + 36) . '" stroke="#00A6EA" stroke-width="3"/><polygon points="' . ($ax + 34) . ',' . ($y + 29) . ' ' . ($ax + 48) . ',' . ($y + 36) . ' ' . ($ax + 34) . ',' . ($y + 43) . '" fill="#00A6EA"/>';
            }
            $x += $w + 62;
        }
        return $o;
    }

    /** B: három pont kártyákon, számozott cián jelöléssel. @param list<mixed> $points */
    private static function points(array $points, int $top): string
    {
        $points = array_slice(array_values(array_filter($points, static fn ($p) => is_array($p) && trim((string) ($p['title'] ?? '')) !== '')), 0, 3);
        $n = max(1, count($points));
        $gap = 24;
        $h = (int) floor((1030 - $top - $gap * ($n - 1)) / $n);
        $h = min($h, 170);
        $o = '';
        $y = $top;
        foreach ($points as $i => $p) {
            $o .= '<rect x="90" y="' . $y . '" width="1020" height="' . $h . '" rx="24" fill="#15305E"/>'
                . '<circle cx="152" cy="' . ($y + $h / 2) . '" r="32" fill="none" stroke="#00A6EA" stroke-width="3"/>'
                . self::text(152, (int) ($y + $h / 2 + 11), (string) ($i + 1), 30, 800, '#00A6EA', ' text-anchor="middle"');
            [$tl, $ts] = self::fit(self::str($p['title'], 50), 34, 26, 860, 1, true);
            [$dl, $ds] = self::fit(self::str($p['text'] ?? '', 120), 26, 22, 860, $h >= 120 ? 2 : 1, false);
            $block = $ts + ($dl ? 10 + count($dl) * (int) round($ds * 1.3) : 0);
            $ty = (int) ($y + ($h - $block) / 2 + $ts * 0.85);
            $o .= self::text(220, $ty, $tl[0] ?? '', $ts, 700, '#FFFFFF');
            if ($dl) {
                $o .= self::lines(220, $ty + 10 + (int) round($ds * 1.25), $dl, $ds, 400, '#B7C6DE', (int) round($ds * 1.3));
            }
            $y += $h + $gap;
        }
        return $o;
    }

    /** C: egy kulcsszám nagy cián kártyán. */
    private static function bigNumber(string $number, string $label, string $note, int $top): string
    {
        $top = min(max($top, 500), 640);
        $o = '<rect x="90" y="' . $top . '" width="1020" height="330" rx="28" fill="#00A6EA"/>';
        [$ll, $ls] = self::fit(mb_strtoupper($label), 26, 18, 940, 1, true);
        $o .= self::text(140, $top + 74, $ll[0] ?? '', $ls, 700, '#0A1A3A', ' letter-spacing="0.4"');
        [$nl, $ns] = self::fit(self::str($number, 16), 190, 90, 940, 1, true);
        $o .= self::text(132, $top + 260, $nl[0] ?? '', $ns, 900, '#0A1A3A');
        if ($note !== '') {
            [$tl, $ts] = self::fit($note, 30, 22, self::W, 2, false);
            $o .= self::lines(self::L, $top + 400, $tl, $ts, 400, '#B7C6DE', (int) round($ts * 1.3));
        }
        return $o;
    }

    /** E (havi válogatás): számozott sorok forrással és címmel. @param list<mixed> $items */
    private static function roundup(array $items, int $top): string
    {
        $items = array_slice(array_values(array_filter($items, static fn ($p) => is_array($p) && trim((string) ($p['title'] ?? '')) !== '')), 0, 4);
        $n = max(1, count($items));
        $h = min(150, (int) floor((1040 - $top) / $n));
        $o = '';
        $y = $top;
        foreach ($items as $i => $it) {
            $o .= '<rect x="90" y="' . ($y + 14) . '" width="64" height="64" rx="18" fill="#00A6EA"/>'
                . self::text(122, $y + 58, (string) ($i + 1), 32, 800, '#0A1A3A', ' text-anchor="middle"');
            [$sl, $ss] = self::fit(mb_strtoupper(self::str($it['source'] ?? '', 40)), 20, 16, 900, 1, true);
            $o .= self::text(186, $y + 34, $sl[0] ?? '', $ss, 700, '#00A6EA', ' letter-spacing="0.4"');
            [$tl, $ts] = self::fit(self::str($it['title'], 110), 30, 22, 920, $h >= 140 ? 2 : 1, true);
            $o .= self::lines(186, $y + 76, $tl, $ts, 700, '#FFFFFF', (int) round($ts * 1.25));
            if ($i < $n - 1) {
                $o .= '<line x1="186" y1="' . ($y + $h - 8) . '" x2="1110" y2="' . ($y + $h - 8) . '" stroke="#2A3F6B" stroke-width="1.5"/>';
            }
            $y += $h;
        }
        return $o;
    }

    /**
     * Sortörés becsült Lato-szélességgel; ha nem fér el a megadott sorokban, kisebb betűvel próbálja.
     *
     * @return array{0:list<string>, 1:int} sorok és betűméret
     */
    public static function fit(string $text, int $size, int $min, int $maxW, int $maxLines, bool $bold): array
    {
        $text = trim($text);
        if ($text === '') {
            return [[], $size];
        }
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        for ($s = $size; $s >= $min; $s -= 2) {
            $lines = self::wrap($text, $s, $maxW, $bold);
            if (count($lines) <= $maxLines) {
                return [$lines, $s];
            }
        }
        $lines = array_slice(self::wrap($text, $min, $maxW, $bold), 0, $maxLines);
        $lines[$maxLines - 1] = rtrim((string) $lines[$maxLines - 1], ' ,.;:') . '…';
        return [$lines, $min];
    }

    /** @return list<string> */
    private static function wrap(string $text, int $size, int $maxW, bool $bold): array
    {
        if (str_contains($text, "\n")) {
            $out = [];
            foreach (explode("\n", $text) as $part) {
                array_push($out, ...self::wrap(trim($part), $size, $maxW, $bold));
            }
            return array_values(array_filter($out, static fn ($l) => $l !== ''));
        }
        $out = [];
        $line = '';
        foreach (preg_split('/\s+/u', $text) ?: [] as $w) {
            $try = $line === '' ? $w : $line . ' ' . $w;
            if ($line !== '' && self::width($try, $size, $bold) > $maxW) {
                $out[] = $line;
                $line = $w;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $out[] = $line;
        }
        return $out;
    }

    /** Becsült szövegszélesség Lato betűvel (karakterosztályonként, kissé felfelé kerekítve). */
    public static function width(string $text, int $size, bool $bold): float
    {
        $em = 0.0;
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $em += match (true) {
                $ch === ' ' => 0.26,
                (bool) preg_match('/[iljtfrI.,:;\'!|()\-]/u', $ch) => 0.30,
                (bool) preg_match('/[mwMW]/u', $ch) => 0.84,
                (bool) preg_match('/[0-9%]/u', $ch) => $ch === '%' ? 0.80 : 0.58,
                (bool) preg_match('/\p{Lu}/u', $ch) => 0.66,
                default => 0.53,
            };
        }
        return $em * $size * ($bold ? 1.05 : 1.0);
    }

    private static function str(mixed $v, int $max): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', is_scalar($v) ? (string) $v : '') ?? '');
        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)) . '…' : $s;
    }

    private static function text(float|int $x, float|int $y, string $t, int $size, int $weight, string $fill, string $extra = ''): string
    {
        if ($t === '') {
            return '';
        }
        return '<text x="' . round($x, 1) . '" y="' . round($y, 1) . '" font-size="' . $size . '" font-weight="' . $weight . '" fill="' . $fill . '"' . $extra . '>' . htmlspecialchars($t, ENT_XML1 | ENT_QUOTES) . '</text>';
    }

    /** @param list<string> $lines */
    private static function lines(int $x, int $y, array $lines, int $size, int $weight, string $fill, int $lh): string
    {
        if (!$lines) {
            return '';
        }
        $o = '<text font-size="' . $size . '" font-weight="' . $weight . '" fill="' . $fill . '">';
        foreach ($lines as $i => $l) {
            $o .= '<tspan x="' . $x . '" ' . ($i === 0 ? 'y="' . $y . '"' : 'dy="' . $lh . '"') . '>' . htmlspecialchars($l, ENT_XML1 | ENT_QUOTES) . '</tspan>';
        }
        return $o . '</text>';
    }
}

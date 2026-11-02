<?php
declare(strict_types=1);

namespace Xperion\Import;

/**
 * A Google Sheet lapjainak értelmezése (product.md 4.). Tiszta függvények:
 * sorokat kapnak (fejléccel), adatrekordokat adnak vissza.
 */
final class SheetParser
{
    public const DECISIONS = ['mehet' => 'mehet', 'linkedin poszt' => 'linkedin', 'nem kell' => 'nemkell'];
    public const POST_STATUSES = ['tervezet' => 'tervezet', 'ügyfélnél' => 'ugyfelnel', 'jóváhagyva' => 'jovahagyva', 'megjelent' => 'megjelent'];
    public const IDEA_STATUSES = ['új' => 'uj', 'elkészült' => 'elkeszult', 'jóváhagyva' => 'jovahagyva', 'ütemezve' => 'utemezve', 'elvetve' => 'elvetve'];

    public static function key(string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s) ?? ''));
    }

    /**
     * Fejléc alapján oszlopindexek: normalizált név → index.
     *
     * @param list<string> $header
     * @return array<string,int>
     */
    public static function columns(array $header): array
    {
        $map = [];
        foreach ($header as $i => $h) {
            $k = self::key($h);
            if ($k !== '' && !isset($map[$k])) {
                $map[$k] = $i;
            }
        }
        return $map;
    }

    /** @param array<string,int> $cols @param list<string> $row @param list<string> $names */
    private static function cell(array $cols, array $row, array $names): string
    {
        foreach ($names as $n) {
            if (isset($cols[$n])) {
                return trim($row[$cols[$n]] ?? '');
            }
        }
        return '';
    }

    /**
     * Havi lap cikkei (régi 2408–2608 és új 2609+ formátum).
     *
     * @param list<list<string>> $rows
     * @return list<array<string,mixed>>
     */
    public static function monthly(array $rows): array
    {
        $headerAt = null;
        foreach ($rows as $i => $r) {
            $keys = array_map([self::class, 'key'], $r);
            if (in_array('link', $keys, true) && (in_array('title', $keys, true) || in_array('no', $keys, true))) {
                $headerAt = $i;
                break;
            }
        }
        if ($headerAt === null) {
            return [];
        }
        $c = self::columns($rows[$headerAt]);
        $out = [];
        $newSource = false;
        foreach (array_slice($rows, $headerAt + 1) as $r) {
            $first = self::key(implode(' ', array_slice($r, 0, 2)));
            if (str_starts_with($first, 'új források') || str_starts_with($first, 'uj forrasok')) {
                $newSource = true;
                continue;
            }
            $link = self::cell($c, $r, ['link']);
            if ($link === '' || !preg_match('#^https?://#i', $link)) {
                continue;
            }
            $uploadedOld = self::key(self::cell($c, $r, ['feltöltve'])) === 'igen';
            $upload = self::cell($c, $r, ['feltöltés']);
            $no = self::cell($c, $r, ['no', 'sorszám']);
            $out[] = [
                'no' => ctype_digit($no) ? (int) $no : null,
                'source_name' => self::cell($c, $r, ['page', 'forrás']),
                'published_on' => self::date(self::cell($c, $r, ['date', 'dátum'])),
                'title' => self::cell($c, $r, ['title', 'cím']),
                'description' => self::cell($c, $r, ['desc', 'leírás']),
                'url' => $link,
                'recommendation' => self::cell($c, $r, ['javaslat']),
                'reason' => self::cell($c, $r, ['indoklás']),
                'decision' => self::DECISIONS[self::key(self::cell($c, $r, ['ügyfél döntése', 'döntés']))] ?? '',
                'uploaded' => $uploadedOld || self::key($upload) === 'feltöltve' ? 1 : 0,
                'upload_status' => self::key($upload) === 'feltöltve' ? '' : mb_substr($upload, 0, 255),
                'url_hu' => self::cell($c, $r, ['feltöltött url (hu)']),
                'url_en' => self::cell($c, $r, ['feltöltött url (en)']),
                'post_code' => self::cell($c, $r, ['poszt id']),
                'from_new_source' => $newSource ? 1 : 0,
            ];
        }
        return $out;
    }

    /** Dátum több szokásos formátumból; ismeretlennél null. */
    public static function date(string $s): ?string
    {
        $s = trim($s, " \t.");
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(\d{4})[.\-\/]\s?(\d{1,2})[.\-\/]\s?(\d{1,2})/', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        if (preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})$/', $s, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        $t = strtotime($s);
        return $t !== false && $t > 0 ? date('Y-m-d', $t) : null;
    }

    /** @param list<list<string>> $rows @return list<array<string,mixed>> */
    public static function sources(array $rows): array
    {
        $out = [];
        foreach (array_slice($rows, 1) as $r) {
            $url = trim($r[2] ?? '');
            if ($url === '') {
                continue;
            }
            $note = trim($r[5] ?? '');
            $out[] = ['name' => trim($r[0] ?? ''), 'type' => trim($r[1] ?? ''), 'url' => $url, 'topics' => trim($r[3] ?? ''),
                'active' => self::key($r[4] ?? '') === 'igen' ? 1 : 0, 'is_new' => str_contains(self::key($note), 'próbaidőszak') ? 1 : 0, 'note' => $note];
        }
        return $out;
    }

    /** @param list<list<string>> $rows @return list<array<string,mixed>> */
    public static function keywords(array $rows): array
    {
        $out = [];
        foreach (array_slice($rows, 1) as $r) {
            $kw = trim($r[0] ?? '');
            if ($kw === '') {
                continue;
            }
            $out[] = ['keyword' => $kw, 'topic' => trim($r[1] ?? ''), 'is_exclude' => str_starts_with(self::key($r[2] ?? ''), 'kizár') ? 1 : 0, 'language' => strtoupper(trim($r[3] ?? ''))];
        }
        return $out;
    }

    /** @param list<list<string>> $rows @return list<array<string,mixed>> */
    public static function posts(array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $c = self::columns($rows[0]);
        $out = [];
        foreach (array_slice($rows, 1) as $r) {
            $code = self::cell($c, $r, ['id']);
            if ($code === '') {
                continue;
            }
            $out[] = [
                'code' => $code,
                'month' => self::cell($c, $r, ['hónap']),
                'status' => self::POST_STATUSES[self::key(self::cell($c, $r, ['státusz']))] ?? 'tervezet',
                'topic' => self::cell($c, $r, ['témakör']),
                'source_articles' => self::cell($c, $r, ['forráscikkek']),
                'opening' => self::cell($c, $r, ['nyitó sor']),
                'alt_opening_1' => self::cell($c, $r, ['alternatív nyitó 1']),
                'alt_opening_2' => self::cell($c, $r, ['alternatív nyitó 2']),
                'context' => self::cell($c, $r, ['kontextus']),
                'highlights' => self::cell($c, $r, ['kiemelések']),
                'source_link' => self::cell($c, $r, ['forráslink']),
                'xperion_view' => self::cell($c, $r, ['xperion szemszög']),
                'closing' => self::cell($c, $r, ['zárás']),
                'hashtags' => self::cell($c, $r, ['hashtagek']),
                'image_ref' => self::cell($c, $r, ['kép']),
                'note' => self::cell($c, $r, ['megjegyzés']),
                'image_data' => self::cell($c, $r, ['kép adatok']),
            ];
        }
        return $out;
    }

    /** @param list<list<string>> $rows @return list<array<string,mixed>> */
    public static function ideas(array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $c = self::columns($rows[0]);
        $out = [];
        foreach (array_slice($rows, 1) as $r) {
            $topic = self::cell($c, $r, ['téma']);
            $code = self::cell($c, $r, ['id']);
            if ($topic === '' && $code === '') {
                continue;
            }
            $out[] = [
                'code' => $code !== '' ? $code : 'PO-' . substr(sha1($topic . self::cell($c, $r, ['fő üzenet'])), 0, 6),
                'submitter' => self::cell($c, $r, ['beküldő']),
                'topic' => $topic,
                'message' => self::cell($c, $r, ['fő üzenet']),
                'link' => self::cell($c, $r, ['forrás- vagy fotólink']),
                'type' => self::cell($c, $r, ['poszttípus']),
                'wanted_week' => self::cell($c, $r, ['kívánt hét']),
                'status' => self::IDEA_STATUSES[self::key(self::cell($c, $r, ['állapot']))] ?? 'uj',
                'post_code' => self::cell($c, $r, ['poszt id']),
                'note' => self::cell($c, $r, ['megjegyzés']),
            ];
        }
        return $out;
    }

    /**
     * Kulcs–érték lapok (LinkedIn szabályok, Beállítások); a fejlécsort kihagyja.
     *
     * @param list<list<string>> $rows
     * @return list<array{0:string,1:string}>
     */
    public static function pairs(array $rows, array $headerNames): array
    {
        $out = [];
        foreach ($rows as $i => $r) {
            $k = trim($r[0] ?? '');
            if ($k === '' || ($i === 0 && in_array(self::key($k), $headerNames, true))) {
                continue;
            }
            $out[] = [$k, trim($r[1] ?? '')];
        }
        return $out;
    }
}

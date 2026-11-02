<?php
declare(strict_types=1);

namespace Xperion\Collect;

/** HTML-segédek: DOM betöltése, abszolút link, hírcsatorna-felfedezés, listaoldal és cikkoldal elemzése. */
final class Html
{
    private const SKIP_PATH = '#/(tag|tags|category|categories|author|authors|page|search|kategoria|cimke)(/|$)|/(feed|rss)(/|\.xml)?$|\.(pdf|jpg|png|zip)$#i';

    public static function dom(string $html): \DOMXPath
    {
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return new \DOMXPath($doc);
    }

    public static function absolute(string $href, string $base): string
    {
        $href = trim($href);
        if ($href === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        if (!$b || empty($b['host'])) {
            return $href;
        }
        $origin = ($b['scheme'] ?? 'https') . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($href, '//')) {
            return ($b['scheme'] ?? 'https') . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/') ?: '/';
        return $origin . $dir . $href;
    }

    /** A listaoldal által hirdetett RSS/Atom cím, ha van. */
    public static function feedLink(string $html, string $base): ?string
    {
        $x = self::dom($html);
        foreach ($x->query('//link[@rel="alternate"][contains(@type,"rss") or contains(@type,"atom")]/@href') ?: [] as $a) {
            $href = trim($a->nodeValue ?? '');
            if ($href !== '' && !preg_match('#/comments/?#i', $href)) {
                return self::absolute($href, $base);
            }
        }
        return null;
    }

    /** @return list<Item> cikkjelöltek egy listaoldalról */
    public static function listItems(string $html, string $pageUrl): array
    {
        $x = self::dom($html);
        $host = self::host($pageUrl);
        $pagePath = rtrim((string) parse_url($pageUrl, PHP_URL_PATH), '/');
        $blocks = iterator_to_array($x->query('//article') ?: []);
        if (!$blocks) {
            // Cím-hivatkozások blokkjai: h2/h3/h4 linkkel, vagy linkben lévő címsor.
            foreach ($x->query('//h2[.//a[@href]] | //h3[.//a[@href]] | //h4[.//a[@href]] | //a[@href][.//h2 or .//h3 or .//h4]') ?: [] as $h) {
                $blocks[] = self::container($h);
            }
        }
        $out = [];
        foreach ($blocks as $b) {
            /** @var \DOMElement $b */
            $a = $x->query('.//h1//a[@href] | .//h2//a[@href] | .//h3//a[@href] | .//h4//a[@href]', $b)->item(0)
                ?? ($b->nodeName === 'a' ? $b : null)
                ?? $x->query('.//a[@href][.//h2 or .//h3 or .//h4]', $b)->item(0)
                ?? $x->query('.//a[@href]', $b)->item(0);
            if (!$a instanceof \DOMElement) {
                continue;
            }
            $url = self::absolute($a->getAttribute('href'), $pageUrl);
            $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');
            if (!preg_match('#^https?://#', $url) || self::host($url) !== $host || $path === '' || $path === $pagePath || preg_match(self::SKIP_PATH, $path)) {
                continue;
            }
            $heading = $x->query('.//h1 | .//h2 | .//h3 | .//h4', $b)->item(0);
            $title = FeedParser::text(($heading ?? $a)->textContent);
            if (mb_strlen($title) < 12) {
                continue;
            }
            $time = $x->query('.//time', $b)->item(0);
            $date = $time instanceof \DOMElement ? (DateText::machine($time->getAttribute('datetime')) ?? DateText::find($time->textContent)) : null;
            $date ??= DateText::find(mb_substr(self::text($b), 0, 600));
            $p = $x->query('.//p', $b)->item(0);
            $out[$url] = new Item($title, $url, $date, $p ? FeedParser::text($p->textContent, 400) : '');
        }
        return array_values($out);
    }

    /** Megjelenési dátum egy cikkoldalról (meta, JSON-LD, time). */
    public static function articleDate(string $html): ?string
    {
        $x = self::dom($html);
        foreach (['//meta[@property="article:published_time"]/@content', '//meta[@name="article:published_time"]/@content', '//meta[@itemprop="datePublished"]/@content',
            '//meta[@name="date" or @name="pubdate" or @name="publish-date" or @name="publication_date" or @name="DC.date"]/@content', '//*[@itemprop="datePublished"]/@datetime', '//time[@datetime]/@datetime'] as $q) {
            $n = $x->query($q)->item(0);
            if ($n && ($d = DateText::machine((string) $n->nodeValue)) !== null) {
                return $d;
            }
        }
        foreach ($x->query('//script[@type="application/ld+json"]') ?: [] as $s) {
            if (preg_match('/"datePublished"\s*:\s*"([^"]+)"/', $s->textContent, $m) && ($d = DateText::machine($m[1])) !== null) {
                return $d;
            }
        }
        return null;
    }

    /** Egy elem szövege, a szövegrészek között szóközzel (a textContent összeragasztja őket). */
    public static function text(\DOMNode $n): string
    {
        $parts = [];
        foreach ((new \DOMXPath($n->ownerDocument))->query('.//text()', $n) ?: [] as $t) {
            $v = trim((string) $t->nodeValue);
            if ($v !== '') {
                $parts[] = $v;
            }
        }
        return FeedParser::text(implode(' ', $parts));
    }

    public static function host(string $url): string
    {
        return preg_replace('/^www\./', '', mb_strtolower((string) parse_url($url, PHP_URL_HOST))) ?? '';
    }

    private static function container(\DOMNode $n): \DOMNode
    {
        // Felfelé lépünk, amíg a blokk egyetlen cikkhivatkozást tartalmaz (legfeljebb 3 szint).
        $best = $n;
        for ($i = 0, $cur = $n->parentNode; $i < 3 && $cur instanceof \DOMElement; $i++, $cur = $cur->parentNode) {
            $heads = (new \DOMXPath($cur->ownerDocument))->query('.//h2|.//h3|.//h4', $cur)->length;
            if ($heads > 1) {
                break;
            }
            $best = $cur;
        }
        return $best;
    }
}

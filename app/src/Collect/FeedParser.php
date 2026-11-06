<?php
declare(strict_types=1);

namespace Xperion\Collect;

/** RSS 2.0 és Atom hírcsatorna értelmezése. */
final class FeedParser
{
    public static function looksLikeFeed(string $body): bool
    {
        $head = ltrim(substr($body, 0, 600));
        return (bool) preg_match('/<(rss|feed|rdf:RDF)[\s>]/i', $head);
    }

    /** @return list<Item> */
    public static function parse(string $xml, string $baseUrl = ''): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($doc === false) {
            return [];
        }
        $items = [];
        if (isset($doc->channel->item) || isset($doc->item)) {
            foreach ($doc->channel->item ?? $doc->item as $it) {
                $dc = $it->children('http://purl.org/dc/elements/1.1/');
                $link = trim((string) $it->link) ?: trim((string) $it->guid);
                $date = (string) $it->pubDate ?: (string) $dc->date;
                $items[] = new Item(self::text((string) $it->title), Html::absolute($link, $baseUrl), DateText::machine($date), self::text((string) $it->description, 400));
            }
            return $items;
        }
        $doc->registerXPathNamespace('a', 'http://www.w3.org/2005/Atom');
        foreach ($doc->xpath('//a:entry') ?: [] as $e) {
            $e->registerXPathNamespace('a', 'http://www.w3.org/2005/Atom');
            $href = '';
            foreach ($e->xpath('a:link') ?: [] as $l) {
                $rel = (string) $l['rel'];
                if ($rel === '' || $rel === 'alternate') {
                    $href = (string) $l['href'];
                    break;
                }
            }
            $date = (string) ($e->xpath('a:published')[0] ?? '') ?: (string) ($e->xpath('a:updated')[0] ?? '');
            $sum = (string) ($e->xpath('a:summary')[0] ?? '') ?: (string) ($e->xpath('a:content')[0] ?? '');
            $items[] = new Item(self::text((string) ($e->xpath('a:title')[0] ?? '')), Html::absolute($href, $baseUrl), DateText::machine($date), self::text($sum, 400));
        }
        return $items;
    }

    public static function text(string $html, int $max = 0): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        return $max > 0 && mb_strlen($t) > $max ? rtrim(mb_substr($t, 0, $max - 1)) . '…' : $t;
    }
}

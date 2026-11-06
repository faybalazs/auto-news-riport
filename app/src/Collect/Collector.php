<?php
declare(strict_types=1);

namespace Xperion\Collect;

use Xperion\Http\HttpClient;

/**
 * Egy forrás cikkeinek begyűjtése. Sorrend: megadott RSS-cím → a listaoldal által
 * hirdetett hírcsatorna → HubSpot-blognál /rss.xml → a listaoldal elemzése.
 * Dátum nélküli cikknél (legfeljebb N darabig) a cikkoldalról olvassa a dátumot.
 */
final class Collector
{
    public function __construct(private HttpClient $http, private int $maxDateFetch = 10)
    {
    }

    /** @return array{items: list<Item>, method: string, error: ?string} */
    public function collect(string $url, string $type = ''): array
    {
        try {
            $isFeedUrl = (bool) preg_match('#(rss|atom|feed)(\.xml)?/?$|\.xml$#i', (string) parse_url($url, PHP_URL_PATH)) || stripos($type, 'rss') !== false;
            $body = $this->get($url);
            if (FeedParser::looksLikeFeed($body)) {
                return ['items' => FeedParser::parse($body, $url), 'method' => 'RSS', 'error' => null];
            }
            if ($isFeedUrl) {
                return ['items' => [], 'method' => 'RSS', 'error' => 'A megadott cím nem hírcsatorna (RSS/Atom).'];
            }
            if (($feed = Html::feedLink($body, $url)) !== null && ($items = $this->tryFeed($feed)) !== null) {
                return ['items' => $items, 'method' => 'RSS (az oldal hirdeti)', 'error' => null];
            }
            if ((stripos($type . ' ' . $body, 'hubspot') !== false || stripos($body, 'hs-blog') !== false)
                && ($items = $this->tryFeed(rtrim($url, '/') . '/rss.xml')) !== null) {
                return ['items' => $items, 'method' => 'RSS (HubSpot)', 'error' => null];
            }
            $items = Html::listItems($body, $url);
            $this->fillDates($items);
            return ['items' => $items, 'method' => 'listaoldal', 'error' => $items ? null : 'A listaoldalon nem találtam cikkhivatkozást.'];
        } catch (\Throwable $e) {
            return ['items' => [], 'method' => '–', 'error' => $e->getMessage()];
        }
    }

    /** @return list<Item>|null */
    private function tryFeed(string $url): ?array
    {
        try {
            $body = $this->get($url);
        } catch (\Throwable) {
            return null;
        }
        return FeedParser::looksLikeFeed($body) ? FeedParser::parse($body, $url) : null;
    }

    /** @param list<Item> $items */
    private function fillDates(array $items): void
    {
        $n = 0;
        foreach ($items as $it) {
            if ($it->date !== null || $n >= $this->maxDateFetch) {
                continue;
            }
            $n++;
            try {
                $it->date = Html::articleDate($this->get($it->url));
            } catch (\Throwable) {
                // a cikk dátum nélkül marad, a kimutatás jelzi
            }
        }
    }

    private function get(string $url): string
    {
        $r = $this->http->request('GET', $url, ['Accept' => 'text/html,application/rss+xml,application/atom+xml,application/xml;q=0.9,*/*;q=0.8', 'Accept-Language' => 'en,hu;q=0.8']);
        if (!$r->ok()) {
            throw new \RuntimeException('A forrás nem érhető el (HTTP ' . $r->status . ').');
        }
        return $r->body;
    }
}

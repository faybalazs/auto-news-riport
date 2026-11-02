<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Collect\Collector;
use Xperion\Collect\DateText;
use Xperion\Collect\CollectService;
use Xperion\Tests\FakeWeb;

final class CollectParsingTest extends TestCase
{
    public const RSS = '<?xml version="1.0"?><rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/"><channel><title>Blog</title>
        <item><title>How a bank cut wait times</title><link>https://www.vemcogroup.com/blog/bank-wait?utm_source=rss</link><pubDate>Thu, 02 Oct 2026 08:00:00 +0000</pubDate><description><![CDATA[<p>Case <b>study</b> about waiting</p>]]></description></item>
        <item><title>September news</title><link>https://www.vemcogroup.com/blog/sept</link><pubDate>Tue, 30 Sep 2026 23:30:00 +0000</pubDate></item>
        <item><title>Late night post</title><link>https://www.vemcogroup.com/blog/late</link><dc:date>2026-10-31T23:30:00Z</dc:date></item>
        </channel></rss>';

    public function testDates(): void
    {
        self::assertSame('2026-10-02', DateText::machine('Thu, 02 Oct 2026 08:00:00 +0000'));
        self::assertSame('2026-10-01', DateText::machine('Tue, 30 Sep 2026 23:30:00 +0000'), 'budapesti idő szerint már október');
        self::assertSame('2026-11-01', DateText::machine('2026-10-31T23:30:00Z'));
        self::assertSame('2026-10-12', DateText::find('Posted on October 12, 2026 by Anna'));
        self::assertSame('2026-10-12', DateText::find('12 Oct 2026'));
        self::assertSame('2026-10-05', DateText::find('Megjelent: 2026. október 5.'));
        self::assertSame('2026-10-05', DateText::find('2026. 10. 05.'));
        self::assertSame('2026-10-25', DateText::find('25/10/2026'));
        self::assertNull(DateText::find('nincs benne dátum 123'));
    }

    public function testRssAndAtom(): void
    {
        $c = new Collector(new FakeWeb(['https://www.vemcogroup.com/blog/rss.xml' => self::RSS]));
        $r = $c->collect('https://www.vemcogroup.com/blog/rss.xml');
        self::assertSame('RSS', $r['method']);
        self::assertCount(3, $r['items']);
        self::assertSame('Case study about waiting', $r['items'][0]->description);

        $atom = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Atom post title here</title><link rel="alternate" href="/news/a"/><published>2026-10-07T10:00:00+02:00</published><summary>Sum</summary></entry></feed>';
        $r = (new Collector(new FakeWeb(['https://x.com/feed' => $atom])))->collect('https://x.com/feed');
        self::assertSame('https://x.com/news/a', $r['items'][0]->url);
        self::assertSame('2026-10-07', $r['items'][0]->date);
    }

    public function testListPageAdvertisingFeedAndHubspot(): void
    {
        $web = new FakeWeb([
            'https://q.com/blog' => '<html><head><link rel="alternate" type="application/rss+xml" href="/blog/rss.xml"></head><body>x</body></html>',
            'https://q.com/blog/rss.xml' => self::RSS,
            'https://hs.com/blog' => '<html><body class="hs-blog-listing"><div>nincs cikk</div></body></html>',
            'https://hs.com/blog/rss.xml' => self::RSS,
        ]);
        self::assertSame('RSS (az oldal hirdeti)', (new Collector($web))->collect('https://q.com/blog')['method']);
        self::assertSame('RSS (HubSpot)', (new Collector($web))->collect('https://hs.com/blog', 'HubSpot blog')['method']);
    }

    public function testGenericListPageWithArticlePageDates(): void
    {
        $list = '<html><body><main>
            <article><h2><a href="/news/kiosk-ordering-lifts-basket">Kiosk ordering lifts basket size</a></h2><time datetime="2026-10-10">Oct 10</time><p>Short summary.</p></article>
            <article><h3><a href="https://acrelec.com/news/drive-thru-timers">Drive-thru timers: what the data says</a></h3><span>October 19, 2026</span></article>
            <article><h3><a href="/news/accessibility-in-self-service">Accessibility in self-service design</a></h3></article>
            <article><h3><a href="/category/news">Category page should be skipped</a></h3></article>
            <article><h3><a href="https://other.com/x/y">External link should be skipped too</a></h3></article>
            </main></body></html>';
        $web = new FakeWeb([
            'https://acrelec.com/news' => $list,
            'https://acrelec.com/news/accessibility-in-self-service' => '<html><head><meta property="article:published_time" content="2026-10-26T09:00:00+02:00"></head></html>',
        ]);
        $r = (new Collector($web))->collect('https://acrelec.com/news');
        self::assertSame('listaoldal', $r['method']);
        self::assertSame(['2026-10-10', '2026-10-19', '2026-10-26'], array_map(static fn ($i) => $i->date, $r['items']));
        self::assertSame('Short summary.', $r['items'][0]->description);
        self::assertSame('https://acrelec.com/news/kiosk-ordering-lifts-basket', $r['items'][0]->url);
    }

    public function testHeadingListWithoutArticleTags(): void
    {
        $list = '<html><body><div class="posts"><div class="card"><h3><a href="/blog/one-long-title-here">One long title here for test</a></h3><p>12 Oct 2026 – summary</p></div>
            <div class="card"><h3><a href="/blog/two-long-title-here">Two long title here for test</a></h3><p>15 Oct 2026</p></div></div></body></html>';
        $r = (new Collector(new FakeWeb(['https://w.com/blog' => $list])))->collect('https://w.com/blog');
        self::assertCount(2, $r['items']);
        self::assertSame(['2026-10-12', '2026-10-15'], array_map(static fn ($i) => $i->date, $r['items']));
    }

    public function testErrorsAreReportedNotThrown(): void
    {
        $r = (new Collector(new FakeWeb([])))->collect('https://nincs.hu/blog');
        self::assertSame([], $r['items']);
        self::assertStringContainsString('HTTP 404', (string) $r['error']);
    }

    public function testExclusionAndMonthHelpers(): void
    {
        self::assertSame('IPO', CollectService::excludedBy('Company plans IPO next year', ['tax', 'IPO']));
        self::assertNull(CollectService::excludedBy('Taxonomy of queues', ['tax']), 'egész szóra illeszt');
        self::assertSame('M&A', CollectService::excludedBy('Big M&A deal', ['M&A']));
        self::assertSame(['2026-10-01', '2026-10-31'], CollectService::monthRange('2610'));
        self::assertSame(['2027-02-01', '2027-02-28'], CollectService::monthRange('2702'));
        self::assertSame('2610', CollectService::previousMonth(new \DateTimeImmutable('2026-11-01 06:00')));
    }
}

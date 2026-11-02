<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Import\SheetParser;
use Xperion\Import\Url;

final class SheetParserTest extends TestCase
{
    public function testOldMonthlyFormat(): void
    {
        $rows = [['No', 'Page', 'Date', 'Title', 'Desc', 'Link', 'Feltöltve'],
            ['1', 'Vemco', '2024.08.05', 'A', 'a leírás', 'https://vemco.com/a', 'Igen'],
            ['2', 'Qmatic', '', 'B', '', 'https://qmatic.com/b', ''],
            ['', '', '', 'link nélkül', '', '', '']];
        $a = SheetParser::monthly($rows);
        self::assertCount(2, $a);
        self::assertSame(1, $a[0]['uploaded']);
        self::assertSame('2024-08-05', $a[0]['published_on']);
        self::assertSame(0, $a[1]['uploaded']);
        self::assertNull($a[1]['published_on']);
    }

    public function testNewMonthlyFormatWithSummaryAndNewSourcesBlock(): void
    {
        $rows = [['Vezetői összefoglaló: 2026. szeptember'], ['Cikkek száma: 26'], ['Részletek: …'],
            ['No', 'Page', 'Date', 'Title', 'Desc', 'Link', 'Javaslat', 'Indoklás', 'Ügyfél döntése', 'Feltöltés', 'Feltöltött URL (HU)', 'Feltöltött URL (EN)', 'Poszt ID'],
            ['1', 'Vemco', '15/09/2026', 'A', 'x', 'https://vemco.com/a', 'Erősen javasolt', 'mert', 'Mehet', 'Feltöltve', 'https://xperion.hu/a', 'https://xperion.hu/en/a', ''],
            ['2', 'Qmatic', 'Sep 20, 2026', 'B', 'y', 'https://qmatic.com/b', 'Javasolt', '', 'LinkedIn poszt', '', '', '', 'LI-2609-01'],
            ['Új források (próbaidőszak, Acrelec)'],
            ['3', 'Acrelec', '2026-09-21', 'C', 'z', 'https://acrelec.com/c', 'Nem javasolt', '', 'Nem kell', 'Hiba: időtúllépés', '', '', '']];
        $a = SheetParser::monthly($rows);
        self::assertCount(3, $a);
        self::assertSame(['mehet', 'linkedin', 'nemkell'], array_column($a, 'decision'));
        self::assertSame([1, 0, 0], array_column($a, 'uploaded'));
        self::assertSame([0, 0, 1], array_column($a, 'from_new_source'));
        self::assertSame('2026-09-15', $a[0]['published_on']);
        self::assertSame('2026-09-20', $a[1]['published_on']);
        self::assertSame('LI-2609-01', $a[1]['post_code']);
        self::assertSame('Hiba: időtúllépés', $a[2]['upload_status']);
        self::assertSame('https://xperion.hu/en/a', $a[0]['url_en']);
    }

    public function testUrlNormalization(): void
    {
        self::assertSame(Url::hash('https://www.Vemco.com/blog/a/?utm_source=x&b=2#top'), Url::hash('http://vemco.com/blog/a?b=2'));
        self::assertNotSame(Url::hash('https://vemco.com/a?id=1'), Url::hash('https://vemco.com/a?id=2'));
        self::assertSame('1MuPaB3VG2LAkHntWJy7FmjTyzzDSx9thgeKjLkZSqPA', Url::spreadsheetId('https://docs.google.com/spreadsheets/d/1MuPaB3VG2LAkHntWJy7FmjTyzzDSx9thgeKjLkZSqPA/edit#gid=0'));
        self::assertSame('', Url::spreadsheetId('nem link'));
    }

    public function testPostsIdeasAndPairs(): void
    {
        $p = SheetParser::posts([['ID', 'Hónap', 'Státusz', 'Témakör', 'Forráscikkek', 'Nyitó sor', 'Alternatív nyitó 1', 'Alternatív nyitó 2', 'Kontextus', 'Kiemelések', 'Forráslink', 'Xperion szemszög', 'Zárás', 'Hashtagek', 'Teljes poszt', 'Kép', 'Megjegyzés'],
            ['LI-2609-01', '2609', 'Megjelent', 'Bank', '1 cikk', 'Nyitó', '', '', 'K', '– a', 'https://x', 'Sz', 'Z', '#a #b', '=TEXTJOIN(…)', '', 'minta']]);
        self::assertSame('megjelent', $p[0]['status']);
        self::assertSame('Nyitó', $p[0]['opening']);
        self::assertArrayNotHasKey('full', $p[0]);
        $i = SheetParser::ideas([['ID', 'Beküldő', 'Téma', 'Fő üzenet', 'Forrás- vagy fotólink', 'Poszttípus', 'Kívánt hét', 'Állapot'], ['', 'Emi', 'Konferencia', 'Ott voltunk', '', 'rendezvény', '46. hét', 'Új']]);
        self::assertStringStartsWith('PO-', $i[0]['code']);
        self::assertSame('uj', $i[0]['status']);
        self::assertSame([['Cég', 'Xperion'], ['Hossz', '800']], SheetParser::pairs([['Megnevezés', 'Érték'], ['Cég', 'Xperion'], ['', 'üres'], ['Hossz', '800']], ['megnevezés']));
    }
}

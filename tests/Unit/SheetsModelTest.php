<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Sheets\Keyword;
use Xperion\Sheets\SheetNames;
use Xperion\Sheets\Settings;
use Xperion\Sheets\Source;
use Xperion\Tests\SheetsFixture;

final class SheetsModelTest extends TestCase
{
    public function testSourcesParseActiveAndTrial(): void
    {
        $rows = [
            ['Forrás', 'Típus', 'Figyelt oldal', 'Fő témák', 'Aktív', 'Megjegyzés'],
            ['Vemco', 'blog', 'https://blog.vemco.com', 'sorkezelés', 'igen', ''],
            ['Új', 'hír', 'https://uj.example', '', 'Igen', 'próbaidőszak 2026. 11-ig'],
            ['Régi', 'hír', 'https://regi.example', '', 'nem'],
            ['Üres URL', 'hír', '', '', 'igen'],
        ];
        $s = Source::fromRows($rows);
        self::assertCount(3, $s);
        self::assertTrue($s[0]->active);
        self::assertFalse($s[0]->isTrial());
        self::assertTrue($s[1]->active && $s[1]->isTrial());
        self::assertFalse($s[2]->active);
    }

    public function testKeywordsDetectExclusion(): void
    {
        $k = Keyword::fromRows([['Kulcsszó', 'Témakör', 'Típus', 'Nyelv'], ['queue', 'sor', 'Pozitív', 'en'], ['IPO', 'pénzügy', 'Kizáró', 'EN'], ['', '', '', '']]);
        self::assertCount(2, $k);
        self::assertFalse($k[0]->exclude);
        self::assertSame('EN', $k[0]->language);
        self::assertTrue($k[1]->exclude);
    }

    public function testMonthlyNames(): void
    {
        self::assertSame('2610', SheetNames::monthly(new \DateTimeImmutable('2026-10-15')));
        self::assertTrue(SheetNames::isMonthly('2609'));
        self::assertFalse(SheetNames::isMonthly('2613'));
        self::assertFalse(SheetNames::isMonthly('Napló'));
    }

    public function testSettingsParseSkipsHeaderAndKeepsRows(): void
    {
        $p = Settings::parse([['Kulcs', 'Érték'], ['ugyfel_email', ' a@b.hu '], [], ['aktualis_honap', '2611']]);
        self::assertSame(['row' => 2, 'value' => 'a@b.hu'], $p['ugyfel_email']);
        self::assertSame(4, $p['aktualis_honap']['row']);
    }

    public function testSettingsSetUpdatesExistingOrAppends(): void
    {
        $f = new SheetsFixture([SheetNames::SETTINGS], [SheetNames::SETTINGS => [['Kulcs', 'Érték'], ['honap_email_allapot', 'Jóváhagyásra vár']]]);
        $s = new Settings($f->client);
        self::assertSame('Jóváhagyásra vár', $s->get('honap_email_allapot'));
        $s->set('honap_email_allapot', 'Mehet');
        $s->set('uj_kulcs', 'x');
        [$put, $append] = $f->writes();
        self::assertSame('PUT', $put['method']);
        self::assertStringContainsString(rawurlencode("'Beállítások'!B2"), $put['url']);
        self::assertStringContainsString(':append', $append['url']);
    }
}

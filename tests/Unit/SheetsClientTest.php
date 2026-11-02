<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Google\SheetsClient;
use Xperion\Http\HttpException;
use Xperion\Tests\FakeHttpClient;
use Xperion\Tests\SheetsFixture;

final class SheetsClientTest extends TestCase
{
    public function testRangeQuotesSheetNames(): void
    {
        self::assertSame("'Napló'!A:D", SheetsClient::range('Napló', 'A:D'));
        self::assertSame("'Ügyfél ''A'''", SheetsClient::range("Ügyfél 'A'"));
    }

    public function testSheetsAndValues(): void
    {
        $f = new SheetsFixture(['2609', 'Napló'], ['Napló' => [['Időbélyeg'], ['x', 2]]]);
        self::assertSame(['2609' => 100, 'Napló' => 101], $f->client->sheets());
        self::assertSame([['Időbélyeg'], ['x', '2']], $f->client->getValues(SheetsClient::range('Napló', 'A:D')));
        self::assertSame('Bearer tok', $f->http->requests[0]['headers']['Authorization']);
    }

    public function testAppendSendsUserEnteredRows(): void
    {
        $f = new SheetsFixture([]);
        $f->client->appendValues(SheetsClient::range('Napló', 'A:D'), [['a', 'Hiba']]);
        $req = $f->writes()[0];
        self::assertStringContainsString('valueInputOption=USER_ENTERED', $req['url']);
        self::assertSame(['values' => [['a', 'Hiba']]], json_decode((string) $req['body'], true));
    }

    public function testForbiddenGivesSharingHint(): void
    {
        $http = (new FakeHttpClient())->on('GET', '#.#', 403, ['error' => ['message' => 'The caller does not have permission']]);
        $client = new SheetsClient('X', $http, static fn () => 't');
        try {
            $client->sheets();
            self::fail('kivételt vártunk');
        } catch (HttpException $e) {
            self::assertStringContainsString('HTTP 403: The caller does not have permission', $e->getMessage());
            self::assertStringContainsString('szolgáltatásfiókkal', $e->getMessage());
        }
    }
}

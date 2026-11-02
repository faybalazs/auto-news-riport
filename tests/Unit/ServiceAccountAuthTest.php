<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Google\ServiceAccountAuth;
use Xperion\Http\HttpException;
use Xperion\Tests\FakeHttpClient;

final class ServiceAccountAuthTest extends TestCase
{
    private static string $privatePem;
    private static string $publicPem;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        self::$privatePem = $pem;
        self::$publicPem = openssl_pkey_get_details($key)['key'];
    }

    private function json(): string
    {
        return (string) json_encode(['client_email' => 'xp@proj.iam.gserviceaccount.com', 'private_key' => self::$privatePem, 'token_uri' => 'https://oauth2.googleapis.com/token']);
    }

    public function testJwtIsValidRs256(): void
    {
        $auth = new ServiceAccountAuth($this->json(), new FakeHttpClient());
        [$h, $c, $s] = explode('.', $auth->jwt(1_700_000_000));
        $dec = static fn ($p) => base64_decode(strtr($p, '-_', '+/'));
        self::assertSame(['alg' => 'RS256', 'typ' => 'JWT'], json_decode($dec($h), true));
        $claims = json_decode($dec($c), true);
        self::assertSame('xp@proj.iam.gserviceaccount.com', $claims['iss']);
        self::assertSame(ServiceAccountAuth::SCOPE_SHEETS, $claims['scope']);
        self::assertSame(1_700_003_600, $claims['exp']);
        self::assertSame(1, openssl_verify("$h.$c", $dec($s), self::$publicPem, OPENSSL_ALGO_SHA256));
    }

    public function testTokenIsCachedUntilExpiry(): void
    {
        $http = (new FakeHttpClient())->on('POST', '#oauth2#', 200, ['access_token' => 'T1', 'expires_in' => 3600]);
        $cache = tempnam(sys_get_temp_dir(), 'tok');
        unlink($cache);
        $now = 1000;
        $clock = static function () use (&$now): int { return $now; };
        $auth = new ServiceAccountAuth($this->json(), $http, $cache, ServiceAccountAuth::SCOPE_SHEETS, $clock);

        self::assertSame('T1', $auth->accessToken());
        self::assertSame('T1', $auth->accessToken());
        self::assertCount(1, $http->requests, 'a második hívás a gyorsítótárból jön');
        self::assertStringContainsString('grant_type=urn%3Aietf', (string) $http->requests[0]['body']);

        $now += 3600; // lejárt
        $auth->accessToken();
        self::assertCount(2, $http->requests);
    }

    public function testErrorIsReadable(): void
    {
        $http = (new FakeHttpClient())->on('POST', '#oauth2#', 400, ['error' => 'invalid_grant', 'error_description' => 'Invalid JWT Signature.']);
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Invalid JWT Signature.');
        (new ServiceAccountAuth($this->json(), $http))->accessToken();
    }

    public function testRejectsIncompleteJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ServiceAccountAuth('{"client_email":"x"}', new FakeHttpClient());
    }
}

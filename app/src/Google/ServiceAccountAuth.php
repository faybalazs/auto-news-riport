<?php
declare(strict_types=1);

namespace Xperion\Google;

use Xperion\Http\HttpClient;
use Xperion\Http\HttpException;

/**
 * Google szolgáltatásfiókos hitelesítés Composer nélkül:
 * RS256-tal aláírt JWT (OpenSSL) → hozzáférési token, fájlba gyorsítótárazva.
 */
final class ServiceAccountAuth
{
    public const SCOPE_SHEETS = 'https://www.googleapis.com/auth/spreadsheets';

    private string $clientEmail;
    private string $privateKey;
    private string $tokenUri;
    /** @var callable():int */
    private $clock;

    public function __construct(
        string $serviceAccountJson,
        private HttpClient $http,
        private ?string $cacheFile = null,
        private string $scope = self::SCOPE_SHEETS,
        ?callable $clock = null,
    ) {
        $data = json_decode($serviceAccountJson, true);
        if (!is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            throw new \InvalidArgumentException('A szolgáltatásfiók JSON hiányos (client_email vagy private_key nincs benne).');
        }
        $this->clientEmail = (string) $data['client_email'];
        $this->privateKey = (string) $data['private_key'];
        $this->tokenUri = (string) ($data['token_uri'] ?? 'https://oauth2.googleapis.com/token');
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function clientEmail(): string
    {
        return $this->clientEmail;
    }

    public function accessToken(): string
    {
        $now = ($this->clock)();
        $cached = $this->readCache();
        if ($cached !== null && $cached['expires_at'] > $now + 60) {
            return $cached['token'];
        }

        try {
            $response = $this->http->request('POST', $this->tokenUri, [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ], http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->jwt($now),
            ]));
        } catch (HttpException $e) {
            throw new HttpException('Google-hitelesítés sikertelen: ' . $e->getMessage(), 0, $e);
        }
        if (!$response->ok()) {
            throw new HttpException('Google-hitelesítés sikertelen: ' . $response->errorMessage(), $response->status);
        }
        $data = $response->json();
        $token = (string) ($data['access_token'] ?? '');
        if ($token === '') {
            throw new HttpException('Google-hitelesítés: nincs access_token a válaszban.');
        }
        $this->writeCache($token, $now + (int) ($data['expires_in'] ?? 3600));
        return $token;
    }

    public function jwt(int $now): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $this->clientEmail,
            'scope' => $this->scope,
            'aud' => $this->tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ];
        $input = self::b64(json_encode($header, JSON_THROW_ON_ERROR)) . '.' . self::b64(json_encode($claims, JSON_THROW_ON_ERROR));
        $key = openssl_pkey_get_private($this->privateKey);
        if ($key === false) {
            throw new \RuntimeException('A szolgáltatásfiók privát kulcsa nem olvasható.');
        }
        $signature = '';
        if (!openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('A JWT aláírása sikertelen.');
        }
        return $input . '.' . self::b64($signature);
    }

    public static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** @return array{token:string, expires_at:int}|null */
    private function readCache(): ?array
    {
        if ($this->cacheFile === null || !is_file($this->cacheFile)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($this->cacheFile), true);
        if (!is_array($data) || ($data['email'] ?? '') !== $this->clientEmail || ($data['scope'] ?? '') !== $this->scope) {
            return null;
        }
        return ['token' => (string) $data['token'], 'expires_at' => (int) $data['expires_at']];
    }

    private function writeCache(string $token, int $expiresAt): void
    {
        if ($this->cacheFile === null) {
            return;
        }
        $json = json_encode(['email' => $this->clientEmail, 'scope' => $this->scope, 'token' => $token, 'expires_at' => $expiresAt]);
        @file_put_contents($this->cacheFile, (string) $json, LOCK_EX);
        @chmod($this->cacheFile, 0600);
    }
}

<?php
declare(strict_types=1);

namespace Xperion\Http;

/**
 * cURL-alapú kliens újrapróbálással: hálózati hiba, 429 és 5xx esetén
 * exponenciális várakozással (1 s, 2 s, 4 s) legfeljebb 3-szor próbál újra.
 */
final class CurlHttpClient implements HttpClient
{
    /** @var callable(int):void */
    private $sleeper;

    public function __construct(
        private int $maxRetries = 3,
        private int $timeoutSeconds = 60,
        ?callable $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? static function (int $ms): void { usleep($ms * 1000); };
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $attempt = 0;
        while (true) {
            try {
                $response = $this->once($method, $url, $headers, $body);
                if (!self::retryable($response->status) || $attempt >= $this->maxRetries) {
                    return $response;
                }
            } catch (HttpException $e) {
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }
            }
            ($this->sleeper)(1000 * (2 ** $attempt));
            $attempt++;
        }
    }

    public static function retryable(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    /** @param array<string,string> $headers */
    private function once(string $method, string $url, array $headers, ?string $body): HttpResponse
    {
        $ch = curl_init($url);
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => 'XperionAutomatizalas/0.1 (+https://xperion.hu)',
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $result = curl_exec($ch);
        if ($result === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new HttpException('Hálózati hiba: ' . $err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return new HttpResponse($status, (string) $result);
    }
}

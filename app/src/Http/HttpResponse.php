<?php
declare(strict_types=1);

namespace Xperion\Http;

final class HttpResponse
{
    public function __construct(public readonly int $status, public readonly string $body)
    {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** @return array<mixed> */
    public function json(): array
    {
        $data = json_decode($this->body, true);
        if (!is_array($data)) {
            throw new HttpException('Érvénytelen JSON-válasz (HTTP ' . $this->status . ').', $this->status);
        }
        return $data;
    }

    /** Rövid, naplózható hibaszöveg a válaszból. */
    public function errorMessage(): string
    {
        $data = json_decode($this->body, true);
        $msg = is_array($data) ? ($data['error']['message'] ?? $data['error_description'] ?? $data['error'] ?? null) : null;
        if (!is_string($msg) || $msg === '') {
            $msg = mb_substr(trim(strip_tags($this->body)), 0, 200);
        }
        return 'HTTP ' . $this->status . ($msg !== '' ? ': ' . $msg : '');
    }
}

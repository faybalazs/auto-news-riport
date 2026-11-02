<?php
declare(strict_types=1);

namespace Xperion\Mail;

/** Az SMTP-kapcsolat alsó rétege (tesztben helyettesíthető). */
interface SmtpConnection
{
    public function open(string $host, int $port, bool $ssl, int $timeout): void;

    public function readLine(): string;

    public function write(string $data): void;

    public function startTls(): void;

    public function close(): void;
}

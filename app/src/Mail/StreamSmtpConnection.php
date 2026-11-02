<?php
declare(strict_types=1);

namespace Xperion\Mail;

/** SMTP-kapcsolat PHP-folyammal (stream_socket_client), SSL-lel vagy STARTTLS-sel. */
final class StreamSmtpConnection implements SmtpConnection
{
    /** @var resource|null */
    private $fp = null;

    public function open(string $host, int $port, bool $ssl, int $timeout): void
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $fp = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if ($fp === false) {
            throw new MailException("Nem sikerült kapcsolódni az SMTP-szerverhez ({$host}:{$port}): " . ($errstr !== '' ? $errstr : 'ismeretlen hiba') . '. Ellenőrizze a config.php smtp részét (host, port, secure).');
        }
        stream_set_timeout($fp, $timeout);
        $this->fp = $fp;
    }

    public function readLine(): string
    {
        $line = $this->fp ? fgets($this->fp, 2048) : false;
        if ($line === false) {
            throw new MailException('Az SMTP-szerver nem válaszolt (időtúllépés vagy megszakadt kapcsolat).');
        }
        return rtrim($line, "\r\n");
    }

    public function write(string $data): void
    {
        if (!$this->fp || @fwrite($this->fp, $data) === false) {
            throw new MailException('Az SMTP-kapcsolat megszakadt küldés közben.');
        }
    }

    public function startTls(): void
    {
        $ok = $this->fp ? @stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT) : false;
        if ($ok !== true) {
            throw new MailException('A titkosított kapcsolat (STARTTLS) nem jött létre. Próbálja a secure: ssl, port: 465 beállítást.');
        }
    }

    public function close(): void
    {
        if ($this->fp) {
            @fclose($this->fp);
        }
        $this->fp = null;
    }
}

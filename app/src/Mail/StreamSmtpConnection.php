<?php
declare(strict_types=1);

namespace Xperion\Mail;

/** SMTP-kapcsolat PHP-folyammal (stream_socket_client), SSL-lel vagy STARTTLS-sel. */
final class StreamSmtpConnection implements SmtpConnection
{
    /** @var resource|null */
    private $fp = null;
    /** A szerver tanúsítványának nevei (CN, SAN), ha lekérhető volt. @var list<string> */
    public array $certNames = [];

    /**
     * @param bool $verify a tanúsítvány ellenőrzése (alapból igen; a config.php smtp.verify_peer => false kapcsolja ki)
     * @param string $peerName a tanúsítványon elvárt név, ha eltér a hosttól (smtp.peer_name)
     */
    public function __construct(private bool $verify = true, private string $peerName = '')
    {
    }

    public function open(string $host, int $port, bool $ssl, int $timeout): void
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => $this->verify, 'verify_peer_name' => $this->verify, 'allow_self_signed' => !$this->verify,
            'SNI_enabled' => true, 'peer_name' => $this->peerName !== '' ? $this->peerName : $host, 'capture_peer_cert' => true]]);
        $warnings = [];
        set_error_handler(static function (int $no, string $msg) use (&$warnings): bool {
            $warnings[] = preg_replace('/^stream_socket_client\(\): /', '', $msg);
            return true;
        });
        try {
            $fp = stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        } finally {
            restore_error_handler();
        }
        if ($fp === false) {
            throw new MailException("Nem sikerült kapcsolódni az SMTP-szerverhez ({$host}:{$port}): " . self::explain($errstr, $warnings));
        }
        $this->captureCert($fp);
        stream_set_timeout($fp, $timeout);
        $this->fp = $fp;
    }

    /** A PHP/OpenSSL hibaüzenetéből érthető magyar ok. @param list<string> $warnings */
    public static function explain(string $errstr, array $warnings): string
    {
        $all = trim($errstr . ' ' . implode(' ', $warnings));
        return match (true) {
            (bool) preg_match('/did not match expected CN|peer certificate|certificate verify failed|self[- ]signed/i', $all) => 'a szerver tanúsítványa nem erre a névre szól, vagy nem hiteles (' . self::short($all) . '). Megoldás: a tanúsítványon szereplő gépnevet írja a host-ba, vagy a config.php smtp részébe: \'verify_peer\' => false.',
            (bool) preg_match('/getaddrinfo|Name or service not known|php_network_getaddresses|No address associated/i', $all) => 'a gépnév nem található (' . self::short($all) . '). Ellenőrizze a host értékét, vagy próbálja: localhost.',
            (bool) preg_match('/Connection refused/i', $all) => 'a szerver ezen a porton elutasította a kapcsolatot. Próbálja a másik portot (465 + ssl, vagy 587 + tls).',
            (bool) preg_match('/timed out|Operation timed out/i', $all) => 'időtúllépés: a tárhely valószínűleg nem engedi a kimenő kapcsolatot ezen a porton. Próbálja: host => localhost, vagy a 587-es portot.',
            (bool) preg_match('/SSL|TLS|handshake|wrong version/i', $all) => 'a titkosított kapcsolat (SSL) nem jött létre (' . self::short($all) . '). Próbálja a 587-es portot tls-sel, vagy: \'verify_peer\' => false.',
            $all === '' => 'a titkosított kapcsolat valószínűleg a tanúsítvány miatt bukott el (a PHP nem adott részletet). Használja a Vezérlőpult „SMTP-kapcsolat ellenőrzése” gombját.',
            default => self::short($all),
        };
    }

    private static function short(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? $s);
        return mb_strlen($s) > 220 ? mb_substr($s, 0, 219) . '…' : $s;
    }

    /** @param resource $fp */
    private function captureCert($fp): void
    {
        $cert = stream_context_get_params($fp)['options']['ssl']['peer_certificate'] ?? null;
        if ($cert === null) {
            return;
        }
        $info = openssl_x509_parse($cert) ?: [];
        $names = [];
        if (!empty($info['subject']['CN'])) {
            $names[] = (string) $info['subject']['CN'];
        }
        foreach (explode(',', (string) ($info['extensions']['subjectAltName'] ?? '')) as $san) {
            $san = trim(str_replace('DNS:', '', $san));
            if ($san !== '') {
                $names[] = $san;
            }
        }
        $this->certNames = array_values(array_unique($names));
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
        if ($ok === true && $this->fp) {
            $this->captureCert($this->fp);
        }
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

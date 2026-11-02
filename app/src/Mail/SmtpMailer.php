<?php
declare(strict_types=1);

namespace Xperion\Mail;

/**
 * Minimális SMTP-küldő Composer nélkül: SSL (465) vagy STARTTLS (587), AUTH PLAIN/LOGIN,
 * multipart/alternative (sima szöveg + HTML), UTF-8, base64. Csak küld; piszkozatot nem ment.
 */
final class SmtpMailer implements Mailer
{
    private const TIMEOUT = 20;

    /** @var array<string,mixed> */
    private array $cfg;

    /** @param array<string,mixed> $cfg config.php smtp része */
    public function __construct(array $cfg, private SmtpConnection $conn = new StreamSmtpConnection())
    {
        $this->cfg = self::normalize($cfg);
    }

    /**
     * A beállítás egységesítése: elterjedt kulcsnevek is jók (server, username, password, encryption, from),
     * és ha nincs külön feladó, a belépési név (ha e-mail-cím) lesz a feladó.
     *
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    public static function normalize(array $cfg): array
    {
        $out = [];
        foreach ($cfg as $k => $v) {
            $out[strtolower(trim((string) $k))] = is_scalar($v) ? trim((string) $v) : $v;
        }
        foreach (['host' => ['server', 'smtp_host', 'hostname'], 'user' => ['username', 'smtp_user', 'login'], 'pass' => ['password', 'smtp_pass'], 'secure' => ['encryption', 'smtp_secure'],
            'port' => ['smtp_port'], 'from_email' => ['from', 'from_address', 'sender', 'email'], 'from_name' => ['name', 'sender_name']] as $key => $aliases) {
            foreach ($aliases as $al) {
                if (($out[$key] ?? '') === '' && ($out[$al] ?? '') !== '') {
                    $out[$key] = $out[$al];
                }
            }
        }
        if (($out['from_email'] ?? '') === '' && Message::validAddress((string) ($out['user'] ?? ''))) {
            $out['from_email'] = $out['user'];
        }
        $out['secure'] = strtolower((string) ($out['secure'] ?? ''));
        if ($out['secure'] === '' || !in_array($out['secure'], ['ssl', 'tls', 'none'], true)) {
            $out['secure'] = in_array($out['secure'], ['starttls'], true) || (int) ($out['port'] ?? 0) === 587 ? 'tls' : ((int) ($out['port'] ?? 0) === 25 ? 'none' : 'ssl');
        }
        return $out;
    }

    /**
     * Mi hiányzik a küldéshez (a felületen ezt mutatjuk). Üres lista = rendben.
     *
     * @param array<string,mixed> $cfg
     * @return list<string>
     */
    public static function problems(array $cfg): array
    {
        $c = self::normalize($cfg);
        $p = [];
        if (!$cfg) {
            return ['a config.php-ban nincs smtp rész (vagy nem a betöltött config-fájlban van)'];
        }
        if (($c['host'] ?? '') === '') {
            $p[] = 'hiányzik a host (a levelezőszerver neve)';
        }
        if (($c['from_email'] ?? '') === '') {
            $p[] = 'hiányzik a from_email (a feladó címe)';
        } elseif (!Message::validAddress((string) $c['from_email'])) {
            $p[] = 'a from_email nem érvényes e-mail-cím';
        }
        if (($c['user'] ?? '') !== '' && ($c['pass'] ?? '') === '') {
            $p[] = 'hiányzik a pass (a postafiók jelszava)';
        }
        return $p;
    }

    /** @return list<string> */
    public function problemList(): array
    {
        return self::problems($this->cfg);
    }

    public function configured(): bool
    {
        return self::problems($this->cfg) === [];
    }

    public function fromEmail(): string
    {
        return trim((string) ($this->cfg['from_email'] ?? ''));
    }

    public function send(Message $m): void
    {
        if (!$this->configured()) {
            throw new MailException('Az e-mail-küldés nincs beállítva: ' . implode('; ', self::problems($this->cfg)) . '.');
        }
        $host = trim((string) $this->cfg['host']);
        $secure = (string) $this->cfg['secure'];
        $port = (int) ($this->cfg['port'] ?? ($secure === 'ssl' ? 465 : 587));
        $user = (string) ($this->cfg['user'] ?? '');
        $from = $this->fromEmail();

        $this->conn->open($host, $port, $secure === 'ssl', self::TIMEOUT);
        try {
            $this->expect([220]);
            $ehlo = $this->ehlo();
            if ($secure === 'tls') {
                if (!preg_grep('/^STARTTLS/i', $ehlo)) {
                    throw new MailException('Az SMTP-szerver nem támogatja a STARTTLS-t. Próbálja a secure: ssl, port: 465 beállítást.');
                }
                $this->cmd('STARTTLS', [220]);
                $this->conn->startTls();
                $ehlo = $this->ehlo();
            }
            if ($user !== '') {
                $auth = strtoupper(implode(' ', preg_grep('/^AUTH[ =]/i', $ehlo) ?: []));
                $pass = (string) ($this->cfg['pass'] ?? '');
                try {
                    if (str_contains($auth, 'PLAIN') || !str_contains($auth, 'LOGIN')) {
                        $this->cmd('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), [235], 'AUTH PLAIN ***');
                    } else {
                        $this->cmd('AUTH LOGIN', [334]);
                        $this->cmd(base64_encode($user), [334], '***');
                        $this->cmd(base64_encode($pass), [235], '***');
                    }
                } catch (MailException $e) {
                    throw new MailException('Az SMTP-belépés nem sikerült: ellenőrizze a config.php smtp.user és smtp.pass értékét. (' . $e->getMessage() . ')');
                }
            }
            $this->cmd('MAIL FROM:<' . $from . '>', [250]);
            foreach ([...$m->to, ...$m->cc] as $rcpt) {
                $this->cmd('RCPT TO:<' . $rcpt . '>', [250, 251]);
            }
            $this->cmd('DATA', [354]);
            $this->conn->write(self::dotStuff($this->build($m)) . "\r\n.\r\n");
            $this->expect([250]);
            try {
                $this->cmd('QUIT', [221]);
            } catch (MailException) {
                // a levél már elment; a QUIT válasza nem számít
            }
        } finally {
            $this->conn->close();
        }
    }

    /** A teljes levél (fejlécek + törzs), CRLF sorvégekkel. */
    public function build(Message $m): string
    {
        $fromName = trim((string) ($this->cfg['from_name'] ?? ''));
        $domain = substr(strrchr($this->fromEmail(), '@') ?: '@localhost', 1);
        $h = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . ($fromName !== '' ? self::encodeHeader($fromName) . ' ' : '') . '<' . $this->fromEmail() . '>',
            'To: ' . implode(', ', $m->to),
        ];
        if ($m->cc) {
            $h[] = 'Cc: ' . implode(', ', $m->cc);
        }
        if ($m->replyTo !== '') {
            $h[] = 'Reply-To: ' . $m->replyTo;
        }
        $h[] = 'Subject: ' . self::encodeHeader($m->subject);
        $h[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>';
        $h[] = 'MIME-Version: 1.0';
        $part = static fn (string $type, string $body): string => "Content-Type: {$type}; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
        if ($m->html === '') {
            return implode("\r\n", $h) . "\r\n" . $part('text/plain', $m->text);
        }
        $b = 'xp-' . bin2hex(random_bytes(8));
        $h[] = 'Content-Type: multipart/alternative; boundary="' . $b . '"';
        return implode("\r\n", $h) . "\r\n\r\n--{$b}\r\n" . $part('text/plain', $m->text) . "\r\n--{$b}\r\n" . $part('text/html', $m->html) . "\r\n--{$b}--";
    }

    public static function encodeHeader(string $s): string
    {
        $s = str_replace(["\r", "\n"], ' ', $s);
        return preg_match('/[^\x20-\x7E]/', $s) ? mb_encode_mimeheader($s, 'UTF-8', 'B', "\r\n") : $s;
    }

    private static function dotStuff(string $data): string
    {
        $data = preg_replace("/\r?\n/", "\r\n", $data) ?? $data;
        return preg_replace('/^\./m', '..', $data) ?? $data;
    }

    /** @return list<string> az EHLO-válasz képességsorai */
    private function ehlo(): array
    {
        $name = preg_replace('/[^A-Za-z0-9.-]/', '', (string) ($_SERVER['SERVER_NAME'] ?? gethostname() ?: 'localhost')) ?: 'localhost';
        $this->conn->write("EHLO {$name}\r\n");
        return array_map(static fn ($l) => substr($l, 4), $this->expect([250]));
    }

    /**
     * @param list<int> $ok
     * @return list<string>
     */
    private function cmd(string $line, array $ok, ?string $shown = null): array
    {
        $this->conn->write($line . "\r\n");
        return $this->expect($ok, $shown ?? $line);
    }

    /**
     * @param list<int> $ok
     * @return list<string> a (többsoros) válasz sorai
     */
    private function expect(array $ok, string $after = ''): array
    {
        $lines = [];
        do {
            $l = $this->conn->readLine();
            $lines[] = $l;
        } while (strlen($l) > 3 && $l[3] === '-');
        $code = (int) substr($l, 0, 3);
        if (!in_array($code, $ok, true)) {
            throw new MailException('Az SMTP-szerver elutasította' . ($after !== '' ? ' (' . preg_replace('/^(MAIL FROM|RCPT TO):?/', '$1', $after) . ')' : '') . ': ' . $l);
        }
        return $lines;
    }
}

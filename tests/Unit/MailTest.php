<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Mail\MailException;
use Xperion\Mail\Message;
use Xperion\Mail\SmtpConnection;
use Xperion\Mail\SmtpMailer;
use Xperion\Mail\TextToHtml;

/** Forgatókönyv szerint válaszoló SMTP-kapcsolat. */
final class ScriptedSmtp implements SmtpConnection
{
    public array $written = [];
    public array $opened = [];
    public bool $tls = false;
    public bool $closed = false;

    /** @param list<string> $replies */
    public function __construct(private array $replies)
    {
    }

    public function open(string $host, int $port, bool $ssl, int $timeout): void
    {
        $this->opened = [$host, $port, $ssl];
    }

    public function readLine(): string
    {
        if (!$this->replies) {
            throw new MailException('nincs több válasz');
        }
        return array_shift($this->replies);
    }

    public function write(string $data): void
    {
        $this->written[] = $data;
    }

    public function startTls(): void
    {
        $this->tls = true;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

final class MailTest extends TestCase
{
    private const CFG = ['host' => 'mail.pelda.hu', 'port' => 587, 'secure' => 'tls', 'user' => 'u@pelda.hu', 'pass' => 'titok', 'from_email' => 'sajto@pelda.hu', 'from_name' => 'Xperion sajtófigyelés'];

    public function testStartTlsAuthAndDataWithDotStuffing(): void
    {
        $c = new ScriptedSmtp(['220 hello', '250-mail.pelda.hu', '250-STARTTLS', '250 SIZE 1000', '220 go', '250-mail.pelda.hu', '250 AUTH LOGIN PLAIN', '235 ok', '250 ok', '250 ok', '250 ok', '354 go', '250 queued', '221 bye']);
        $m = new SmtpMailer(self::CFG, $c);
        $m->send(new Message(['attila@ugyfel.hu'], 'Sajtófigyelés – október', "Első sor\n.pont a sor elején", '<p>x</p>', ['emi@ugyfel.hu']));
        self::assertSame(['mail.pelda.hu', 587, false], $c->opened);
        self::assertTrue($c->tls);
        self::assertTrue($c->closed);
        $w = implode('', $c->written);
        self::assertStringContainsString('AUTH PLAIN ' . base64_encode("\0u@pelda.hu\0titok"), $w);
        self::assertStringContainsString("RCPT TO:<attila@ugyfel.hu>\r\nRCPT TO:<emi@ugyfel.hu>", $w);
        $data = $c->written[count($c->written) - 2];
        self::assertStringEndsWith("\r\n.\r\n", $data);
        self::assertStringContainsString('Subject: =?UTF-8?B?', $data);
        self::assertStringContainsString('multipart/alternative', $data);
        self::assertStringContainsString('Cc: emi@ugyfel.hu', $data);
        // a base64 törzsben nincs sor eleji pont; a fejlécek CRLF-esek
        self::assertDoesNotMatchRegularExpression("/(?<!\r)\n/", $data);
        $plain = base64_decode(preg_replace('/\s+/', '', explode("\r\n\r\n", explode('--xp-', $data)[1], 2)[1]));
        self::assertSame("Első sor\n.pont a sor elején", $plain);
    }

    public function testAuthFailureGivesHungarianHint(): void
    {
        $c = new ScriptedSmtp(['220 hello', '250-x', '250 AUTH PLAIN', '535 5.7.8 bad credentials']);
        $m = new SmtpMailer(['secure' => 'ssl'] + self::CFG + ['port' => 465], $c);
        try {
            $m->send(new Message(['a@b.hu'], 'T', 'x'));
            self::fail('hiba várt');
        } catch (MailException $e) {
            self::assertStringContainsString('smtp.user és smtp.pass', $e->getMessage());
            self::assertStringNotContainsString('titok', $e->getMessage());
            self::assertStringNotContainsString(base64_encode("\0u@pelda.hu\0titok"), $e->getMessage());
        }
        self::assertTrue($c->opened[2], 'ssl');
        self::assertTrue($c->closed);
    }

    public function testRejectedRecipientAndNotConfigured(): void
    {
        $c = new ScriptedSmtp(['220 hello', '250 x', '250 ok', '550 no such user']);
        try {
            (new SmtpMailer(['secure' => 'none', 'user' => ''] + self::CFG, $c))->send(new Message(['nincs@ugyfel.hu'], 'T', 'x'));
            self::fail('hiba várt');
        } catch (MailException $e) {
            self::assertStringContainsString('RCPT TO', $e->getMessage());
            self::assertStringContainsString('550', $e->getMessage());
        }
        self::assertFalse((new SmtpMailer([]))->configured());
        $this->expectException(MailException::class);
        (new SmtpMailer([]))->send(new Message(['a@b.hu'], 'T', 'x'));
    }

    public function testConfigVariantsAreAccepted(): void
    {
        // feladó nélkül: a belépési név lesz a feladó
        self::assertSame([], SmtpMailer::problems(['host' => 'mail.pelda.hu', 'user' => 'sajto@pelda.hu', 'pass' => 'x']));
        // más kulcsnevek, nagybetűk, szóközök
        self::assertSame([], SmtpMailer::problems(['Server' => ' mail.pelda.hu ', 'username' => 'sajto@pelda.hu', 'password' => 'x', 'encryption' => 'STARTTLS', 'port' => '587']));
        $n = SmtpMailer::normalize(['host' => 'h', 'port' => 587, 'from' => 'a@b.hu']);
        self::assertSame(['tls', 'a@b.hu'], [$n['secure'], $n['from_email']]);
        self::assertSame('ssl', SmtpMailer::normalize(['host' => 'h', 'port' => 465])['secure']);
        // pontos hibaüzenetek
        self::assertSame(['a config.php-ban nincs smtp rész (vagy nem a betöltött config-fájlban van)'], SmtpMailer::problems([]));
        self::assertSame(['hiányzik a host (a levelezőszerver neve)', 'hiányzik a from_email (a feladó címe)'], SmtpMailer::problems(['host' => '', 'port' => 465, 'secure' => 'ssl', 'user' => '', 'pass' => '', 'from_email' => '']));
        self::assertSame(['a from_email nem érvényes e-mail-cím', 'hiányzik a pass (a postafiók jelszava)'], SmtpMailer::problems(['host' => 'h', 'user' => 'u', 'from_email' => 'nem-cim']));
    }

    public function testTopLevelSmtpKeysInConfig(): void
    {
        $c = new \Xperion\Config(['smtp_host' => 'mail.pelda.hu', 'smtp_user' => 'sajto@pelda.hu', 'smtp_pass' => 'x']);
        self::assertSame([], SmtpMailer::problems($c->smtp()));
        $row = array_values(array_filter($c->checklist(), static fn ($r) => str_starts_with($r['label'], 'SMTP')))[0];
        self::assertTrue($row['ok']);
        $row = array_values(array_filter((new \Xperion\Config(['smtp' => ['host' => 'h']]))->checklist(), static fn ($r) => str_starts_with($r['label'], 'SMTP')))[0];
        self::assertSame('hiányzik a from_email (a feladó címe)', $row['note']);
    }

    public function testDuplicateSmtpSectionInConfigIsDetected(): void
    {
        $f = sys_get_temp_dir() . '/xp-dup-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($f, "<?php\nreturn [\n    'smtp' => ['host' => 'mail.sunserver.hu', 'from_email' => 's@sunserver.hu'],\n    'db' => ['name' => 'x', 'user' => 'y'],\n    // minta\n    'smtp' => [\n        'host' => '',\n        'from_email' => '',\n    ],\n];\n");
        $c = \Xperion\Config::load([$f]);
        self::assertSame(['smtp'], $c->duplicateKeys());
        $rows = array_column($c->checklist(), 'note', 'label');
        self::assertStringContainsString('kétszer szerepel: smtp', $rows['Konfigurációs fájl']);
        self::assertStringContainsString('az alsó (üres) érvényes', $rows['SMTP (e-mail)']);
        file_put_contents($f, "<?php\nreturn ['smtp' => ['host' => 'h', 'from_email' => 'a@b.hu', 'user' => 'a@b.hu', 'pass' => 'x'], 'db' => ['host' => 'localhost']];\n");
        self::assertSame([], \Xperion\Config::load([$f])->duplicateKeys(), 'belső kulcsok (host) nem számítanak');
    }

    public function testConnectErrorsAreExplainedInHungarian(): void
    {
        $e = \Xperion\Mail\StreamSmtpConnection::explain('', ['SSL operation failed with code 1. OpenSSL Error messages: error:0A000086:SSL routines::certificate verify failed']);
        self::assertStringContainsString("'verify_peer' => false", $e);
        self::assertStringContainsString('localhost', \Xperion\Mail\StreamSmtpConnection::explain('php_network_getaddresses: getaddrinfo for x failed', []));
        self::assertStringContainsString('kimenő', \Xperion\Mail\StreamSmtpConnection::explain('Connection timed out', []));
        self::assertStringContainsString('SMTP-kapcsolat ellenőrzése', \Xperion\Mail\StreamSmtpConnection::explain('', []));
    }

    public function testProbeFindsWorkingVariantAndPrefersCertificateName(): void
    {
        $tried = [];
        $factory = function (array $cfg) use (&$tried) {
            $tried[] = $cfg['host'] . ':' . $cfg['port'] . ':' . $cfg['secure'] . (isset($cfg['verify_peer']) ? ':noverify' : '');
            $ok = $cfg['port'] === 465 && ($cfg['host'] === 'srv.sunserver.hu' || isset($cfg['verify_peer']));
            $err = $ok ? '' : ($cfg['port'] === 465 ? 'a szerver tanúsítványa nem erre a névre szól' : 'a szerver ezen a porton elutasította a kapcsolatot');
            $conn = new ScriptedSmtp([]);
            return new class ($cfg, $conn, $ok, $err) extends \stdClass {
                public function __construct(public array $cfg, public $conn, private bool $ok, private string $err) {}
                public function check(): array { return ['ok' => $this->ok, 'error' => $this->err, 'cert' => ['*.sunserver.hu', 'srv.sunserver.hu']]; }
            };
        };
        $rows = SmtpMailer::probe(['host' => 'mail.sunserver.hu', 'user' => 'u@x.hu', 'pass' => 'p'], $factory);
        $last = end($rows);
        self::assertSame(['srv.sunserver.hu', 465, 'ssl', true, true], [$last['host'], $last['port'], $last['secure'], $last['verify'], $last['ok']], 'a tanúsítványon szereplő névvel, ellenőrzéssel működik');
        self::assertContains('mail.sunserver.hu:465:ssl:noverify', $tried);
    }

    public function testMessageValidationAndList(): void
    {
        self::assertSame(['a@b.hu', 'c@d.hu'], Message::parseList(' a@b.hu; c@d.hu , a@b.hu'));
        $this->expectException(MailException::class);
        new Message(["a@b.hu\r\nBcc: x@y.hu"], 'T', 'x');
    }

    public function testTextToHtml(): void
    {
        $h = TextToHtml::convert("Kedves Attila!\n\nLink: https://app.test/index.php?p=cikkek&honap=2610\n\n- Új forrás: Acrelec <b>\n- Cikkek: 3");
        self::assertStringContainsString('<a href="https://app.test/index.php?p=cikkek&amp;honap=2610"', $h);
        self::assertStringContainsString('<li style="margin:0 0 6px 0"><b>Új forrás:</b> Acrelec &lt;b&gt;</li>', $h);
        self::assertStringContainsString('<p style="margin:0 0 12px 0">Kedves Attila!</p>', $h);
    }
}

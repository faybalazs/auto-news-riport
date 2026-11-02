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

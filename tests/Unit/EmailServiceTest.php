<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use Xperion\Auth\Access;
use Xperion\Auth\AccessDenied;
use Xperion\Auth\AuditLog;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\TokenService;
use Xperion\Auth\UserAdmin;
use Xperion\Claude\ClaudeClient;
use Xperion\Db\Database;
use Xperion\Import\Url;
use Xperion\Mail\MailException;
use Xperion\Monthly\EmailService;
use Xperion\Tests\DbTestCase;
use Xperion\Tests\FakeClaude;
use Xperion\Tests\FakeMailer;

final class EmailServiceTest extends DbTestCase
{
    private FakeClaude $claude;
    private FakeMailer $mailer;
    private EmailService $svc;
    private UserAdmin $users;
    private int $op;
    private int $x;
    private int $other;

    protected function setUp(): void
    {
        parent::setUp();
        $audit = new AuditLog($this->db);
        $this->users = new UserAdmin($this->db, new TokenService($this->db), $audit);
        $clients = new ClientAdmin($this->db, $audit);
        $this->op = $this->users->createFirstOperator('Balázs', 'b@pelda.hu', 'eleg-hosszu-jelszo');
        $this->x = $clients->create(Access::forUser($this->db, $this->op, null), 'Xperion', '');
        $this->other = $clients->create(Access::forUser($this->db, $this->op, null), 'Másik', '');
        $now = Database::now();
        foreach ([['Vemco', 'How a bank cut wait times', 'Erősen javasolt', 0], ['Acrelec', 'Kiosk ordering', 'Javasolt', 1]] as $i => [$s, $t, $r, $new]) {
            $u = 'https://a.test/' . $i;
            $this->db->insert('articles', ['client_id' => $this->x, 'month' => '2610', 'source_name' => $s, 'title' => $t, 'url' => $u, 'url_hash' => Url::hash($u), 'recommendation' => $r, 'from_new_source' => $new, 'created_at' => $now, 'updated_at' => $now]);
        }
        $this->db->insert('month_reports', ['client_id' => $this->x, 'month' => '2610', 'summary_rows' => '[{"label":"Cikkek száma","value":"2"}]', 'details' => 'Rövid.', 'stats' => '{}', 'model' => 'm', 'created_at' => $now]);
        $this->claude = new FakeClaude();
        $this->mailer = new FakeMailer();
        $this->svc = new EmailService($this->db, new ClaudeClient($this->claude, 'k'), $this->mailer, $audit, 'https://app.test/');
    }

    private function op(): Access
    {
        return Access::forUser($this->db, $this->op, $this->x);
    }

    public function testGenerateFromMonthDataAndNotifyOnlyOperator(): void
    {
        $this->svc->saveSettings($this->op(), 'b@pelda.hu; ', 'emi@ugyfel.hu', '');
        $dry = $this->svc->generate($this->op(), '2610', true);
        self::assertFalse($dry['saved']);
        self::assertNull($this->svc->get($this->op(), '2610'), 'szárazon nem ment');

        $r = $this->svc->generateForClient($this->x, '2610', false);
        self::assertTrue($r['saved']);
        self::assertSame(1, $r['notified']);
        self::assertCount(1, $this->mailer->sent, 'csak az üzemeltető kapott levelet');
        self::assertSame(['b@pelda.hu'], $this->mailer->sent[0]->to);
        self::assertStringStartsWith('Jóváhagyásra vár:', $this->mailer->sent[0]->subject);
        self::assertStringContainsString('https://app.test/index.php?p=vezerlo', $this->mailer->sent[0]->text);

        $req = end($this->claude->requests)['body'];
        $facts = json_decode($req['messages'][0]['content'], true);
        self::assertSame('https://app.test/index.php?p=cikkek&honap=2610', $facts['havi_cikkek_link']);
        self::assertSame(['Acrelec: Kiosk ordering'], $facts['új_forrásból']);
        self::assertStringContainsString('Balázs', $facts['megszólított'], 'a címzett neve a felhasználók közül');
        self::assertSame('claude-sonnet-5-5', $req['model']);

        $row = $this->svc->get($this->op(), '2610');
        self::assertSame('jovahagyasra_var', $row['status']);
        self::assertSame('Sajtófigyelés – 2026. október', $row['subject']);
    }

    public function testEditSendOnceAndLockAfterSending(): void
    {
        $this->svc->saveSettings($this->op(), 'attila@ugyfel.hu', 'emi@ugyfel.hu, attila@ugyfel.hu', 'Rövid, tegező.');
        $this->svc->generate($this->op(), '2610', false);
        self::assertSame([], $this->mailer->sent, 'kézi generáláskor nincs értesítő');
        $this->svc->update($this->op(), '2610', 'Új tárgy', "Szöveg\n- Pont: egy");
        self::assertSame('Balázs', $this->svc->get($this->op(), '2610')['edited_by_name']);

        self::assertSame('b@pelda.hu', $this->svc->sendTest($this->op(), '2610'));
        self::assertSame('[Teszt] Új tárgy', $this->mailer->sent[0]->subject);
        self::assertSame('jovahagyasra_var', $this->svc->get($this->op(), '2610')['status'], 'a teszt nem küldi el');

        $this->mailer->fail = 'Az SMTP-szerver elutasította: 550';
        try {
            $this->svc->approveAndSend($this->op(), '2610');
            self::fail('hiba várt');
        } catch (MailException $e) {
            self::assertStringContainsString('nem ment el', $e->getMessage());
        }
        self::assertSame('hiba', $this->svc->get($this->op(), '2610')['status']);

        $this->mailer->fail = null;
        $row = $this->svc->approveAndSend($this->op(), '2610');
        self::assertSame('elkuldve', $row['status']);
        $m = end($this->mailer->sent);
        self::assertSame(['attila@ugyfel.hu'], $m->to);
        self::assertSame(['emi@ugyfel.hu'], $m->cc, 'a címzett nem kerül a másolatba is');
        self::assertStringContainsString('<li', $m->html);
        self::assertStringContainsString('attila@ugyfel.hu (másolat: emi@ugyfel.hu)', $row['sent_to']);

        foreach ([fn () => $this->svc->approveAndSend($this->op(), '2610'), fn () => $this->svc->update($this->op(), '2610', 'X', 'Y'), fn () => $this->svc->generateForClient($this->x, '2610', false)] as $f) {
            try {
                $f();
                self::fail('az elküldött levél nem módosítható és nem küldhető újra');
            } catch (\InvalidArgumentException) {
            }
        }
        self::assertCount(2, $this->mailer->sent, 'teszt + egyetlen éles küldés');
    }

    public function testGuards(): void
    {
        $this->svc->generate($this->op(), '2610', false);
        try {
            $this->svc->approveAndSend($this->op(), '2610');
            self::fail('címzett nélkül nem küld');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Nincs megadva', $e->getMessage());
        }
        try {
            $this->svc->generate(Access::forUser($this->db, $this->op, $this->other), '2610', false);
            self::fail('lista nélkül nem ír');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('nincs havi lista', $e->getMessage());
        }
        self::assertNull($this->svc->get(Access::forUser($this->db, $this->op, $this->other), '2610'), 'ügyfélhatár');
        try {
            $this->svc->saveSettings($this->op(), 'nem-cim', '', '');
            self::fail('érvénytelen cím');
        } catch (\InvalidArgumentException) {
        }
        $inv = $this->users->invite($this->op(), 'Attila', 'attila@ugyfel.hu', $this->x, 'ugyfeladmin');
        $this->users->setPasswordWithToken((string) $inv['token'], TokenService::INVITE, 'attila-jelszo-1');
        $this->expectException(AccessDenied::class);
        $this->svc->approveAndSend(Access::forUser($this->db, $inv['user_id'], null), '2610');
    }
}

<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use Xperion\Auth\Access;
use Xperion\Auth\AccessDenied;
use Xperion\Auth\AuditLog;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\TokenService;
use Xperion\Auth\UserAdmin;
use Xperion\Db\Database;
use Xperion\Monthly\ArticleService;
use Xperion\Monthly\SummaryShare;
use Xperion\Tests\DbTestCase;
use Xperion\Tests\FakeMailer;

final class SummaryShareTest extends DbTestCase
{
    private int $op;
    private int $x;
    private FakeMailer $mailer;
    private SummaryShare $share;
    private UserAdmin $users;

    protected function setUp(): void
    {
        parent::setUp();
        $audit = new AuditLog($this->db);
        $this->users = new UserAdmin($this->db, new TokenService($this->db), $audit);
        $this->op = $this->users->createFirstOperator('Balázs', 'b@pelda.hu', 'eleg-hosszu-jelszo');
        $this->x = (new ClientAdmin($this->db, $audit))->create(Access::forUser($this->db, $this->op, null), 'Xperion', 'xperion.hu');
        $this->db->insert('month_reports', ['client_id' => $this->x, 'month' => '2610', 'summary_rows' => '[]', 'details' => 'Októberben a várakozás mérése volt a fő téma.',
            'sections' => json_encode([['title' => 'Újdonságok', 'points' => ['A Qmatic új szolgáltatást vezetett be.']], ['title' => 'Trendek', 'points' => ['A bankok mérik a várakozást.']]], JSON_UNESCAPED_UNICODE),
            'stats' => '{}', 'model' => 'm', 'created_at' => Database::now()]);
        $this->mailer = new FakeMailer();
        $this->share = new SummaryShare(new ArticleService($this->db, $audit), $this->mailer, $audit, 'https://app.test/');
    }

    private function op(): Access
    {
        return Access::forUser($this->db, $this->op, $this->x);
    }

    public function testTextAndSend(): void
    {
        $d = $this->share->data($this->op(), '2610');
        $text = SummaryShare::text($d);
        self::assertStringContainsString('Vezetői összefoglaló – Xperion, 2026. október', $text);
        self::assertStringContainsString("Újdonságok\n- A Qmatic új szolgáltatást vezetett be.", $text);

        $to = $this->share->send($this->op(), '2610', 'vezeto@ceg.hu, masik@ceg.hu', 'cc@ceg.hu', 'Kedves Kollégák!');
        self::assertSame(['vezeto@ceg.hu', 'masik@ceg.hu', 'cc@ceg.hu'], $to);
        $m = $this->mailer->sent[0];
        self::assertSame('Vezetői összefoglaló – Xperion, 2026. október', $m->subject);
        self::assertStringStartsWith('Kedves Kollégák!', $m->text);
        self::assertStringContainsString('https://app.test/index.php?p=cikkek&honap=2610', $m->text);
        self::assertStringContainsString('<li style=', $m->html);
        self::assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM audit_log WHERE action = 'Vezetői összefoglaló elküldve'"));
    }

    public function testValidationAndPermission(): void
    {
        foreach ([['', 'legalább egy címzettet'], ['nem-email', 'Érvénytelen e-mail-cím']] as [$to, $msg]) {
            try {
                $this->share->send($this->op(), '2610', $to, '', '');
                self::fail('hiba várt');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString($msg, $e->getMessage());
            }
        }
        try {
            $this->share->data($this->op(), '2609');
            self::fail('nincs összefoglaló');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('nincs vezetői összefoglaló', $e->getMessage());
        }
        $inv = $this->users->invite($this->op(), 'Emi', 'emi@x.hu', $this->x, 'tartalom');
        $this->users->setPasswordWithToken((string) $inv['token'], TokenService::INVITE, 'jelszo-tartalom-1');
        $this->expectException(AccessDenied::class);
        $this->share->send(Access::forUser($this->db, $inv['user_id'], null), '2610', 'a@b.hu', '', '');
    }
}

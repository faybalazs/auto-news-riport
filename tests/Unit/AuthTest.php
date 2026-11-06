<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use Xperion\Auth\Access;
use Xperion\Auth\AccessDenied;
use Xperion\Auth\AuditLog;
use Xperion\Auth\Authenticator;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\Roles;
use Xperion\Auth\TokenService;
use Xperion\Auth\UserAdmin;
use Xperion\Tests\DbTestCase;

final class AuthTest extends DbTestCase
{
    private UserAdmin $users;
    private ClientAdmin $clients;
    private TokenService $tokens;
    private int $opId;
    private int $xperion;
    private int $other;

    protected function setUp(): void
    {
        parent::setUp();
        $audit = new AuditLog($this->db);
        $this->tokens = new TokenService($this->db);
        $this->users = new UserAdmin($this->db, $this->tokens, $audit);
        $this->clients = new ClientAdmin($this->db, $audit);
        $this->opId = $this->users->createFirstOperator('Balázs', 'Balazs@Pelda.hu', 'eleg-hosszu-jelszo');
        $op = $this->op();
        $this->xperion = $this->clients->create($op, 'Xperion', 'https://xperion.hu/');
        $this->other = $this->clients->create($this->op(), 'Másik ügyfél', '');
    }

    private function op(?int $client = null): Access
    {
        return Access::forUser($this->db, $this->opId, $client);
    }

    /** Meghív és aktivál egy felhasználót; visszaadja az azonosítóját. */
    private function member(string $email, int $client, string $role): int
    {
        $r = $this->users->invite($this->op($client), 'Teszt ' . $email, $email, $client, $role);
        if ($r['token'] !== null) {
            $this->users->setPasswordWithToken($r['token'], TokenService::INVITE, 'jelszo-12345');
        }
        return $r['user_id'];
    }

    public function testSetupOnlyOnce(): void
    {
        $this->expectException(AccessDenied::class);
        $this->users->createFirstOperator('Más', 'mas@pelda.hu', 'eleg-hosszu-jelszo');
    }

    public function testLoginNormalizesEmailAndRateLimits(): void
    {
        $auth = new Authenticator($this->db);
        self::assertFalse($auth->setupNeeded());
        self::assertSame($this->opId, (int) $auth->attempt('  BALAZS@pelda.hu ', 'eleg-hosszu-jelszo', '1.1.1.1')['user']['id']);
        for ($i = 0; $i < Authenticator::MAX_ATTEMPTS; $i++) {
            self::assertSame('Hibás e-mail-cím vagy jelszó.', $auth->attempt('balazs@pelda.hu', 'rossz', '2.2.2.2')['error']);
        }
        self::assertStringContainsString('Túl sok', (string) $auth->attempt('balazs@pelda.hu', 'eleg-hosszu-jelszo', '2.2.2.2')['error']);
        self::assertNotNull($auth->attempt('balazs@pelda.hu', 'eleg-hosszu-jelszo', '3.3.3.3')['user'], 'más IP-ről működik');
    }

    public function testInviteFlowAndSingleUseToken(): void
    {
        $r = $this->users->invite($this->op($this->xperion), 'Berényi Attila', 'attila@pelda.hu', $this->xperion, Roles::CLIENT_ADMIN);
        $auth = new Authenticator($this->db);
        self::assertSame('Hibás e-mail-cím vagy jelszó.', $auth->attempt('attila@pelda.hu', 'barmi', '9.9.9.9')['error'], 'jelszó nélküli fiók: nem derül ki, hogy létezik');
        try {
            $this->users->setPasswordWithToken((string) $r['token'], TokenService::INVITE, 'rovid');
            self::fail('rövid jelszó');
        } catch (\InvalidArgumentException) {
        }
        self::assertSame($r['user_id'], $this->users->setPasswordWithToken((string) $r['token'], TokenService::INVITE, 'attila-jelszava-1'));
        self::assertNull($this->users->setPasswordWithToken((string) $r['token'], TokenService::INVITE, 'attila-jelszava-2'), 'a link egyszer használható');
        self::assertNotNull($auth->attempt('attila@pelda.hu', 'attila-jelszava-1', '9.9.9.9')['user']);
    }

    public function testClientAdminSeesAndManagesOnlyOwnClient(): void
    {
        $attila = $this->member('attila@pelda.hu', $this->xperion, Roles::CLIENT_ADMIN);
        $emi = $this->member('emi@pelda.hu', $this->xperion, Roles::CONTENT);
        $stranger = $this->member('idegen@pelda.hu', $this->other, Roles::CLIENT_ADMIN);
        $a = Access::forUser($this->db, $attila, $this->other); // másik ügyfelet kér, de nem fér hozzá
        self::assertSame($this->xperion, $a->clientId(), 'a kért, de nem elérhető ügyfél helyett a sajátját kapja');
        self::assertCount(1, $a->clients());

        $emails = array_column($this->users->visibleUsers($a), 'email');
        sort($emails);
        self::assertSame(['attila@pelda.hu', 'emi@pelda.hu'], $emails, 'idegen ügyfél tagja és az üzemeltető nem látszik');

        foreach ([fn () => $this->users->setDisabled($a, $stranger, true), fn () => $this->users->remove($a, $stranger), fn () => $this->users->issueLink($a, $stranger)] as $fn) {
            try {
                $fn();
                self::fail('idegen ügyfél felhasználója nem kezelhető');
            } catch (\InvalidArgumentException $e) {
                self::assertSame('Nincs ilyen felhasználó.', $e->getMessage());
            }
        }
        $this->expectException(AccessDenied::class);
        $this->users->invite($a, 'X', 'x@pelda.hu', $this->other, Roles::READER);
    }

    public function testClientAdminCannotTouchOperatorOrSelf(): void
    {
        $attila = $this->member('attila@pelda.hu', $this->xperion, Roles::CLIENT_ADMIN);
        $a = Access::forUser($this->db, $attila, null);
        foreach ([fn () => $this->users->invite($a, 'Új üzemeltető', 'op2@pelda.hu', null, Roles::OPERATOR), fn () => $this->users->remove($a, $attila)] as $fn) {
            try {
                $fn();
                self::fail('tiltott');
            } catch (AccessDenied) {
                self::assertTrue(true);
            }
        }
    }

    public function testMultiClientPersonIsOnlyRemovedFromOwnClient(): void
    {
        $attila = $this->member('attila@pelda.hu', $this->xperion, Roles::CLIENT_ADMIN);
        $shared = $this->member('kozos@pelda.hu', $this->xperion, Roles::CONTENT);
        $this->users->invite($this->op($this->other), 'Közös', 'kozos@pelda.hu', $this->other, Roles::READER);
        $a = Access::forUser($this->db, $attila, null);
        self::assertSame('eltavolitva', $this->users->remove($a, $shared));
        self::assertNotNull($this->db->one('SELECT id FROM users WHERE id = ?', [$shared]), 'a fiók megmarad a másik ügyfélnél');
        self::assertSame(Roles::READER, Access::forUser($this->db, $shared, null)->role());
    }

    public function testPermissionsFollowRoleInCurrentClient(): void
    {
        $emi = $this->member('emi@pelda.hu', $this->xperion, Roles::CONTENT);
        $e = Access::forUser($this->db, $emi, null);
        self::assertTrue($e->can('publish'));
        self::assertFalse($e->can('decide'));
        self::assertFalse($e->can('users'));
        self::assertFalse($e->can('ops'));
        $this->expectException(AccessDenied::class);
        $this->users->visibleUsers($e);
    }

    public function testOperatorSeesAllClientsAndCanSwitch(): void
    {
        $op = $this->op($this->other);
        self::assertSame($this->other, $op->clientId());
        self::assertCount(2, $op->clients());
        self::assertSame(Roles::OPERATOR, $op->role());
        self::assertSame('xperion.hu', $this->db->value('SELECT website FROM clients WHERE id = ?', [$this->xperion]));
    }

    public function testDisabledUserCannotLoginOrGetAccess(): void
    {
        $emi = $this->member('emi@pelda.hu', $this->xperion, Roles::CONTENT);
        $this->users->setDisabled($this->op($this->xperion), $emi, true);
        self::assertStringContainsString('le van tiltva', (string) (new Authenticator($this->db))->attempt('emi@pelda.hu', 'jelszo-12345', '5.5.5.5')['error']);
        self::assertNull(Access::forUser($this->db, $emi, null));
        $this->users->setDisabled($this->op($this->xperion), $emi, false);
        self::assertNotNull(Access::forUser($this->db, $emi, null));
    }

    public function testResetLinkForActiveUser(): void
    {
        $emi = $this->member('emi@pelda.hu', $this->xperion, Roles::CONTENT);
        $link = $this->users->issueLink($this->op($this->xperion), $emi);
        self::assertSame(TokenService::RESET, $link['purpose']);
        self::assertNull($this->tokens->peek($link['token'], TokenService::INVITE), 'a cél nem keverhető');
        self::assertSame($emi, $this->users->setPasswordWithToken($link['token'], TokenService::RESET, 'uj-jelszo-12345'));
    }

    public function testAuditIsScopedToClient(): void
    {
        $attila = $this->member('attila@pelda.hu', $this->xperion, Roles::CLIENT_ADMIN);
        $this->member('idegen@pelda.hu', $this->other, Roles::READER);
        $log = (new AuditLog($this->db))->recent($this->xperion);
        foreach ($log as $row) {
            self::assertSame($this->xperion, (int) $row['client_id']);
            self::assertStringNotContainsString('idegen@', (string) $row['details']);
        }
        self::assertNotEmpty($log);
    }
}

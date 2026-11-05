<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\TokenService;
use Xperion\Auth\UserAdmin;
use Xperion\Db\Database;
use Xperion\Import\Url;
use Xperion\Tests\DbTestCase;
use Xperion\Web\GlobalSearch;

final class GlobalSearchTest extends DbTestCase
{
    public function testSearchesCurrentClientByPermission(): void
    {
        $audit = new AuditLog($this->db);
        $users = new UserAdmin($this->db, new TokenService($this->db), $audit);
        $op = $users->createFirstOperator('Balázs', 'b@pelda.hu', 'eleg-hosszu-jelszo');
        $clients = new ClientAdmin($this->db, $audit);
        $x = $clients->create(Access::forUser($this->db, $op, null), 'Xperion', 'xperion.hu');
        $m = $clients->create(Access::forUser($this->db, $op, null), 'Másik', 'masik.hu');
        $now = Database::now();
        foreach ([[$x, 'How a bank cut wait times'], [$m, 'Bank secret of another client']] as [$cid, $t]) {
            $u = 'https://e.test/' . md5($t);
            $this->db->insert('articles', ['client_id' => $cid, 'month' => '2610', 'source_name' => 'Vemco', 'title' => $t, 'url' => $u, 'url_hash' => Url::hash($u), 'created_at' => $now, 'updated_at' => $now]);
        }
        $this->db->insert('posts', ['client_id' => $x, 'code' => 'LI-2610-01', 'month' => '2610', 'status' => 'ugyfelnel', 'topic' => 'Bankfiók várakozás', 'opening' => 'Mennyit ér egy perc?', 'created_at' => $now, 'updated_at' => $now]);
        $s = new GlobalSearch($this->db);

        $r = $s->search(Access::forUser($this->db, $op, $x), 'bank');
        $groups = array_column($r, 'items', 'group');
        self::assertSame(['How a bank cut wait times'], array_column($groups['Cikkek'], 'title'), 'csak az aktuális ügyfél cikke');
        self::assertStringContainsString('honap=2610', $groups['Cikkek'][0]['href']);
        self::assertSame('index.php?p=poszt&kod=LI-2610-01', $groups['LinkedIn-posztok'][0]['href']);
        self::assertSame([], $s->search(Access::forUser($this->db, $op, $x), 'b'), 'legalább 2 karakter kell');
        self::assertSame('Felhasználók', $s->search(Access::forUser($this->db, $op, $x), 'meghívás')[0]['items'][0]['title']);

        $inv = $users->invite(Access::forUser($this->db, $op, $x), 'Olga', 'olga@x.hu', $x, 'olvaso');
        $users->setPasswordWithToken((string) $inv['token'], TokenService::INVITE, 'jelszo-olvaso-11');
        $reader = Access::forUser($this->db, $inv['user_id'], $x);
        self::assertSame([], $s->search($reader, 'meghívás'), 'olvasó nem látja a Felhasználók menüpontot');
        self::assertSame([], $s->search($reader, '100%_\\'), 'a LIKE-jelek nem szűrnek mindent');
    }
}

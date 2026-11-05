<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\TokenService;
use Xperion\Auth\UserAdmin;
use Xperion\Tests\DbTestCase;

final class NotificationsTest extends DbTestCase
{
    public function testNotificationsShowOthersWorkOnCurrentClient(): void
    {
        $audit = new AuditLog($this->db);
        $users = new UserAdmin($this->db, new TokenService($this->db), $audit);
        $op = $users->createFirstOperator('Balázs', 'b@pelda.hu', 'eleg-hosszu-jelszo');
        $clients = new ClientAdmin($this->db, $audit);
        $x = $clients->create(Access::forUser($this->db, $op, null), 'Xperion', 'xperion.hu');
        $m = $clients->create(Access::forUser($this->db, $op, null), 'Másik', 'masik.hu');
        $inv = $users->invite(Access::forUser($this->db, $op, $x), 'Olga', 'olga@x.hu', $x, 'tartalom');
        $users->setPasswordWithToken((string) $inv['token'], TokenService::INVITE, 'jelszo-tartalom-11');
        $olga = (int) $inv['user_id'];

        $audit->add($x, $olga, 'Poszt jóváhagyva', 'LI-2610-01');
        $audit->add($x, $olga, 'Belépés', '');
        $audit->add($x, $olga, 'Forrás felvéve', 'Vemco');
        $audit->add($x, $op, 'Döntés módosítva', 'saját művelet');
        $audit->add($m, $olga, 'Poszt jóváhagyva', 'más ügyfél');

        $n = $audit->notifications(Access::forUser($this->db, $op, $x));
        self::assertSame(['Forrás felvéve', 'Poszt jóváhagyva'], array_column($n, 'action'), 'a saját művelet, a belépés és a más ügyfél kimarad');
        self::assertSame('index.php?p=poszt&kod=LI-2610-01', $n[1]['href']);

        $audit->add($x, $op, 'Forrás törölve', 'Qmatic');
        $n = $audit->notifications(Access::forUser($this->db, $olga, $x));
        self::assertSame(['Döntés módosítva'], array_column($n, 'action'), 'Napló-jog nélkül csak a munkához tartozó kategóriák');
    }
}

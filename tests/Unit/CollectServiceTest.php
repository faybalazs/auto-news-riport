<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\TokenService;
use Xperion\Auth\UserAdmin;
use Xperion\Collect\CollectService;
use Xperion\Collect\Collector;
use Xperion\Db\Database;
use Xperion\Import\Url;
use Xperion\Log\LocalLog;
use Xperion\Tests\DbTestCase;
use Xperion\Tests\FakeWeb;

final class CollectServiceTest extends DbTestCase
{
    public function testCollectFiltersMonthDuplicatesExclusionsAndStaysInClient(): void
    {
        $audit = new AuditLog($this->db);
        $users = new UserAdmin($this->db, new TokenService($this->db), $audit);
        $clients = new ClientAdmin($this->db, $audit);
        $op = $users->createFirstOperator('B', 'b@pelda.hu', 'eleg-hosszu-jelszo');
        $x = $clients->create(Access::forUser($this->db, $op, null), 'Xperion', '');
        $other = $clients->create(Access::forUser($this->db, $op, null), 'Másik', '');
        $now = Database::now();
        $src = fn (int $c, string $name, string $url, int $active = 1, int $new = 0) => $this->db->insert('sources', ['client_id' => $c, 'name' => $name, 'url' => $url, 'url_hash' => Url::hash($url), 'active' => $active, 'is_new' => $new, 'updated_at' => $now]);
        $src($x, 'Vemco', 'https://www.vemcogroup.com/blog/rss.xml');
        $src($x, 'Kikapcsolt', 'https://off.com/blog', 0);
        $src($other, 'Idegen', 'https://idegen.com/rss.xml');
        $this->db->insert('keywords', ['client_id' => $x, 'keyword' => 'news', 'is_exclude' => 1, 'updated_at' => $now]);
        // a „bank-wait” cikk már szerepelt egy korábbi havi lapon (követőkód nélkül)
        $this->db->insert('articles', ['client_id' => $x, 'month' => '2609', 'url' => 'https://vemcogroup.com/blog/bank-wait', 'url_hash' => Url::hash('https://vemcogroup.com/blog/bank-wait'), 'created_at' => $now, 'updated_at' => $now]);

        $rss = str_replace('September news', 'October news roundup', CollectParsingTest::RSS);
        $rss = str_replace('</channel>', '<item><title>Fresh October insight on queues</title><link>https://www.vemcogroup.com/blog/fresh</link><pubDate>Wed, 15 Oct 2026 10:00:00 +0000</pubDate></item></channel>', $rss);
        $web = new FakeWeb(['https://www.vemcogroup.com/blog/rss.xml' => $rss, 'https://idegen.com/rss.xml' => CollectParsingTest::RSS]);
        $dir = sys_get_temp_dir() . '/xp-col-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $svc = new CollectService($this->db, new Collector($web), new LocalLog($dir . '/n.jsonl'), $dir . '/l.lock');

        $r = $svc->run(Access::forUser($this->db, $op, $x), '2610');
        self::assertTrue($r->ok, $r->summary());
        self::assertSame(['Fresh October insight on queues'], array_column($r->data['items'], 'title'));
        $s = $r->data['sources'][0];
        self::assertSame(['found' => 4, 'in_month' => 3, 'duplicate' => 1, 'excluded' => 1, 'kept' => 1], array_intersect_key($s, array_flip(['found', 'in_month', 'duplicate', 'excluded', 'kept'])));
        self::assertCount(1, $r->data['sources'], 'csak az aktív, saját források');
        self::assertNotContains('https://idegen.com/rss.xml', $web->requested);
        self::assertNotContains('https://off.com/blog', $web->requested);
        self::assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM articles'), 'a gyűjtés nem ír az adatbázisba');
    }
}

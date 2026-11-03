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
use Xperion\Collect\Collector;
use Xperion\Collect\SourceDiscovery;
use Xperion\Collect\SourceService;
use Xperion\Tests\DbTestCase;
use Xperion\Tests\FakeClaude;
use Xperion\Tests\FakeWeb;

final class SourceDiscoveryTest extends DbTestCase
{
    private int $op;
    private int $x;
    private FakeClaude $claude;
    private SourceDiscovery $disc;
    private SourceService $sources;
    private UserAdmin $users;

    protected function setUp(): void
    {
        parent::setUp();
        $audit = new AuditLog($this->db);
        $this->users = new UserAdmin($this->db, new TokenService($this->db), $audit);
        $this->op = $this->users->createFirstOperator('Balázs', 'b@pelda.hu', 'eleg-hosszu-jelszo');
        $this->x = (new ClientAdmin($this->db, $audit))->create(Access::forUser($this->db, $this->op, null), 'Xperion', 'xperion.hu');
        $web = new FakeWeb(['https://retailhirek.test/rss.xml' => CollectParsingTest::RSS, 'https://bankfiok.test/feed' => CollectParsingTest::RSS]);
        $collector = new Collector($web);
        $this->sources = new SourceService($this->db, $audit, $collector);
        $this->sources->saveSource($this->op(), null, ['url' => 'https://www.figyelt.test/hirek', 'name' => 'Figyelt', 'topics' => 'várakozás, retail', 'active' => true]);
        $this->sources->saveKeyword($this->op(), null, ['keyword' => 'sorszámhúzó', 'topic' => 'ügyfélirányítás']);
        $this->sources->saveKeyword($this->op(), null, ['keyword' => 'IPO', 'is_exclude' => true]);
        $this->claude = new FakeClaude();
        $this->claude->discovered = [
            ['name' => 'Figyelt aloldal', 'url' => 'https://figyelt.test/blog', 'type' => 'szakmai média', 'topics' => 'x', 'reason' => 'már figyelt'],
            ['name' => 'Saját', 'url' => 'https://xperion.hu/hirek', 'type' => '', 'topics' => '', 'reason' => 'az ügyfél saját oldala'],
            ['name' => 'Halott oldal', 'url' => 'https://halott.test/news', 'type' => 'szakmai média', 'topics' => 'retail', 'reason' => 'nem működik'],
            ['name' => 'RetailHírek', 'url' => 'https://retailhirek.test/rss.xml', 'type' => 'szakmai média', 'topics' => 'retail, várakozás', 'reason' => 'Havonta több cikk a bolti várakozásról.'],
            ['name' => 'Bankfiók', 'url' => 'https://bankfiok.test/feed', 'type' => 'szakmai média', 'topics' => 'bank', 'reason' => 'Bankfiók-hírek.'],
            ['name' => 'Harmadik', 'url' => 'https://bankfiok.test/masik', 'type' => '', 'topics' => '', 'reason' => 'ugyanaz a webhely'],
        ];
        $this->disc = new SourceDiscovery($this->db, $audit, new ClaudeClient($this->claude, 'teszt-kulcs'), $collector);
    }

    private function op(): Access
    {
        return Access::forUser($this->db, $this->op, $this->x);
    }

    public function testSearchVerifiesAndFiltersCandidates(): void
    {
        $this->claude->pauseOnce = true;
        $r = $this->disc->search($this->op(), 2);
        self::assertSame(2, $r['found']);
        self::assertSame(['RetailHírek', 'Bankfiók'], array_column($r['pending'], 'name'), 'a figyelt, a saját és a nem működő oldal kimarad');
        self::assertSame(['Halott oldal: A listaoldalon nem találtam cikkhivatkozást.'], array_map(static fn ($f) => preg_replace('/: .*/', ': A listaoldalon nem találtam cikkhivatkozást.', $f), $r['failed']));
        self::assertGreaterThan(0, $r['pending'][0]['count']);
        self::assertSame(2, $this->disc->count($this->x), 'a beállított darabszám megmarad');

        // a kérés: webkereső eszköz, a témák, a kulcsszavak és a már figyelt webhelyek a promptban; pause_turn után folytatás
        self::assertCount(2, $this->claude->requests);
        $first = $this->claude->requests[0]['body'];
        self::assertSame('web_search_20260209', $first['tools'][0]['type']);
        self::assertArrayNotHasKey('format', $first['output_config']);
        $prompt = $first['messages'][0]['content'];
        foreach (['ügyfélirányítás', 'sorszámhúzó', 'Kizárt témák (ilyen forrás ne legyen): IPO', 'figyelt.test', 'xperion.hu', 'Keress 4 új forrást'] as $needle) {
            self::assertStringContainsString($needle, $prompt);
        }
        self::assertSame('assistant', $this->claude->requests[1]['body']['messages'][1]['role']);

        // újrakeresés: a már javasolt címeket nem hozza újra
        $r2 = $this->disc->search($this->op(), 3);
        self::assertSame(0, $r2['found']);
        self::assertCount(2, $r2['pending']);
    }

    public function testAcceptAndDismiss(): void
    {
        $p = $this->disc->search($this->op(), 2)['pending'];
        $data = $this->disc->accept($this->op(), $p[0]['id'], $this->sources);
        $added = array_values(array_filter($data['sources'], static fn ($s) => $s['name'] === 'RetailHírek'))[0];
        self::assertTrue($added['active'] && $added['is_new'], 'próbaidős „új forrás” jelöléssel kerül be');
        $this->disc->dismiss($this->op(), $p[1]['id']);
        self::assertSame([], $this->disc->pending($this->op()));
        self::assertSame('elvetve', $this->db->value('SELECT status FROM source_suggestions WHERE id = ?', [$p[1]['id']]));
        $this->expectException(\InvalidArgumentException::class);
        $this->disc->dismiss($this->op(), $p[1]['id']);
    }

    public function testJsonFromTextAndPermission(): void
    {
        self::assertSame(['a' => 1], ClaudeClient::jsonFromText("Szöveg {nem json}\n```json\n{\"a\":1}\n```"));
        self::assertSame(['b' => 2], ClaudeClient::jsonFromText('Előtte {"b":2} utána'));
        self::assertNull(ClaudeClient::jsonFromText('nincs benne'));
        $inv = $this->users->invite($this->op(), 'Emi', 'emi@x.hu', $this->x, 'tartalom');
        $this->users->setPasswordWithToken((string) $inv['token'], TokenService::INVITE, 'jelszo-tartalom-1');
        $this->expectException(AccessDenied::class);
        $this->disc->search(Access::forUser($this->db, $inv['user_id'], null), 3);
    }
}

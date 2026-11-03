<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\TokenService;
use Xperion\Auth\UserAdmin;
use Xperion\Claude\ClaudeClient;
use Xperion\Config;
use Xperion\Db\Database;
use Xperion\Import\Url;
use Xperion\Log\LocalLog;
use Xperion\Press\PressKey;
use Xperion\Press\SourceText;
use Xperion\Press\UploadService;
use Xperion\Press\WpBridgeClient;
use Xperion\Task\TaskContext;
use Xperion\Tests\DbTestCase;
use Xperion\Tests\FakeClaude;
use Xperion\Tests\FakeMailer;
use Xperion\Tests\FakeWeb;
use Xperion\Tests\FakeWpBridge;

final class UploadServiceTest extends DbTestCase
{
    private FakeWpBridge $wp;
    private FakeClaude $claude;
    private FakeMailer $mailer;
    private UploadService $svc;
    private int $op;
    private int $x;

    protected function setUp(): void
    {
        parent::setUp();
        $audit = new AuditLog($this->db);
        $users = new UserAdmin($this->db, new TokenService($this->db), $audit);
        $this->op = $users->createFirstOperator('Balázs', 'b@pelda.hu', 'eleg-hosszu-jelszo');
        $this->x = (new ClientAdmin($this->db, $audit))->create(Access::forUser($this->db, $this->op, null), 'Xperion', 'xperion.hu');
        $this->db->run("UPDATE clients SET status = 'aktiv' WHERE id = ?", [$this->x]);
        $this->wp = new FakeWpBridge();
        $this->claude = new FakeClaude();
        $this->mailer = new FakeMailer();
        $web = new FakeWeb(['https://src.test/bank' => '<html><body><nav>Menü</nav><article><h1>How a bank cut wait times</h1>' . str_repeat('<p>Queue management at the branch reduced waiting by a third for customers.</p>', 8) . '</article></body></html>']);
        $dir = sys_get_temp_dir() . '/xp-up-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $this->svc = new UploadService($this->db, new ClaudeClient($this->claude, 'k'), fn (array $c) => new WpBridgeClient($this->wp, 'https://xperion.hu', 'cikkfigyelo', 'abcd efgh'),
            new SourceText($web), $this->mailer, $audit, new LocalLog($dir . '/n.jsonl'), $dir . '/l.lock', static function (int $ms): void {});
    }

    private function art(string $url, string $dec = 'mehet', string $title = 'How a bank cut wait times', ?string $date = '2026-10-02'): int
    {
        $now = Database::now();
        return $this->db->insert('articles', ['client_id' => $this->x, 'month' => '2610', 'source_name' => 'Vemco', 'title' => $title, 'description' => 'Rövid leírás.', 'url' => $url, 'url_hash' => Url::hash($url),
            'published_on' => $date, 'recommendation' => 'Javasolt', 'decision' => $dec, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function ctx(bool $dry): TaskContext
    {
        return new TaskContext($dry, new \DateTimeImmutable('2026-11-02 10:00'), static fn () => throw new \LogicException());
    }

    private function row(int $id): array
    {
        return $this->db->one('SELECT * FROM articles WHERE id = ?', [$id]);
    }

    public function testPressKeyFollowsPluginSpec(): void
    {
        self::assertSame('https://example.com/Cikk?a=1&b=2', PressKey::normalizeUrl('https://EXAMPLE.com/Cikk?utm_source=x&a=1&fbclid=y&b=2&GCLID=z#szakasz'));
        self::assertSame('p-' . substr(hash('sha256', 'https://example.com/cikk'), 0, 20), PressKey::key('https://Example.com/cikk?utm_medium=e#x'));
        self::assertMatchesRegularExpression('/^[a-z0-9-]{3,80}$/', PressKey::key('https://a.hu/x'));
        self::assertFalse(PressKey::acceptable('http://a.hu/x'));
        self::assertFalse(PressKey::acceptable('https://u:p@a.hu/x'));
    }

    public function testDryRunTranslatesButWritesNothing(): void
    {
        $id = $this->art('https://src.test/bank?utm_source=nl');
        $r = $this->svc->runForClient($this->x, $this->ctx(true));
        self::assertSame('szaraz', $r['items'][0]['state']);
        self::assertSame('HU: How a bank cut wait times', $r['items'][0]['hu']['title']);
        self::assertSame(['xperion_press_get_pair'], array_column($this->wp->calls, 'tool'), 'szárazon csak olvas');
        self::assertStringContainsString('Queue management', end($this->claude->requests)['body']['messages'][0]['content'], 'a forrásszöveg bekerül');
        self::assertStringNotContainsString('Menü', end($this->claude->requests)['body']['messages'][0]['content']);
        $row = $this->row($id);
        self::assertSame([0, '', ''], [(int) $row['uploaded'], $row['hu_title'], $row['press_key']]);
    }

    public function testLiveDeployWritesBackAndIsIdempotent(): void
    {
        $id = $this->art('https://src.test/bank?utm_source=nl');
        $skip = $this->art('https://src.test/other', 'linkedin');
        $r = $this->svc->runForClient($this->x, $this->ctx(false));
        self::assertSame('kint', $r['items'][0]['state']);
        self::assertCount(1, $r['items'], 'csak a Mehet cikk megy fel');
        $deploy = $this->wp->calls[1];
        self::assertSame('xperion_press_deploy_pair', $deploy['tool']);
        self::assertSame('Basic ' . base64_encode('cikkfigyelo:abcd efgh'), $deploy['auth']);
        self::assertSame('https://xperion.hu/wp-json/xperion-bridge/v1/press', $deploy['url']);
        self::assertSame('https://src.test/bank', $deploy['args']['source_url']);
        self::assertSame(PressKey::key('https://src.test/bank'), $deploy['args']['key']);
        self::assertSame('2026-10-02', $deploy['args']['date']);
        $row = $this->row($id);
        self::assertSame(1, (int) $row['uploaded']);
        self::assertSame('kint', $row['wp_state']);
        self::assertSame('https://xperion.hu/hu/p100/', $row['url_hu']);
        self::assertSame('https://xperion.hu/en/p101/', $row['url_en']);
        self::assertSame(101, (int) $row['wp_en_id']);
        self::assertSame('Magyar ajánló.', $row['hu_summary']);
        self::assertSame(0, (int) $this->row($skip)['uploaded']);

        // újrafuttatás: nincs teendő, nincs újabb deploy
        $calls = count($this->wp->calls);
        $this->svc->runForClient($this->x, $this->ctx(false));
        self::assertCount($calls, $this->wp->calls);

        // ha a WordPressben már kint van (pl. egy megszakadt futás után), csak visszaír
        $id2 = $this->art('https://src.test/again', 'mehet', 'Again');
        $k = PressKey::key('https://src.test/again');
        $this->wp->pairs[$k] = ['state' => 'published', 'posts' => [['id' => 7, 'language' => 'hu', 'url' => 'https://xperion.hu/hu/7/'], ['id' => 8, 'language' => 'en', 'url' => 'https://xperion.hu/en/8/']]];
        $claudeCalls = count($this->claude->requests);
        $this->svc->runForClient($this->x, $this->ctx(false));
        self::assertSame(['kint', 7], [$this->row($id2)['wp_state'], (int) $this->row($id2)['wp_hu_id']]);
        self::assertCount($claudeCalls, $this->claude->requests, 'kint lévő párnál nincs fordítás');
    }

    public function testRevertUploadAndDeleteRestoreArticle(): void
    {
        $op = Access::forUser($this->db, $this->op, $this->x);
        $id = $this->art('https://src.test/bank');
        $this->svc->runForClient($this->x, $this->ctx(false));
        self::assertSame(1, (int) $this->row($id)['uploaded']);

        // a WordPress szerint még kint van: megerősítést kér, és nem módosít
        $r = $this->svc->revert($op, $id, false);
        self::assertTrue($r['needsConfirm']);
        self::assertStringContainsString('Végleges törlés', $r['message']);
        self::assertSame(1, (int) $this->row($id)['uploaded']);
        // a bejegyzéseket törölték a WordPressben: visszavonható; a kész fordítás megmarad
        $this->wp->pairs = [];
        self::assertTrue($this->svc->revert($op, $id, false)['ok']);
        $row = $this->row($id);
        self::assertSame([0, '', '', ''], [(int) $row['uploaded'], $row['decision'], $row['wp_state'], $row['url_hu']]);
        self::assertSame('Magyar ajánló.', $row['hu_summary']);
        self::assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM audit_log WHERE action = 'Feltöltés visszavonva'"));
        try {
            $this->svc->revert($op, $id, true);
            self::fail('nem feltöltött cikknél hiba várt');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('nincs feltöltve', $e->getMessage());
        }

        // törlés a listából és visszaállítás ugyanazzal az azonosítóval
        $articles = new \Xperion\Monthly\ArticleService($this->db, new AuditLog($this->db));
        $articles->remove($op, $id);
        self::assertNull($this->db->one('SELECT id FROM articles WHERE id = ?', [$id]));
        self::assertCount(0, $articles->list($op, '2610'));
        $back = $articles->restore($op, $id);
        self::assertSame($id, $back['id']);
        self::assertSame('Magyar ajánló.', $back['hu_summary']);
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM deleted_articles'));
        // publikált cikk nem törölhető
        $this->db->run('UPDATE articles SET uploaded = 1 WHERE id = ?', [$id]);
        $this->expectException(\InvalidArgumentException::class);
        $articles->remove($op, $id);
    }

    public function testImageRejectedByWordpressFallsBackToConvertedImageOrDefault(): void
    {
        $msg = 'Featured image import failed: Sorry, you are not allowed to upload this file type. Inspect existing pair state (get_pair) before retrying a write.';
        // 1) átalakított kép a saját címünkről
        $im = imagecreatetruecolor(400, 300); ob_start(); imagepng($im); $png = (string) ob_get_clean();
        $web = new FakeWeb(['https://src.test/kep-cikk' => '<meta property="og:image" content="https://cdn.test/x?f=webp">', 'https://cdn.test/x?f=webp' => $png]);
        $dir = sys_get_temp_dir() . '/xp-kep-' . bin2hex(random_bytes(4));
        $audit = new AuditLog($this->db);
        $logDir = sys_get_temp_dir() . '/xp-up2-' . bin2hex(random_bytes(4)); mkdir($logDir);
        $svc = new UploadService($this->db, new ClaudeClient($this->claude, 'k'), fn (array $c) => new WpBridgeClient($this->wp, 'https://xperion.hu', 'cikkfigyelo', 'abcd efgh'),
            new SourceText($web), $this->mailer, $audit, new LocalLog($logDir . '/n.jsonl'), $logDir . '/l.lock', static function (int $ms): void {},
            new \Xperion\Press\FeaturedImage($web, $dir, 'https://app.test/'));
        $id = $this->art('https://src.test/kep-cikk');
        $this->wp->imageFailFor['https://src.test/kep-cikk'] = $msg;
        $r = $svc->runForClient($this->x, $this->ctx(false));
        self::assertSame('kint', $r['items'][0]['state'], $r['items'][0]['error'] ?? '');
        $deploys = array_values(array_filter($this->wp->calls, static fn ($c) => $c['tool'] === 'xperion_press_deploy_pair'));
        self::assertStringStartsWith('https://app.test/kepek/', $deploys[1]['args']['featured_image_url']);
        self::assertContains('xperion_press_get_pair', array_column($this->wp->calls, 'tool'));

        // 2) átalakító nélkül: az ügyfél alapértelmezett képe
        $id2 = $this->art('https://src.test/kep-ketto', 'mehet', 'Masodik');
        $this->wp->imageFailFor['https://src.test/kep-ketto'] = $msg;
        $this->db->insert('client_settings', ['client_id' => $this->x, 'name' => 'alap_kep', 'value' => '77', 'updated_at' => Database::now()]);
        $this->svc->runForClient($this->x, $this->ctx(false));
        self::assertSame('kint', $this->row($id2)['wp_state']);
        $last = end($this->wp->calls);
        self::assertSame(77, $last['args']['featured_media']);

        // 3) se átalakítás, se alapkép: érthető teendő
        $this->db->run("DELETE FROM client_settings WHERE name = 'alap_kep'");
        $id3 = $this->art('https://src.test/kep-harom', 'mehet', 'Harmadik');
        $this->wp->imageFailFor['https://src.test/kep-harom'] = $msg;
        $this->svc->runForClient($this->x, $this->ctx(false));
        self::assertSame('hiba', $this->row($id3)['wp_state']);
        self::assertStringContainsString('alapértelmezett kiemelt képet', $this->row($id3)['upload_error']);
    }

    public function testUploadPermissionForClientRoles(): void
    {
        foreach (['uzemelteto' => true, 'ugyfeladmin' => true, 'tartalom' => true, 'olvaso' => false] as $role => $ok) {
            self::assertSame($ok, \Xperion\Auth\Roles::grants($role, 'upload'), $role);
        }
    }

    public function testErrorClassesFollowPluginTable(): void
    {
        $dup = $this->art('https://src.test/dup', 'mehet', 'Dup');
        $img = $this->art('https://src.test/img', 'mehet', 'Img');
        $http = $this->art('http://src.test/plain', 'mehet', 'Plain');
        $this->wp->failFor['https://src.test/dup'] = 'Source URL already exists outside this pair.';
        $this->wp->failFor['https://src.test/img'] = 'No HTTPS og:image found on the source page.';
        $r = $this->svc->runForClient($this->x, $this->ctx(false));
        self::assertSame(['duplikatum', 'hiba', 'hiba'], [$this->row($dup)['wp_state'], $this->row($img)['wp_state'], $this->row($http)['wp_state']]);
        self::assertSame(0, (int) $this->row($dup)['upload_attempts'], 'duplikátumot nem számol és nem próbál újra');
        self::assertSame('HU: Img', $this->row($img)['hu_title'], 'a fordítás megmarad a következő próbához');
        self::assertStringContainsString('https', $this->row($http)['upload_error']);
        self::assertCount(1, $this->mailer->sent, 'egy összesítő értesítés');
        self::assertStringContainsString('og:image', $this->mailer->sent[0]->text);

        // második és harmadik próba: a fordítást nem kéri újra; a 3. után leáll
        $claudeCalls = count($this->claude->requests);
        $this->svc->runForClient($this->x, $this->ctx(false));
        $this->svc->runForClient($this->x, $this->ctx(false));
        self::assertCount($claudeCalls, $this->claude->requests);
        self::assertSame(3, (int) $this->row($img)['upload_attempts']);
        $deploys = count(array_filter($this->wp->calls, static fn ($c) => $c['tool'] === 'xperion_press_deploy_pair'));
        $this->svc->runForClient($this->x, $this->ctx(false));
        self::assertSame($deploys, count(array_filter($this->wp->calls, static fn ($c) => $c['tool'] === 'xperion_press_deploy_pair')), 'a korlát után nem próbálja');

        // kézi újrapróbálás engedélyezése
        unset($this->wp->failFor['https://src.test/img']);
        $this->svc->retry(Access::forUser($this->db, $this->op, $this->x), $img);
        $this->svc->runForClient($this->x, $this->ctx(false));
        self::assertSame('kint', $this->row($img)['wp_state']);
    }

    public function testAuthErrorStopsTheWholeRun(): void
    {
        $a = $this->art('https://src.test/a', 'mehet', 'A');
        $this->art('https://src.test/b', 'mehet', 'B');
        $this->wp->status = 401;
        $r = $this->svc->runForClient($this->x, $this->ctx(false));
        self::assertTrue($r['stopped']);
        self::assertCount(1, $this->wp->calls, 'az első hívás után megáll');
        self::assertSame(0, (int) $this->row($a)['upload_attempts'], 'belépési hiba nem fogyasztja a próbálkozást');
        self::assertStringStartsWith('Leállt a feltöltés', $this->mailer->sent[0]->subject);
    }

    public function testAuthErrorsAreDiagnosed(): void
    {
        $op = Access::forUser($this->db, $this->op, $this->x);
        $this->wp->status = 401;
        $this->wp->authCode = 'incorrect_password';
        $this->wp->me = [401, ['code' => 'incorrect_password', 'message' => 'x']];
        try {
            $this->svc->check($op);
            self::fail('hiba várt');
        } catch (\Xperion\Press\WpException $e) {
            self::assertStringContainsString('alkalmazásjelszó hibás', $e->getMessage());
        }
        // a fejlécet a tárhely kiszűri: a WordPress nem látja a belépést
        $this->wp->authCode = 'rest_forbidden';
        $this->wp->me = [401, ['code' => 'rest_not_logged_in', 'message' => 'You are not currently logged in.']];
        try {
            $this->svc->check($op);
            self::fail('hiba várt');
        } catch (\Xperion\Press\WpException $e) {
            self::assertStringContainsString('xperion-auth-fejlec.php', $e->getMessage());
            self::assertStringContainsString('mu-plugins', $e->getMessage());
        }
        // a tartalék fejléc is megy, ugyanazzal a belépéssel
        $last = end($this->wp->calls);
        self::assertSame($last['auth'], $last['alt']);
        self::assertStringStartsWith('Basic ', $last['alt']);
        // a kiegészítő jelentése szerinti pontos ok
        $ap = ['application-passwords' => []];
        foreach ([
            [['xperion_auth_header' => '1.0.0'], 'régi változata'],
            [['xperion_auth_header' => '1.1.0', 'xperion_auth_seen' => ['authorization' => false, 'alt' => false, 'failed' => '', 'logged_in' => false]], 'minden belépési fejlécet kiszűr'],
            [['xperion_auth_header' => '1.1.0', 'xperion_auth_seen' => ['authorization' => true, 'alt' => true, 'failed' => 'incorrect_password', 'logged_in' => false]], 'alkalmazásjelszó hibás'],
            [['xperion_auth_header' => '1.1.0', 'xperion_auth_seen' => ['authorization' => false, 'alt' => true, 'failed' => '', 'logged_in' => false]], 'megérkezik a WordPressbe'],
        ] as [$extra, $expect]) {
            $this->wp->index = ['name' => 'Xperion', 'authentication' => $ap] + $extra;
            try {
                $this->svc->check($op);
                self::fail('hiba várt');
            } catch (\Xperion\Press\WpException $e) {
                self::assertStringContainsString($expect, $e->getMessage());
            }
        }
        // a webhely lekérdezése is viszi a belépést
        $idxCall = array_values(array_filter($this->wp->calls, static fn ($c) => $c['tool'] === 'index'));
        self::assertNotEmpty($idxCall);
        self::assertStringStartsWith('Basic ', $idxCall[0]['alt']);
        $this->wp->index = null;
        // a webhely átirányít (pl. www-re): a belépési adat elveszik
        $this->wp->finalUrl = 'https://www.xperion.hu/wp-json/';
        try {
            $this->svc->check($op);
            self::fail('hiba várt');
        } catch (\Xperion\Press\WpException $e) {
            self::assertStringContainsString("'base_url' => 'https://www.xperion.hu'", $e->getMessage());
            self::assertStringNotContainsString('.htaccess', $e->getMessage());
        }
        $this->wp->finalUrl = '';
        // az alkalmazásjelszavak nem elérhetők (biztonsági bővítmény vagy nem érzékelt HTTPS)
        $this->wp->index = ['name' => 'Xperion', 'authentication' => []];
        try {
            $this->svc->check($op);
            self::fail('hiba várt');
        } catch (\Xperion\Press\WpException $e) {
            self::assertStringContainsString('alkalmazásjelszavak ezen a webhelyen nem használhatók', $e->getMessage());
            self::assertStringContainsString("\$_SERVER['HTTPS'] = 'on'", $e->getMessage());
        }
        $this->wp->index = null;
        // a belépés jó, de a végpont tiltja (jogosultság)
        $this->wp->status = 403;
        $this->wp->me = [200, ['name' => 'cikkfigyelo', 'roles' => ['subscriber']]];
        try {
            $this->svc->check($op);
            self::fail('hiba várt');
        } catch (\Xperion\Press\WpException $e) {
            self::assertStringContainsString('A belépés jó (cikkfigyelo, szerepkör: subscriber)', $e->getMessage());
        }
        self::assertStringContainsString('WordPress-felhasználónév', \Xperion\Press\WpBridgeClient::authMessage(401, 'invalid_username', ''));
    }

    public function testWordpressConfigLookup(): void
    {
        $c = new Config(['wordpress' => ['xperion.hu' => ['user' => 'u', 'app_password' => 'p'], 'mas.hu' => ['user' => '', 'app_password' => '']]]);
        self::assertSame(['base_url' => 'https://xperion.hu', 'user' => 'u', 'app_password' => 'p'], $c->wordpressSite('https://www.Xperion.hu/'));
        self::assertNull($c->wordpressSite('mas.hu'), 'üres belépés = nincs beállítva');
        self::assertSame(['xperion.hu'], $c->wordpressSites());
        $ctx = $this->svc->check(Access::forUser($this->db, $this->op, $this->x));
        self::assertSame(['0.6.0', true, 'Sajtószemle (hu)', 'Press review (en)'], [$ctx['version'], $ctx['can_publish'], $ctx['hu_category'], $ctx['en_category']]);
    }
}

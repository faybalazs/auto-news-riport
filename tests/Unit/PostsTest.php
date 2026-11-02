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
use Xperion\Posts\IdeaService;
use Xperion\Posts\PostService;
use Xperion\Posts\PostWriter;
use Xperion\Posts\RuleService;
use Xperion\Tests\DbTestCase;
use Xperion\Tests\FakeClaude;
use Xperion\Tests\FakeMailer;

final class PostsTest extends DbTestCase
{
    private FakeClaude $claude;
    private FakeMailer $mailer;
    private RuleService $rules;
    private IdeaService $ideas;
    private PostService $posts;
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
        $this->x = $clients->create(Access::forUser($this->db, $this->op, null), 'Xperion', 'xperion.hu');
        $this->other = $clients->create(Access::forUser($this->db, $this->op, null), 'Másik', '');
        $this->claude = new FakeClaude();
        $this->mailer = new FakeMailer();
        $this->rules = new RuleService($this->db, $audit);
        $this->ideas = new IdeaService($this->db, $audit);
        $this->posts = new PostService($this->db, new ClaudeClient($this->claude, 'k'), $this->rules, $this->mailer, $audit, 'https://app.test/');
    }

    private function op(?int $c = null): Access
    {
        return Access::forUser($this->db, $this->op, $c ?? $this->x);
    }

    private function member(string $name, string $email, string $role): Access
    {
        $inv = $this->users->invite($this->op(), $name, $email, $this->x, $role);
        $this->users->setPasswordWithToken((string) $inv['token'], TokenService::INVITE, 'jelszo-' . $role . '-1');
        return Access::forUser($this->db, $inv['user_id'], null);
    }

    private function art(string $month, string $title, string $dec = 'linkedin'): int
    {
        $u = 'https://src.test/' . md5($title);
        $now = Database::now();
        return $this->db->insert('articles', ['client_id' => $this->x, 'month' => $month, 'source_name' => 'Vemco', 'title' => $title, 'url' => $u, 'url_hash' => Url::hash($u), 'recommendation' => 'Javasolt', 'decision' => $dec, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function testRulesCrudOrderAndScope(): void
    {
        $this->rules->save($this->op(), null, 'Hangnem', 'Tárgyilagos');
        $r = $this->rules->save($this->op(), null, 'Hossz', '900–1300 karakter');
        self::assertSame(['Hangnem', 'Hossz'], array_column($r, 'name'));
        $r = $this->rules->reorder($this->op(), [$r[1]['id'], $r[0]['id']]);
        self::assertSame(['Hossz', 'Hangnem'], array_column($r, 'name'));
        $r = $this->rules->save($this->op(), $r[0]['id'], 'Hossz', '1000 karakter');
        self::assertSame("- Hossz: 1000 karakter\n- Hangnem: Tárgyilagos", $this->rules->asText($this->x));
        self::assertSame([], $this->rules->all($this->op($this->other)), 'ügyfélhatár');
        try {
            $this->rules->delete($this->op($this->other), $r[0]['id']);
            self::fail('más ügyfél szabálya nem törölhető');
        } catch (\InvalidArgumentException) {
        }
        $emi = $this->member('Emi', 'emi@x.hu', 'tartalom');
        self::assertCount(1, $this->rules->delete($emi, $r[0]['id']), 'a tartalomkezelő szerkesztheti a szabályokat');
        $this->expectException(AccessDenied::class);
        $this->rules->save($this->member('Olvasó', 'o@x.hu', 'olvaso'), null, 'X', 'Y');
    }

    public function testIdeaToPostAndApprovalFlow(): void
    {
        $this->rules->save($this->op(), null, 'Hangnem', 'Tárgyilagos');
        $emi = $this->member('Emi', 'emi@x.hu', 'tartalom');
        $attila = $this->member('Attila', 'attila@x.hu', 'ugyfeladmin');
        $idea = $this->ideas->save($emi, '', ['topic' => 'Új referencia', 'message' => 'Bankfiók átadás', 'type' => 'esettanulmány', 'link' => 'https://xperion.hu/ref']);
        self::assertMatchesRegularExpression('/^OT-\d{4}-01$/', $idea['code']);
        self::assertSame('Emi', $idea['submitter']);

        $code = $this->posts->request($emi, 'otlet', $idea['code']);
        self::assertMatchesRegularExpression('/^LI-\d{4}-01$/', $code);
        $req = end($this->claude->requests)['body'];
        self::assertSame('claude-opus-5-5', $req['model']);
        self::assertStringContainsString('- Hangnem: Tárgyilagos', $req['system'][0]['text'], 'a friss szabályok a rendszerpromptban');
        self::assertStringContainsString('Bankfiók átadás', $req['messages'][0]['content']);
        $p = $this->posts->get($emi, $code);
        self::assertSame('tervezet', $p['status']);
        self::assertSame('Kontextus a szövegben.', $p['context'], 'a link nem maradhat a poszt szövegében');
        self::assertSame("Mit mér egy bankfiók, amikor a várakozást méri?\n\nKontextus a szövegben.\n\n– egy\n– kettő\n\nSzemszög.\n\nZárás?\n\n#ügyfélélmény #retail", $p['full_text']);
        self::assertSame('Mért várakozás', $p['image']['title']);
        self::assertSame(['elkeszult', $code], [$this->ideas->get($emi, $idea['code'])['status'], $this->ideas->get($emi, $idea['code'])['post_code']]);
        self::assertSame('Generálva', $p['events'][0]['action']);

        try {
            $this->posts->transition($emi, $code, 'kuldes');
            self::fail('csak az üzemeltető küldi az ügyfélnek');
        } catch (AccessDenied) {
        }
        $this->posts->transition($this->op(), $code, 'kuldes');
        self::assertSame(['attila@x.hu'], end($this->mailer->sent)->to, 'az ügyfél-adminisztrátor kap értesítést');
        self::assertStringContainsString('https://app.test/index.php?p=poszt&kod=' . $code, end($this->mailer->sent)->text);
        try {
            $this->posts->transition($attila, $code, 'modositas', '');
            self::fail('módosításhoz megjegyzés kell');
        } catch (\InvalidArgumentException) {
        }
        $p = $this->posts->transition($attila, $code, 'modositas', 'Rövidebb nyitó sort kérünk.');
        self::assertSame(['tervezet', 'Rövidebb nyitó sort kérünk.'], [$p['status'], $p['change_request']]);

        $this->posts->request($this->op(), 'ujra', $code);
        self::assertStringContainsString('Rövidebb nyitó sort kérünk.', end($this->claude->requests)['body']['messages'][0]['content'], 'az újragenerálás megkapja a kérést');
        self::assertSame($code, $this->posts->all($this->op())[0]['code'], 'ugyanaz a kód marad');

        $this->posts->transition($this->op(), $code, 'kuldes');
        $p = $this->posts->transition($attila, $code, 'jovahagyas');
        self::assertSame(['jovahagyva', ''], [$p['status'], $p['change_request']]);
        self::assertSame(['emi@x.hu', 'b@pelda.hu'], end($this->mailer->sent)->to, 'a közzétevő és az üzemeltető kap értesítést');
        try {
            $this->posts->update($attila, $code, ['opening' => 'X']);
            self::fail('jóváhagyott posztot csak az üzemeltető szerkeszt');
        } catch (\InvalidArgumentException) {
        }
        $p = $this->posts->transition($emi, $code, 'megjelent');
        self::assertSame('megjelent', $p['status']);
        self::assertSame('utemezve', $this->ideas->get($emi, $idea['code'])['status']);
        self::assertSame(['Megjelent a LinkedInen', 'Jóváhagyva'], array_slice(array_column($p['events'], 'action'), 0, 2));
    }

    public function testMonthlyPostSingleArticlePostAndDelete(): void
    {
        $this->rules->save($this->op(), null, 'Hangnem', 'Tárgyilagos');
        $a1 = $this->art('2610', 'Bank cuts wait');
        $a2 = $this->art('2610', 'Retail traffic');
        $a3 = $this->art('2610', 'Kiosk study');
        $this->art('2610', 'Mehet only', 'mehet');

        $single = $this->posts->request($this->op(), 'cikk', (string) $a3);
        self::assertSame('LI-2610-01', $single);
        self::assertSame([['month' => '2610', 'label' => '2026. október', 'n' => 2]], $this->posts->openMonths($this->op()));

        $monthly = $this->posts->request($this->op(), 'cikkek', '2610');
        self::assertSame('LI-2610-02', $monthly);
        $content = end($this->claude->requests)['body']['messages'][0]['content'];
        self::assertStringContainsString('Bank cuts wait', $content);
        self::assertStringContainsString('Retail traffic', $content);
        self::assertStringNotContainsString('Kiosk study', $content, 'a külön posztos cikk nem kerül a havi posztba');
        self::assertStringNotContainsString('Mehet only', $content);
        self::assertSame([$a1, $a2], array_column($this->posts->get($this->op(), $monthly)['articles'], 'id'));
        self::assertSame([], $this->posts->openMonths($this->op()));

        try {
            $this->posts->request($this->op(), 'cikkek', '2610');
            self::fail('nincs több poszt nélküli cikk');
        } catch (\InvalidArgumentException) {
        }

        $this->posts->delete($this->op(), $monthly);
        self::assertNull($this->posts->get($this->op(), $monthly));
        self::assertSame('', (string) $this->db->value('SELECT post_code FROM articles WHERE id = ?', [$a1]), 'a cikkek újra küldhetők');
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM post_events WHERE post_code = ?', [$monthly]));
    }

    public function testFailedRequestIsKeptAndRetriedByHourlyRun(): void
    {
        $this->rules->save($this->op(), null, 'Hangnem', 'Tárgyilagos');
        $this->db->run("UPDATE clients SET status = 'aktiv' WHERE id = ?", [$this->x]);
        $a = $this->art('2610', 'Bank');
        $this->claude->stopReason = 'max_tokens';
        try {
            $this->posts->request($this->op(), 'cikk', (string) $a);
            self::fail('hiba várt');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('az óránkénti futás újrapróbálja', $e->getMessage());
        }
        self::assertSame([['kind' => 'cikk', 'ref' => (string) $a, 'attempts' => 1, 'error' => 'A válasz túl hosszú lett, és megszakadt (max_tokens).']], $this->posts->pendingJobs($this->op()));
        $this->claude->stopReason = null;
        $ctx = new \Xperion\Task\TaskContext(false, new \DateTimeImmutable(), static fn () => throw new \LogicException());
        $this->posts->runPending($ctx);
        self::assertSame([], $this->posts->pendingJobs($this->op()));
        self::assertSame('LI-2610-01', (string) $this->db->value('SELECT post_code FROM articles WHERE id = ?', [$a]));
    }

    public function testNoRulesNoGeneration(): void
    {
        $a = $this->art('2610', 'Bank');
        try {
            $this->posts->request($this->op(), 'cikk', (string) $a);
            self::fail('hiba várt');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Nincsenek Posztszabályok', $e->getMessage());
        }
        self::assertSame([], $this->posts->pendingJobs($this->op()), 'adathibánál nincs újrapróbálás');
        self::assertSame([], $this->claude->requests);
    }

    public function testFullTextSkipsEmptyParts(): void
    {
        self::assertSame("A\n\nB", PostWriter::fullText(['opening' => 'A', 'context' => ' ', 'closing' => 'B', 'source_link' => 'https://x']));
    }
}

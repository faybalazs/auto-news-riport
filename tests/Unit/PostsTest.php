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

    public function testIdeaToPostGoesStraightToClientWithImageAndAutoRevision(): void
    {
        $this->rules->save($this->op(), null, 'Hangnem', 'Tárgyilagos');
        $this->rules->save($this->op(), null, 'Kép', '1200×1200, sötétkék háttér');
        $emi = $this->member('Emi', 'emi@x.hu', 'tartalom');
        $attila = $this->member('Attila', 'attila@x.hu', 'ugyfeladmin');
        $idea = $this->ideas->save($emi, '', ['topic' => 'Új referencia', 'message' => 'Bankfiók átadás', 'type' => 'esettanulmány', 'link' => 'https://xperion.hu/ref']);
        self::assertMatchesRegularExpression('/^OT-\d{4}-01$/', $idea['code']);
        self::assertSame('Emi', $idea['submitter']);

        $code = $this->posts->request($emi, 'otlet', $idea['code']);
        self::assertMatchesRegularExpression('/^LI-\d{4}-01$/', $code);
        $text = $this->claude->requests[0]['body'];
        self::assertSame('claude-opus-5-5', $text['model']);
        self::assertStringContainsString('- Hangnem: Tárgyilagos', $text['system'][0]['text'], 'a friss szabályok a rendszerpromptban');
        self::assertStringContainsString('Bankfiók átadás', $text['messages'][0]['content']);
        self::assertStringContainsString('- Kép: 1200×1200', $this->claude->requests[1]['body']['system'][0]['text'], 'a képtervező a szabályok képsorait kapja');

        $p = $this->posts->get($emi, $code);
        self::assertSame('ugyfelnel', $p['status'], 'magától jóváhagyásra megy');
        self::assertSame('Kontextus a szövegben.', $p['context'], 'a link nem maradhat a poszt szövegében');
        self::assertSame("Mit mér egy bankfiók, amikor a várakozást méri?\n\nKontextus a szövegben.\n\n– egy\n– kettő\n\nSzemszög.\n\nZárás?\n\n#ügyfélélmény #retail", $p['full_text']);
        self::assertStringContainsString('<tspan', $p['image_svg']);
        foreach (['<script', 'onclick', 'evil.test', '<image'] as $bad) {
            self::assertStringNotContainsString($bad, $p['image_svg'], 'tisztított kép');
        }
        self::assertSame(['Egyedi kép elkészült', 'Generálva, jóváhagyásra elküldve'], array_column($p['events'], 'action'));
        self::assertSame(['attila@x.hu', 'b@pelda.hu'], end($this->mailer->sent)->to, 'Attila és az üzemeltető kap értesítést');
        self::assertStringContainsString('https://app.test/index.php?p=poszt&kod=' . $code, end($this->mailer->sent)->text);
        self::assertSame(['elkeszult', $code], [$this->ideas->get($emi, $idea['code'])['status'], $this->ideas->get($emi, $idea['code'])['post_code']]);

        try {
            $this->posts->transition($attila, $code, 'modositas', '');
            self::fail('módosításhoz megjegyzés kell');
        } catch (\InvalidArgumentException) {
        }
        $calls = count($this->claude->requests);
        $p = $this->posts->transition($attila, $code, 'modositas', 'Rövidebb nyitó sort kérünk.');
        self::assertSame(['ugyfelnel', ''], [$p['status'], $p['change_request']], 'automatikusan újraírva, és újra jóváhagyásra vár');
        self::assertStringContainsString('Rövidebb nyitó sort kérünk.', $this->claude->requests[$calls]['body']['messages'][0]['content'], 'az újraírás megkapja a kérést');
        self::assertStringContainsString('Rövidebb nyitó sort kérünk.', $this->claude->requests[$calls + 1]['body']['messages'][0]['content'], 'a kép is');
        self::assertSame($code, $this->posts->all($this->op())[0]['code'], 'ugyanaz a kód marad');
        self::assertContains('Módosítást kért', array_column($p['events'], 'action'));

        $p = $this->posts->newImage($attila, $code, 'Világosabb háttér');
        self::assertSame('Egyedi kép elkészült', $p['events'][0]['action']);

        $p = $this->posts->transition($attila, $code, 'jovahagyas');
        self::assertSame('jovahagyva', $p['status']);
        self::assertSame(['emi@x.hu', 'b@pelda.hu'], end($this->mailer->sent)->to, 'a közzétevő és az üzemeltető kap értesítést');
        try {
            $this->posts->update($attila, $code, ['opening' => 'X']);
            self::fail('jóváhagyott posztot csak az üzemeltető szerkeszt');
        } catch (\InvalidArgumentException) {
        }
        $p = $this->posts->transition($emi, $code, 'megjelent');
        self::assertSame('megjelent', $p['status']);
        self::assertSame('utemezve', $this->ideas->get($emi, $idea['code'])['status']);
    }

    public function testPostFromSelectedArticlesOnlyOnTrigger(): void
    {
        $this->rules->save($this->op(), null, 'Hangnem', 'Tárgyilagos');
        $a1 = $this->art('2610', 'Bank cuts wait');
        $a2 = $this->art('2610', 'Retail traffic');
        $a3 = $this->art('2610', 'Kiosk study');
        $m = $this->art('2610', 'Mehet only', 'mehet');
        $attila = $this->member('Attila', 'attila@x.hu', 'ugyfeladmin');
        $emi = $this->member('Emi', 'emi@x.hu', 'tartalom');

        try {
            $this->posts->request($emi, 'cikkek', (string) $a1);
            self::fail('cikkekből az indít, aki dönt');
        } catch (AccessDenied) {
        }
        try {
            $this->posts->request($attila, 'cikkek', "$a1,$m");
            self::fail('nem LinkedIn-cikk nem kerülhet bele');
        } catch (\InvalidArgumentException) {
        }
        self::assertSame([], $this->claude->requests, 'kijelölés közben semmi nem generálódik');

        $code = $this->posts->request($attila, 'cikkek', " $a2, $a1 ");
        self::assertSame('LI-2610-01', $code);
        $content = $this->claude->requests[0]['body']['messages'][0]['content'];
        self::assertStringContainsString('Bank cuts wait', $content);
        self::assertStringContainsString('Retail traffic', $content);
        self::assertStringContainsString('közös poszt a kijelölt cikkekből', $content);
        self::assertStringNotContainsString('Kiosk study', $content, 'a később kijelölendő cikk nem kerül bele');
        self::assertSame([$a1, $a2], array_column($this->posts->get($this->op(), $code)['articles'], 'id'));
        self::assertSame([['month' => '2610', 'label' => '2026. október', 'n' => 1]], $this->posts->openMonths($this->op()));

        $single = $this->posts->request($this->op(), 'cikk', (string) $a3);
        self::assertSame('LI-2610-02', $single);
        self::assertStringContainsString('Egy poszt ebből a cikkből', end($this->claude->requests)['body']['messages'][0]['content'] . $this->claude->requests[2]['body']['messages'][0]['content']);

        $this->posts->delete($this->op(), $code);
        self::assertNull($this->posts->get($this->op(), $code));
        self::assertSame('', (string) $this->db->value('SELECT post_code FROM articles WHERE id = ?', [$a1]), 'a cikkek újra kijelölhetők');
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM post_events WHERE post_code = ?', [$code]));
    }

    public function testInvalidSvgKeepsPostWithTemplateImage(): void
    {
        $this->rules->save($this->op(), null, 'Hangnem', 'Tárgyilagos');
        $a = $this->art('2610', 'Bank');
        $this->claude->svg = 'nem svg';
        $code = $this->posts->request($this->op(), 'cikk', (string) $a);
        $p = $this->posts->get($this->op(), $code);
        self::assertSame(['ugyfelnel', ''], [$p['status'], $p['image_svg']]);
        self::assertSame('A kép nem készült el', $p['events'][0]['action']);
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
        self::assertSame([['kind' => 'cikkek', 'ref' => (string) $a, 'attempts' => 1, 'error' => 'A válasz túl hosszú lett, és megszakadt (max_tokens).']], $this->posts->pendingJobs($this->op()));
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

    public function testReferenceImageSurvivesSanitizerAndGuidesDesigner(): void
    {
        $sample = (string) file_get_contents(dirname(__DIR__, 2) . '/app/resources/poszt-kep-minta.svg');
        $clean = \Xperion\Posts\SvgSanitizer::clean($sample);
        self::assertNotNull($clean);
        foreach (['<pattern', 'Mennyit ér egy', 'ILLUSZTRATÍV PÉLDA', '<polygon', 'xperion.hu', 'fill="url(#grid)"'] as $k) {
            self::assertStringContainsString($k, $clean, 'a minta a tisztítás után is teljes');
        }
        $withFont = \Xperion\Posts\SvgFonts::embed($clean);
        self::assertStringContainsString("@font-face{font-family:'Lato'", $withFont);
        self::assertSame(6, substr_count($withFont, '@font-face'), 'Lato 400/700/900, latin és latin-ext');

        $this->rules->save($this->op(), null, 'Hangnem', 'Tárgyilagos');
        $a = $this->art('2610', 'Bank');
        $code = $this->posts->request($this->op(), 'cikk', (string) $a);
        $req = $this->claude->requests[1]['body'];
        self::assertSame('high', $req['output_config']['effort']);
        self::assertStringContainsString('Mennyit ér egy', $req['system'][0]['text'], 'a minta SVG a tervező utasításában');
        self::assertStringContainsString('ELRENDEZÉSEK', $req['system'][0]['text']);
        self::assertStringContainsString('@font-face', $this->posts->get($this->op(), $code)['image_svg'], 'megjelenítéskor beágyazott betű');
        self::assertStringNotContainsString('@font-face', (string) $this->db->value('SELECT image_svg FROM posts WHERE code = ?', [$code]), 'az adatbázisban betű nélkül');
    }

    public function testFullTextSkipsEmptyParts(): void
    {
        self::assertSame("A\n\nB", PostWriter::fullText(['opening' => 'A', 'context' => ' ', 'closing' => 'B', 'source_link' => 'https://x']));
    }
}

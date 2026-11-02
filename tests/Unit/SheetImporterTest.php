<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\TokenService;
use Xperion\Auth\UserAdmin;
use Xperion\Google\SheetsClient;
use Xperion\Import\ImportService;
use Xperion\Log\LocalLog;
use Xperion\Tests\DbTestCase;
use Xperion\Tests\FakeSpreadsheet;

final class SheetImporterTest extends DbTestCase
{
    private const SHEET_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private FakeSpreadsheet $fake;
    private ImportService $import;
    private int $op;
    private int $a;
    private int $b;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeSpreadsheet([
            'Figyelendő oldalak' => [['Forrás', 'Típus', 'Figyelt oldal', 'Fő témák', 'Aktív', 'Megjegyzés'], ['Vemco', 'blog', 'https://vemco.com/blog', 'x', 'igen', ''], ['Acrelec', 'hír', 'https://acrelec.com/news', '', 'igen', 'próbaidőszak']],
            'Kulcsszavak' => [['Kulcsszó', 'Témakör', 'Típus', 'Nyelv'], ['queue', 'sor', 'Pozitív', 'EN'], ['IPO', '', 'Kizáró', 'EN']],
            '2608' => [['No', 'Page', 'Date', 'Title', 'Desc', 'Link', 'Feltöltve'], ['1', 'Vemco', '2026.08.02', 'A', 'a', 'https://vemco.com/a?utm_source=li', 'Igen']],
            '2609' => [['Vezetői összefoglaló'], [''], [''], ['No', 'Page', 'Date', 'Title', 'Desc', 'Link', 'Javaslat', 'Indoklás', 'Ügyfél döntése'],
                ['1', 'Vemco', '2026.09.01', 'A újra', 'a', 'https://www.vemco.com/a/', 'Javasolt', '', ''], ['2', 'Qmatic', '2026.09.03', 'B', 'b', 'https://qmatic.com/b', 'Erősen javasolt', 'jó', 'LinkedIn poszt']],
            'LinkedIn posztok' => [['ID', 'Hónap', 'Státusz', 'Témakör', 'Forráscikkek', 'Nyitó sor'], ['LI-2609-01', '2609', 'Megjelent', 'Bank', '1', 'Nyitó']],
            'LinkedIn szabályok' => [['Megnevezés', 'Érték'], ['Cég', 'Xperion Ltd.'], ['Hossz', '800–1300 karakter']],
            'Beállítások' => [['Kulcs', 'Érték'], ['ugyfel_email', 'a@pelda.hu'], ['ugyfel_cc', '']],
            'Egyéb lap' => [['x']],
        ]);
        $audit = new AuditLog($this->db);
        $users = new UserAdmin($this->db, new TokenService($this->db), $audit);
        $clients = new ClientAdmin($this->db, $audit);
        $this->op = $users->createFirstOperator('Balázs', 'b@pelda.hu', 'eleg-hosszu-jelszo');
        $op = Access::forUser($this->db, $this->op, null);
        $this->a = $clients->create($op, 'Xperion', '', 'https://docs.google.com/spreadsheets/d/' . self::SHEET_A . '/edit');
        $this->b = $clients->create(Access::forUser($this->db, $this->op, null), 'Másik', '');
        $this->dir = sys_get_temp_dir() . '/xp-imp-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $fake = $this->fake;
        $this->import = new ImportService($this->db, static fn (string $id): SheetsClient => new SheetsClient($id, $fake, static fn () => 't'), new LocalLog($this->dir . '/n.jsonl'), $audit, $this->dir . '/l.lock');
    }

    private function rows(string $table, int $client): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM `$table` WHERE client_id = ?", [$client]);
    }

    public function testDryRunWritesNothing(): void
    {
        $r = $this->import->run(Access::forUser($this->db, $this->op, $this->a), true);
        self::assertTrue($r->ok, $r->summary());
        foreach (['sources', 'keywords', 'articles', 'posts', 'post_rules', 'client_settings'] as $t) {
            self::assertSame(0, $this->rows($t, $this->a), $t);
        }
        $text = implode("\n", array_column($r->lines, 'text'));
        self::assertStringContainsString('Havi lapok: 2 lap (2608 – 2609), 3 cikksor, ebből 2 egyedi cikk', $text);
    }

    public function testLiveImportIsIdempotentAndScopedToClient(): void
    {
        $a = Access::forUser($this->db, $this->op, $this->a);
        self::assertTrue($this->import->run($a, false)->ok);
        self::assertSame(2, $this->rows('sources', $this->a));
        self::assertSame(2, $this->rows('keywords', $this->a));
        self::assertSame(2, $this->rows('articles', $this->a), 'a 2608-as és 2609-es „A” ugyanaz a link');
        self::assertSame(1, $this->rows('posts', $this->a));
        self::assertSame(2, $this->rows('post_rules', $this->a));
        self::assertSame(1, $this->rows('client_settings', $this->a), 'üres beállítás kimarad');
        $first = $this->db->one("SELECT month, uploaded, published_on FROM articles WHERE client_id = ? AND title = 'A'", [$this->a]);
        self::assertSame(['month' => '2608', 'uploaded' => 1, 'published_on' => '2026-08-02'], $first);
        self::assertSame('linkedin', $this->db->value("SELECT decision FROM articles WHERE client_id = ? AND title = 'B'", [$this->a]));
        self::assertSame(1, (int) $this->db->value('SELECT is_new FROM sources WHERE client_id = ? AND name = ?', [$this->a, 'Acrelec']));

        // újrafuttatás: nincs duplikáció; a szabályokat nem írja felül
        $this->db->run("UPDATE post_rules SET value = 'kézzel módosítva' WHERE client_id = ? AND name = 'Hossz'", [$this->a]);
        $this->fake->sheets['Kulcsszavak'][] = ['retail', '', 'Pozitív', 'EN'];
        $r2 = $this->import->run($a, false);
        self::assertTrue($r2->ok);
        self::assertSame(2, $this->rows('articles', $this->a));
        self::assertSame(3, $this->rows('keywords', $this->a));
        self::assertSame('kézzel módosítva', $this->db->value("SELECT value FROM post_rules WHERE client_id = ? AND name = 'Hossz'", [$this->a]));
        self::assertStringContainsString('nem írtam felül', implode("\n", array_column($r2->lines, 'text')));

        foreach (['sources', 'keywords', 'articles', 'posts', 'post_rules', 'client_settings'] as $t) {
            self::assertSame(0, $this->rows($t, $this->b), "a másik ügyfél ($t) érintetlen");
        }
        self::assertNotEmpty((new AuditLog($this->db))->recent($this->a));
    }

    public function testClientWithoutSheetGetsClearError(): void
    {
        $r = $this->import->run(Access::forUser($this->db, $this->op, $this->b), true);
        self::assertFalse($r->ok);
        self::assertStringContainsString('nincs megadva Google Sheet', $r->summary());
        self::assertSame(0, $this->fake->calls);
    }

    public function testOnlyOperatorMayImport(): void
    {
        $audit = new AuditLog($this->db);
        $users = new UserAdmin($this->db, new TokenService($this->db), $audit);
        $inv = $users->invite(Access::forUser($this->db, $this->op, $this->a), 'Attila', 'attila@pelda.hu', $this->a, 'ugyfeladmin');
        $users->setPasswordWithToken((string) $inv['token'], TokenService::INVITE, 'attila-jelszo-1');
        $this->expectException(\Xperion\Auth\AccessDenied::class);
        $this->import->run(Access::forUser($this->db, $inv['user_id'], $this->a), true);
    }

    public function testInvalidSheetLinkIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ClientAdmin($this->db, new AuditLog($this->db)))->update(Access::forUser($this->db, $this->op, null), $this->b, 'Másik', '', 'aktiv', 'nem egy link');
    }
}

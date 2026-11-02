<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xperion\Config;

final class ConfigTest extends TestCase
{
    public function testDottedKeysAndDefaults(): void
    {
        $c = new Config(['smtp' => ['host' => ' mail.example.hu ', 'port' => 465]]);
        self::assertSame('mail.example.hu', $c->string('smtp.host'));
        self::assertSame(465, $c->get('smtp.port'));
        self::assertNull($c->get('smtp.nincs'));
        self::assertFalse($c->has('claude_api_key'));
    }

    public function testLoadPicksFirstExistingFile(): void
    {
        $dir = sys_get_temp_dir() . '/xp-cfg-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/b.php', "<?php return ['admin_key' => 'b'];");
        $c = Config::load([$dir . '/a.php', $dir . '/b.php']);
        self::assertTrue($c->isLoaded());
        self::assertSame('b', $c->string('admin_key'));
        self::assertFalse(Config::load([$dir . '/nincs.php'])->isLoaded());
    }

    public function testServiceAccountInlineWinsOverFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sa');
        file_put_contents($file, '{"from":"file"}');
        self::assertSame('{"from":"file"}', (new Config(['google_service_account_file' => $file]))->serviceAccountJson());
        self::assertSame('{"x":1}', (new Config(['google_service_account_file' => $file, 'google_service_account_json' => '{"x":1}']))->serviceAccountJson());
    }

    public function testChecklistNeverExposesValues(): void
    {
        $c = new Config(['admin_key' => 'titkos-kulcs-1234567890', 'cron_key' => 'rovid'], '/x/config.php');
        $flat = json_encode($c->checklist());
        self::assertStringNotContainsString('titkos-kulcs', (string) $flat);
        $byLabel = array_column($c->checklist(), null, 'label');
        self::assertTrue($byLabel['Admin kulcs']['ok']);
        self::assertFalse($byLabel['Cron-kulcs']['ok']);
        self::assertSame('túl rövid', $byLabel['Cron-kulcs']['note']);
    }
}

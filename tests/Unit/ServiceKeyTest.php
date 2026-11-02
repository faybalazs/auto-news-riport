<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use Xperion\App;
use Xperion\Auth\Access;
use Xperion\Auth\AccessDenied;
use Xperion\Auth\AuditLog;
use Xperion\Auth\ClientAdmin;
use Xperion\Auth\TokenService;
use Xperion\Auth\UserAdmin;
use Xperion\Config;
use Xperion\Tests\DbTestCase;

final class ServiceKeyTest extends DbTestCase
{
    private function keyJson(): string
    {
        $k = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($k, $pem);
        return (string) json_encode(['type' => 'service_account', 'client_email' => 'xp@proj.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token']);
    }

    public function testConfigFileRelativeToConfigAndUploadedFallback(): void
    {
        $dir = sys_get_temp_dir() . '/xp-key-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/google-kulcs.json', '{"a":1}');
        file_put_contents($dir . '/config.php', "<?php return ['google_service_account_file' => 'google-kulcs.json'];");
        $c = Config::load([$dir . '/config.php']);
        self::assertSame('{"a":1}', $c->serviceAccountJson(), 'relatív útvonal a config.php mappájához képest');
        self::assertSame('config.php (fájl)', $c->serviceAccountSource());

        file_put_contents($dir . '/config.php', "<?php return ['google_service_account_file' => 'nincs-ilyen.json'];");
        $c = Config::load([$dir . '/config.php']);
        self::assertSame('a config.php-ban megadott fájl nem található', $c->serviceAccountSource());
        file_put_contents($dir . '/feltoltott.json', '{"b":2}');
        $c->setUploadedKeyFile($dir . '/feltoltott.json');
        self::assertSame('{"b":2}', $c->serviceAccountJson(), 'a feltöltött kulcs elsőbbséget élvez');
        self::assertSame('feltöltve a felületen', $c->serviceAccountSource());
    }

    public function testUploadValidatesAndRequiresOperator(): void
    {
        $audit = new AuditLog($this->db);
        $users = new UserAdmin($this->db, new TokenService($this->db), $audit);
        $op = $users->createFirstOperator('B', 'b@pelda.hu', 'eleg-hosszu-jelszo');
        $x = (new ClientAdmin($this->db, $audit))->create(Access::forUser($this->db, $op, null), 'Xperion', '');
        $dir = sys_get_temp_dir() . '/xp-app-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $cfg = new Config(['db' => ['host' => getenv('XP_TEST_DB_HOST') ?: 'localhost', 'name' => getenv('XP_TEST_DB_NAME') ?: 'xp_test', 'user' => getenv('XP_TEST_DB_USER') ?: 'xp', 'pass' => getenv('XP_TEST_DB_PASS') ?: 'xp']], '/x/config.php');
        $cfg->setUploadedKeyFile($dir . '/google-service-account.json');
        $app = new App($cfg, $dir);

        try {
            $app->saveServiceAccountKey(Access::forUser($this->db, $op, $x), '{"type":"authorized_user"}');
            self::fail('nem szolgáltatásfiók');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('nem Google-szolgáltatásfiók', $e->getMessage());
        }
        self::assertSame('xp@proj.iam.gserviceaccount.com', $app->saveServiceAccountKey(Access::forUser($this->db, $op, $x), $this->keyJson()));
        self::assertSame('0600', substr(sprintf('%o', fileperms($dir . '/google-service-account.json')), -4));
        self::assertSame('xp@proj.iam.gserviceaccount.com', $app->serviceAccountEmail());

        $inv = $users->invite(Access::forUser($this->db, $op, $x), 'Attila', 'a@pelda.hu', $x, 'ugyfeladmin');
        $users->setPasswordWithToken((string) $inv['token'], TokenService::INVITE, 'attila-jelszo-1');
        $this->expectException(AccessDenied::class);
        $app->saveServiceAccountKey(Access::forUser($this->db, $inv['user_id'], null), $this->keyJson());
    }
}

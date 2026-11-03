<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** A WordPress-oldali belépési kiegészítő (wordpress/mu-plugins/xperion-auth-fejlec.php) külön PHP-folyamatban, WordPress-csonkkal. */
final class WpAuthHelperTest extends TestCase
{
    private function runPlugin(array $server): array
    {
        $plugin = dirname(__DIR__, 2) . '/wordpress/mu-plugins/xperion-auth-fejlec.php';
        $code = 'define("ABSPATH", "/"); $GLOBALS["f"] = []; function add_filter($h, $cb) { $GLOBALS["f"][$h] = $cb; } function add_action($h, $cb) { $GLOBALS["f"][$h] = $cb; } function get_current_user_id() { return 0; }'
            . ' $_SERVER = array_merge($_SERVER, ' . var_export($server, true) . '); require ' . var_export($plugin, true) . ';'
            . ' $r = new class { public $d = ["name" => "x"]; function get_data() { return $this->d; } function set_data($d) { $this->d = $d; } };'
            . ' $api = $GLOBALS["f"]["application_password_is_api_request"](false);'
            . ' $GLOBALS["f"]["application_password_failed_authentication"](new class { function get_error_code() { return "incorrect_password"; } });'
            . ' $r = $GLOBALS["f"]["rest_index"]($r);'
            . ' echo json_encode(["auth" => $_SERVER["HTTP_AUTHORIZATION"] ?? null, "user" => $_SERVER["PHP_AUTH_USER"] ?? null, "pw" => $_SERVER["PHP_AUTH_PW"] ?? null, "marker" => $r->d["xperion_auth_header"] ?? null, "seen" => $r->d["xperion_auth_seen"] ?? null, "api" => $api]);';
        $out = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code));
        return json_decode((string) $out, true) ?? ['raw' => $out];
    }

    public function testRestoresStrippedAuthorization(): void
    {
        $basic = 'Basic ' . base64_encode('cikkfigyelo:abcd efgh:ijkl');
        $r = $this->runPlugin(['HTTP_X_XPERION_AUTH' => $basic, 'REQUEST_URI' => '/wp-json/wp/v2/users/me']);
        self::assertSame($basic, $r['auth']);
        self::assertSame('cikkfigyelo', $r['user']);
        self::assertSame('abcd efgh:ijkl', $r['pw']);
        self::assertSame('1.1.0', $r['marker']);
        // REST-kérésnek számít akkor is, ha a REST_REQUEST még nincs meg (korai felhasználó-lekérdezés)
        self::assertTrue($r['api']);
        self::assertSame(['authorization' => false, 'alt' => true, 'failed' => 'incorrect_password', 'logged_in' => false], $r['seen']);
        // nem REST-kérésnél nem avatkozik be
        self::assertFalse($this->runPlugin(['HTTP_X_XPERION_AUTH' => $basic, 'REQUEST_URI' => '/wp-login.php'])['api']);
    }

    public function testKeepsOriginalHeaderAndIgnoresGarbage(): void
    {
        $r = $this->runPlugin(['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('a:b'), 'HTTP_X_XPERION_AUTH' => 'Basic ' . base64_encode('c:d')]);
        self::assertSame('Basic ' . base64_encode('a:b'), $r['auth']);
        self::assertNull($r['user']);
        $r = $this->runPlugin(['HTTP_X_XPERION_AUTH' => 'Bearer xyz']);
        self::assertNull($r['auth']);
        $r = $this->runPlugin(['HTTP_X_XPERION_AUTH' => 'Basic !!!nem-base64']);
        self::assertNull($r['user']);
    }
}

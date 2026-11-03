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
        $code = 'define("ABSPATH", "/"); $GLOBALS["f"] = []; function add_filter($h, $cb) { $GLOBALS["f"][$h] = $cb; }'
            . ' $_SERVER = array_merge($_SERVER, ' . var_export($server, true) . '); require ' . var_export($plugin, true) . ';'
            . ' $r = new class { public $d = ["name" => "x"]; function get_data() { return $this->d; } function set_data($d) { $this->d = $d; } };'
            . ' $r = $GLOBALS["f"]["rest_index"]($r);'
            . ' echo json_encode(["auth" => $_SERVER["HTTP_AUTHORIZATION"] ?? null, "user" => $_SERVER["PHP_AUTH_USER"] ?? null, "pw" => $_SERVER["PHP_AUTH_PW"] ?? null, "marker" => $r->d["xperion_auth_header"] ?? null]);';
        $out = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code));
        return json_decode((string) $out, true) ?? ['raw' => $out];
    }

    public function testRestoresStrippedAuthorization(): void
    {
        $basic = 'Basic ' . base64_encode('cikkfigyelo:abcd efgh:ijkl');
        $r = $this->runPlugin(['HTTP_X_XPERION_AUTH' => $basic]);
        self::assertSame($basic, $r['auth']);
        self::assertSame('cikkfigyelo', $r['user']);
        self::assertSame('abcd efgh:ijkl', $r['pw']);
        self::assertSame('1.0.0', $r['marker']);
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

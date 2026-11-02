<?php
declare(strict_types=1);

namespace Xperion\Tests;

use PHPUnit\Framework\TestCase;
use Xperion\Db\Database;
use Xperion\Db\Migrator;

/**
 * Valódi MySQL/MariaDB-n futó tesztek alaposztálya. Minden teszt üres,
 * frissen migrált adatbázist kap. Ha nincs elérhető adatbázis, kihagyja.
 * Beállítás: XP_TEST_DB_HOST, XP_TEST_DB_USER, XP_TEST_DB_PASS, XP_TEST_DB_NAME.
 */
abstract class DbTestCase extends TestCase
{
    protected Database $db;

    protected function setUp(): void
    {
        $name = getenv('XP_TEST_DB_NAME') ?: 'xp_test';
        $cfg = ['host' => getenv('XP_TEST_DB_HOST') ?: 'localhost', 'user' => getenv('XP_TEST_DB_USER') ?: 'xp', 'pass' => getenv('XP_TEST_DB_PASS') ?: 'xp'];
        try {
            $root = Database::connect($cfg + ['name' => '']);
        } catch (\PDOException $e) {
            self::markTestSkipped('Nincs elérhető teszt-adatbázis: ' . $e->getMessage());
        }
        $root->pdo()->exec("DROP DATABASE IF EXISTS `$name`");
        $root->pdo()->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_hungarian_ci");
        $this->db = Database::connect($cfg + ['name' => $name]);
        (new Migrator($this->db, dirname(__DIR__) . '/app/migrations'))->migrate();
    }
}

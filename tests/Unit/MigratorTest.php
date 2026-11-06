<?php
declare(strict_types=1);

namespace Xperion\Tests\Unit;

use Xperion\Db\Migrator;
use Xperion\Tests\DbTestCase;

final class MigratorTest extends DbTestCase
{
    public function testMigrationsAreAppliedOnce(): void
    {
        $m = new Migrator($this->db, dirname(__DIR__, 2) . '/app/migrations');
        self::assertSame([], $m->pending());
        self::assertSame([], $m->migrate(), 'a második futás nem csinál semmit');
        $tables = array_column($this->db->all('SHOW TABLES'), 'Tables_in_' . $this->db->value('SELECT DATABASE()'));
        foreach (['clients', 'users', 'memberships', 'user_tokens', 'login_attempts', 'audit_log', 'schema_migrations'] as $t) {
            self::assertContains($t, $tables);
        }
    }

    public function testStatementSplitterIgnoresComments(): void
    {
        self::assertSame(['CREATE TABLE a (x INT)', 'CREATE TABLE b (y INT)'], Migrator::statements("-- megjegyzés\nCREATE TABLE a (x INT);\n\nCREATE TABLE b (y INT);"));
    }
}

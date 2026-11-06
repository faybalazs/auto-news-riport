<?php
declare(strict_types=1);

namespace Xperion\Db;

/**
 * Sémafrissítés terminál nélkül: az app/migrations/NNN_nev.sql fájlokat
 * sorrendben, egyszer futtatja le. A schema_migrations tábla tartja nyilván.
 * Párhuzamos futás ellen MySQL GET_LOCK védi.
 */
final class Migrator
{
    public function __construct(private Database $db, private string $dir)
    {
    }

    /** @return list<string> a még le nem futott migrációk neve */
    public function pending(): array
    {
        $this->ensureTable();
        $done = array_column($this->db->all('SELECT version FROM schema_migrations'), 'version');
        return array_values(array_diff($this->available(), $done));
    }

    /** @return list<string> a most lefuttatott migrációk */
    public function migrate(): array
    {
        $this->ensureTable();
        if ((int) $this->db->value("SELECT GET_LOCK('xperion_migrate', 30)") !== 1) {
            throw new \RuntimeException('Az adatbázis frissítése éppen fut egy másik kérésben.');
        }
        try {
            $ran = [];
            foreach ($this->pending() as $version) {
                foreach (self::statements((string) file_get_contents($this->dir . '/' . $version . '.sql')) as $sql) {
                    $this->db->pdo()->exec($sql);
                }
                $this->db->insert('schema_migrations', ['version' => $version, 'applied_at' => Database::now()]);
                $ran[] = $version;
            }
            return $ran;
        } finally {
            $this->db->value("SELECT RELEASE_LOCK('xperion_migrate')");
        }
    }

    /** @return list<string> */
    public function available(): array
    {
        $files = glob($this->dir . '/*.sql') ?: [];
        sort($files);
        return array_map(static fn ($f) => basename($f, '.sql'), $files);
    }

    /** @return list<string> pontosvesszővel tagolt utasítások (a megjegyzések nélkül) */
    public static function statements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
        return array_values(array_filter(array_map('trim', explode(";\n", $sql . "\n")), static fn ($s) => $s !== '' && $s !== ';'));
    }

    private function ensureTable(): void
    {
        $this->db->pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci');
    }
}

<?php
declare(strict_types=1);

namespace Xperion\Db;

use PDO;

/**
 * Vékony PDO-réteg MySQL/MariaDB-hez. Minden lekérdezés előkészített,
 * paraméterezett; az időpontokat UTC-ben tároljuk.
 */
final class Database
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array{host?:string, port?:int|string, name?:string, user?:string, pass?:string} $c */
    public static function connect(array $c): self
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'] ?? 'localhost', (int) ($c['port'] ?? 3306), $c['name'] ?? '');
        $pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00', NAMES utf8mb4 COLLATE utf8mb4_hungarian_ci");
        return new self($pdo);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @param array<string|int,mixed> $params */
    public function run(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** @return array<string,mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $table, implode(', ', array_map(static fn ($c) => "`$c`", $cols)), implode(', ', array_map(static fn ($c) => ":$c", $cols)));
        $this->run($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where oszlop => érték (ÉS kapcsolat)
     */
    public function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(static fn ($c) => "`$c` = :s_$c", array_keys($data)));
        $cond = implode(' AND ', array_map(static fn ($c) => "`$c` = :w_$c", array_keys($where)));
        $params = [];
        foreach ($data as $k => $v) {
            $params["s_$k"] = $v;
        }
        foreach ($where as $k => $v) {
            $params["w_$k"] = $v;
        }
        return $this->run("UPDATE `$table` SET $set WHERE $cond", $params)->rowCount();
    }

    /** @template T @param callable():T $fn @return T */
    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

<?php
declare(strict_types=1);

namespace Xperion\Posts;

use Xperion\Auth\Access;
use Xperion\Auth\AuditLog;
use Xperion\Db\Database;

/** Posztszabályok: a posztgenerálás egyetlen szabályforrása (product.md 4.1), ügyfelenként. */
final class RuleService
{
    public function __construct(private Database $db, private AuditLog $audit)
    {
    }

    /** @return list<array{id:int, sort:int, name:string, value:string, updated_at:string}> */
    public function all(Access $a): array
    {
        $cid = $a->clientId();
        return $cid === null ? [] : $this->forClient($cid);
    }

    /** @return list<array{id:int, sort:int, name:string, value:string, updated_at:string}> */
    public function forClient(int $cid): array
    {
        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'sort' => (int) $r['sort'], 'name' => (string) $r['name'], 'value' => (string) $r['value'], 'updated_at' => (string) $r['updated_at']],
            $this->db->all('SELECT id, sort, name, value, updated_at FROM post_rules WHERE client_id = ? ORDER BY sort, id', [$cid]));
    }

    /** Új (id = null) vagy meglévő szabály mentése. @return list<array<string,mixed>> */
    public function save(Access $a, ?int $id, string $name, string $value): array
    {
        $a->require('rules');
        $cid = $this->client($a);
        $name = mb_substr(trim($name), 0, 190);
        $value = trim($value);
        if ($name === '' || $value === '') {
            throw new \InvalidArgumentException('A megnevezés és az érték nem lehet üres.');
        }
        $now = Database::now();
        if ($id === null) {
            $sort = (int) $this->db->value('SELECT COALESCE(MAX(sort), 0) + 1 FROM post_rules WHERE client_id = ?', [$cid]);
            $this->db->insert('post_rules', ['client_id' => $cid, 'sort' => $sort, 'name' => $name, 'value' => $value, 'updated_at' => $now]);
            $this->audit->add($cid, $a->userId(), 'Posztszabály felvéve', $name);
        } else {
            $old = $this->db->one('SELECT name FROM post_rules WHERE id = ? AND client_id = ?', [$id, $cid]) ?? throw new \InvalidArgumentException('Nincs ilyen szabály.');
            $this->db->run('UPDATE post_rules SET name = ?, value = ?, updated_at = ? WHERE id = ? AND client_id = ?', [$name, $value, $now, $id, $cid]);
            $this->audit->add($cid, $a->userId(), 'Posztszabály módosítva', $old['name'] !== $name ? $old['name'] . ' → ' . $name : $name);
        }
        return $this->forClient($cid);
    }

    /** @return list<array<string,mixed>> */
    public function delete(Access $a, int $id): array
    {
        $a->require('rules');
        $cid = $this->client($a);
        $old = $this->db->one('SELECT name FROM post_rules WHERE id = ? AND client_id = ?', [$id, $cid]) ?? throw new \InvalidArgumentException('Nincs ilyen szabály.');
        $this->db->run('DELETE FROM post_rules WHERE id = ? AND client_id = ?', [$id, $cid]);
        $this->audit->add($cid, $a->userId(), 'Posztszabály törölve', (string) $old['name']);
        return $this->forClient($cid);
    }

    /** Új sorrend (a lista összes azonosítója, a kívánt sorrendben). @param list<int> $ids @return list<array<string,mixed>> */
    public function reorder(Access $a, array $ids): array
    {
        $a->require('rules');
        $cid = $this->client($a);
        $mine = array_map('intval', array_column($this->db->all('SELECT id FROM post_rules WHERE client_id = ?', [$cid]), 'id'));
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($mine);
        $check = $ids;
        sort($check);
        if ($check !== $mine) {
            throw new \InvalidArgumentException('A sorrend nem egyezik a szabályokkal. Töltse újra az oldalt.');
        }
        $this->db->transaction(function () use ($ids, $cid): void {
            foreach ($ids as $i => $id) {
                $this->db->run('UPDATE post_rules SET sort = ? WHERE id = ? AND client_id = ?', [$i + 1, $id, $cid]);
            }
        });
        return $this->forClient($cid);
    }

    /** A szabályok egy szövegként a Claude-nak. */
    public function asText(int $cid): string
    {
        return implode("\n", array_map(static fn ($r) => '- ' . $r['name'] . ': ' . $r['value'], $this->forClient($cid)));
    }

    private function client(Access $a): int
    {
        return $a->clientId() ?? throw new \InvalidArgumentException('Nincs kiválasztott ügyfél.');
    }
}

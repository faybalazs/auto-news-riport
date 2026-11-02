<?php
declare(strict_types=1);

namespace Xperion\Auth;

use Xperion\Db\Database;

/** Ügyfelek kezelése (csak üzemeltető). */
final class ClientAdmin
{
    public const STATUS_LABELS = ['aktiv' => 'Aktív', 'beallitas' => 'Beállítás alatt', 'szunetel' => 'Szüneteltetve'];

    public function __construct(private Database $db, private AuditLog $audit)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(Access $a): array
    {
        $a->require('ops');
        return array_map(static function ($c) {
            $c['id'] = (int) $c['id'];
            $c['users'] = (int) $c['users'];
            return $c;
        }, $this->db->all('SELECT c.id, c.name, c.website, c.status, c.created_at, COUNT(m.user_id) AS users FROM clients c LEFT JOIN memberships m ON m.client_id = c.id GROUP BY c.id ORDER BY c.name'));
    }

    public function create(Access $a, string $name, string $website): int
    {
        $a->require('ops');
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Adja meg az ügyfél nevét.');
        }
        if ($this->db->value('SELECT 1 FROM clients WHERE name = ?', [$name])) {
            throw new \InvalidArgumentException('Ilyen nevű ügyfél már van.');
        }
        $id = $this->db->insert('clients', ['name' => $name, 'website' => self::cleanSite($website), 'status' => 'beallitas', 'created_at' => Database::now()]);
        $this->audit->add($id, $a->userId(), 'Ügyfél létrehozva', $name);
        return $id;
    }

    public function update(Access $a, int $id, string $name, string $website, string $status): void
    {
        $a->require('ops');
        if (!isset(self::STATUS_LABELS[$status])) {
            throw new \InvalidArgumentException('Ismeretlen állapot.');
        }
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Adja meg az ügyfél nevét.');
        }
        if ($this->db->value('SELECT 1 FROM clients WHERE name = ? AND id <> ?', [$name, $id])) {
            throw new \InvalidArgumentException('Ilyen nevű ügyfél már van.');
        }
        if ($this->db->update('clients', ['name' => $name, 'website' => self::cleanSite($website), 'status' => $status], ['id' => $id]) === 0
            && !$this->db->value('SELECT 1 FROM clients WHERE id = ?', [$id])) {
            throw new \InvalidArgumentException('Nincs ilyen ügyfél.');
        }
        $this->audit->add($id, $a->userId(), 'Ügyfél módosítva', $name . ' · ' . self::STATUS_LABELS[$status]);
    }

    private static function cleanSite(string $site): string
    {
        $site = trim($site);
        $site = preg_replace('#^https?://#i', '', $site) ?? '';
        return rtrim($site, '/');
    }
}

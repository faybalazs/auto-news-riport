<?php
declare(strict_types=1);

namespace Xperion\Auth;

use Xperion\Db\Database;
use Xperion\Import\Url;

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
            foreach (['sources', 'keywords', 'articles', 'posts', 'rules'] as $k) {
                $c[$k] = (int) $c[$k];
            }
            return $c;
        }, $this->db->all('SELECT c.id, c.name, c.website, c.spreadsheet_id, c.status, c.created_at,
            (SELECT COUNT(*) FROM memberships m WHERE m.client_id = c.id) AS users,
            (SELECT COUNT(*) FROM sources s WHERE s.client_id = c.id) AS sources,
            (SELECT COUNT(*) FROM keywords k WHERE k.client_id = c.id) AS keywords,
            (SELECT COUNT(*) FROM articles a WHERE a.client_id = c.id) AS articles,
            (SELECT COUNT(*) FROM posts p WHERE p.client_id = c.id) AS posts,
            (SELECT COUNT(*) FROM post_rules r WHERE r.client_id = c.id) AS rules
            FROM clients c ORDER BY c.name'));
    }

    public function create(Access $a, string $name, string $website, string $sheet = ''): int
    {
        $a->require('ops');
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Adja meg az ügyfél nevét.');
        }
        if ($this->db->value('SELECT 1 FROM clients WHERE name = ?', [$name])) {
            throw new \InvalidArgumentException('Ilyen nevű ügyfél már van.');
        }
        $id = $this->db->insert('clients', ['name' => $name, 'website' => self::cleanSite($website), 'spreadsheet_id' => self::sheetId($sheet), 'status' => 'beallitas', 'created_at' => Database::now()]);
        $this->audit->add($id, $a->userId(), 'Ügyfél létrehozva', $name);
        return $id;
    }

    public function update(Access $a, int $id, string $name, string $website, string $status, string $sheet = ''): void
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
        if ($this->db->update('clients', ['name' => $name, 'website' => self::cleanSite($website), 'spreadsheet_id' => self::sheetId($sheet), 'status' => $status], ['id' => $id]) === 0
            && !$this->db->value('SELECT 1 FROM clients WHERE id = ?', [$id])) {
            throw new \InvalidArgumentException('Nincs ilyen ügyfél.');
        }
        $this->audit->add($id, $a->userId(), 'Ügyfél módosítva', $name . ' · ' . self::STATUS_LABELS[$status]);
    }

    /** Üres megadás: nincs Sheet; érvénytelen: hiba. */
    private static function sheetId(string $input): string
    {
        if (trim($input) === '') {
            return '';
        }
        $id = Url::spreadsheetId($input);
        if ($id === '') {
            throw new \InvalidArgumentException('A Google Sheet linkje vagy azonosítója nem értelmezhető. Másolja be a tábla teljes linkjét a böngésző címsorából.');
        }
        return $id;
    }

    private static function cleanSite(string $site): string
    {
        $site = trim($site);
        $site = preg_replace('#^https?://#i', '', $site) ?? '';
        return rtrim($site, '/');
    }
}

<?php
declare(strict_types=1);

namespace Xperion\Auth;

use Xperion\Db\Database;

/** Felhasználói műveletek naplója (ki, mit, mikor), ügyfelenként. */
final class AuditLog
{
    public function __construct(private Database $db)
    {
    }

    public function add(?int $clientId, ?int $userId, string $action, string $details = ''): void
    {
        $this->db->insert('audit_log', ['client_id' => $clientId, 'user_id' => $userId, 'action' => mb_substr($action, 0, 100), 'details' => mb_substr($details, 0, 1000), 'created_at' => Database::now()]);
    }

    /** @return list<array<string,mixed>> */
    /** A művelet kategóriája a Napló szűrőjéhez. */
    public static function category(string $action): string
    {
        return match (true) {
            str_starts_with($action, 'Döntés') => 'Döntések',
            str_starts_with($action, 'Poszt') => 'Posztok',
            str_starts_with($action, 'Havi e-mail') => 'Havi e-mail',
            str_starts_with($action, 'Feltöltés') => 'Feltöltés',
            str_starts_with($action, 'Forrás'), str_starts_with($action, 'Kulcsszó') => 'Források',
            str_starts_with($action, 'Havi futás'), str_starts_with($action, 'Adatátvétel') => 'Futások',
            str_starts_with($action, 'Belépés') => 'Belépés',
            str_starts_with($action, 'Felhasználó'), str_contains($action, 'jelszó'), str_contains($action, 'Meghív') => 'Felhasználók',
            default => 'Beállítások',
        };
    }

    /** A 2 évnél régebbi bejegyzések törlése (megőrzés). */
    public function prune(int $days = 730): int
    {
        return $this->db->run('DELETE FROM audit_log WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - $days * 86400)])->rowCount();
    }

    public function recent(?int $clientId, int $limit = 300): array
    {
        $sql = 'SELECT a.created_at, a.action, a.details, a.client_id, COALESCE(u.name, "Rendszer") AS who, c.name AS client
                FROM audit_log a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN clients c ON c.id = a.client_id';
        $params = [];
        if ($clientId !== null) {
            $sql .= ' WHERE a.client_id = ?'; // más ügyfélre vagy globális műveletre vonatkozó sor nem szivároghat át
            $params[] = $clientId;
        }
        return array_map(static fn ($r) => $r + ['category' => self::category((string) $r['action']), 'time_hu' => \Xperion\Web\View::dateHu((string) $r['created_at'])],
            $this->db->all($sql . ' ORDER BY a.id DESC LIMIT ' . max(1, min(2000, $limit)), $params));
    }
}

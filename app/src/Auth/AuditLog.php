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
    public function recent(?int $clientId, int $limit = 300): array
    {
        $sql = 'SELECT a.created_at, a.action, a.details, a.client_id, COALESCE(u.name, "Rendszer") AS who, c.name AS client
                FROM audit_log a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN clients c ON c.id = a.client_id';
        $params = [];
        if ($clientId !== null) {
            $sql .= ' WHERE a.client_id = ?'; // más ügyfélre vagy globális műveletre vonatkozó sor nem szivároghat át
            $params[] = $clientId;
        }
        return $this->db->all($sql . ' ORDER BY a.id DESC LIMIT ' . max(1, min(1000, $limit)), $params);
    }
}

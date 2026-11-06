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
            str_starts_with($action, 'Cikk') => 'Cikkek',
            str_starts_with($action, 'Vezetői összefoglaló') => 'Futások',
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

    /** Az értesítésekben (csengő) is megjelenő kategóriák annak, akinek nincs Napló-joga. */
    public const WORK_CATEGORIES = ['Döntések', 'Cikkek', 'Posztok', 'Feltöltés', 'Havi e-mail'];

    /**
     * A csengő értesítései: az ügyfél legfrissebb műveletei, a saját műveletek és a belépések nélkül. Napló-jog nélkül csak
     * a munkához tartozó kategóriák (döntés, cikk, poszt, feltöltés, havi e-mail).
     *
     * @return list<array{id:int, action:string, details:string, who:string, category:string, time_hu:string, href:string}>
     */
    public function notifications(Access $a, int $limit = 12): array
    {
        $cid = $a->clientId();
        if ($cid === null) {
            return [];
        }
        $rows = $this->db->all('SELECT a.id, a.created_at, a.action, a.details, COALESCE(u.name, "Rendszer") AS who FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
            WHERE a.client_id = ? AND (a.user_id IS NULL OR a.user_id <> ?) ORDER BY a.id DESC LIMIT 200', [$cid, $a->userId()]);
        $out = [];
        foreach ($rows as $r) {
            $cat = self::category((string) $r['action']);
            if ($cat === 'Belépés' || (!$a->can('log') && !in_array($cat, self::WORK_CATEGORIES, true))) {
                continue;
            }
            $details = (string) $r['details'];
            $href = match ($cat) {
                'Döntések', 'Cikkek', 'Feltöltés' => 'index.php?p=cikkek',
                'Posztok' => preg_match('/\bLI-\d{4}-\d{2}\b/', (string) $r['action'] . ' ' . $details, $m) ? 'index.php?p=poszt&kod=' . $m[0] : 'index.php?p=posztok',
                'Havi e-mail' => $a->can('ops') ? 'index.php?p=vezerlo' : 'index.php?p=attekintes',
                'Források' => $a->can('sources') ? 'index.php?p=forrasok' : 'index.php?p=naplo',
                'Felhasználók' => $a->can('users') ? 'index.php?p=felhasznalok' : 'index.php?p=naplo',
                default => $a->can('log') ? 'index.php?p=naplo' : 'index.php?p=attekintes',
            };
            $out[] = ['id' => (int) $r['id'], 'action' => (string) $r['action'], 'details' => mb_strimwidth($details, 0, 140, '…'), 'who' => (string) $r['who'], 'category' => $cat,
                'time_hu' => \Xperion\Web\View::dateHu((string) $r['created_at']), 'href' => $href];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }
}

<?php
declare(strict_types=1);

namespace Xperion\Auth;

use Xperion\Db\Database;

/** Belépés e-maillel és jelszóval, a hibás próbálkozások korlátozásával. */
final class Authenticator
{
    public const MAX_ATTEMPTS = 5;
    public const WINDOW_SECONDS = 900;
    public const MIN_PASSWORD = 10;

    public function __construct(private Database $db)
    {
    }

    public function setupNeeded(): bool
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM users') === 0;
    }

    /**
     * @return array{user:?array<string,mixed>, error:?string}
     */
    public function attempt(string $email, string $password, string $ip): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        if ((int) $this->db->value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?', [$ip, $since]) >= self::MAX_ATTEMPTS) {
            return ['user' => null, 'error' => 'Túl sok sikertelen próbálkozás. Próbálja újra 15 perc múlva.'];
        }
        $user = $this->db->one('SELECT id, email, name, password_hash, status FROM users WHERE email = ?', [self::normalizeEmail($email)]);
        $ok = $user !== null && $user['password_hash'] !== null && password_verify($password, (string) $user['password_hash']);
        if (!$ok) {
            $this->db->insert('login_attempts', ['ip' => $ip, 'email' => mb_substr(self::normalizeEmail($email), 0, 190), 'attempted_at' => Database::now()]);
            return ['user' => null, 'error' => 'Hibás e-mail-cím vagy jelszó.'];
        }
        if ($user['status'] !== 'aktiv') {
            return ['user' => null, 'error' => $user['status'] === 'letiltva' ? 'A fiókja le van tiltva. Kérje az adminisztrátort.' : 'A fiók még nincs aktiválva. Használja a meghívó linkjét.'];
        }
        $this->db->run('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
        $this->db->run('UPDATE users SET last_login_at = ? WHERE id = ?', [Database::now(), $user['id']]);
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        unset($user['password_hash']);
        return ['user' => $user, 'error' => null];
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function passwordProblem(string $password): ?string
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            return 'A jelszó legalább ' . self::MIN_PASSWORD . ' karakter legyen.';
        }
        return null;
    }
}

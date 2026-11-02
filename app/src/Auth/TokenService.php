<?php
declare(strict_types=1);

namespace Xperion\Auth;

use Xperion\Db\Database;

/**
 * Meghívó- és jelszó-visszaállító linkek. Csak a token SHA-256 lenyomatát
 * tároljuk; a link egyszer használható, és lejár.
 */
final class TokenService
{
    public const INVITE = 'meghivas';
    public const RESET = 'jelszo';
    private const TTL_HOURS = [self::INVITE => 168, self::RESET => 24];

    public function __construct(private Database $db)
    {
    }

    public function issue(int $userId, string $purpose, ?int $createdBy): string
    {
        $token = bin2hex(random_bytes(32));
        // Ugyanarra a célra korábban kiadott, még érvényes linkek érvénytelenítése.
        $this->db->run('UPDATE user_tokens SET used_at = ? WHERE user_id = ? AND purpose = ? AND used_at IS NULL', [Database::now(), $userId, $purpose]);
        $this->db->insert('user_tokens', [
            'user_id' => $userId,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $token),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600 * self::TTL_HOURS[$purpose]),
            'created_by' => $createdBy,
            'created_at' => Database::now(),
        ]);
        return $token;
    }

    /** @return array<string,mixed>|null a tokenhez tartozó felhasználó (csak ellenőrzés, nem használja fel) */
    public function peek(string $token, string $purpose): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return $this->db->one(
            'SELECT t.id AS token_id, u.id, u.email, u.name, u.status FROM user_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.purpose = ? AND t.used_at IS NULL AND t.expires_at > ?',
            [hash('sha256', $token), $purpose, Database::now()]
        );
    }

    /** Felhasználja a tokent; visszaadja a felhasználó azonosítóját, vagy null-t. */
    public function consume(string $token, string $purpose): ?int
    {
        $row = $this->peek($token, $purpose);
        if ($row === null) {
            return null;
        }
        $n = $this->db->run('UPDATE user_tokens SET used_at = ? WHERE id = ? AND used_at IS NULL', [Database::now(), $row['token_id']])->rowCount();
        return $n === 1 ? (int) $row['id'] : null;
    }
}

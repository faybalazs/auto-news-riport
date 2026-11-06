<?php
declare(strict_types=1);

namespace Xperion\Auth;

use Xperion\Db\Database;

/**
 * Felhasználókezelés ügyfélhatárokkal:
 * - az üzemeltető minden felhasználót kezel, üzemeltetőt is létrehozhat;
 * - az ügyfél-adminisztrátor csak a saját (aktuális) ügyfele tagjait kezeli,
 *   üzemeltetőhöz nem nyúlhat, és más ügyfél tagságát nem látja;
 * - saját magát senki nem tilthatja le és nem törölheti.
 */
final class UserAdmin
{
    public function __construct(private Database $db, private TokenService $tokens, private AuditLog $audit)
    {
    }

    /** @return list<array<string,mixed>> */
    public function visibleUsers(Access $a): array
    {
        $a->require('users');
        if ($a->isOperator()) {
            $rows = $this->db->all('SELECT id, email, name, status, is_operator, last_login_at FROM users ORDER BY name');
            $mem = $this->db->all('SELECT m.user_id, m.client_id, m.role, c.name AS client FROM memberships m JOIN clients c ON c.id = m.client_id ORDER BY c.name');
        } else {
            $cid = (int) $a->clientId();
            $rows = $this->db->all('SELECT u.id, u.email, u.name, u.status, u.is_operator, u.last_login_at FROM users u JOIN memberships m ON m.user_id = u.id WHERE m.client_id = ? ORDER BY u.name', [$cid]);
            $mem = $this->db->all('SELECT m.user_id, m.client_id, m.role, c.name AS client FROM memberships m JOIN clients c ON c.id = m.client_id WHERE m.client_id = ?', [$cid]);
        }
        $byUser = [];
        foreach ($mem as $m) {
            $byUser[(int) $m['user_id']][] = ['client_id' => (int) $m['client_id'], 'client' => $m['client'], 'role' => $m['role']];
        }
        return array_map(static function ($u) use ($byUser) {
            $u['id'] = (int) $u['id'];
            $u['is_operator'] = (int) $u['is_operator'] === 1;
            $u['memberships'] = $byUser[$u['id']] ?? [];
            return $u;
        }, $rows);
    }

    /**
     * Meghívás: új felhasználó, vagy meglévő felhasználó tagsága egy további ügyfélben.
     *
     * @return array{user_id:int, token:?string} token: ha új fiók, a meghívó linkhez
     */
    public function invite(Access $a, string $name, string $email, ?int $clientId, string $role): array
    {
        $a->require('users');
        $email = Authenticator::normalizeEmail($email);
        $name = trim($name);
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Adjon meg nevet és érvényes e-mail-címet.');
        }
        if ($role === Roles::OPERATOR) {
            if (!$a->isOperator()) {
                throw new AccessDenied('Üzemeltetőt csak üzemeltető hívhat meg.');
            }
            $clientId = null;
        } else {
            if (!Roles::isClientRole($role)) {
                throw new \InvalidArgumentException('Ismeretlen szerepkör.');
            }
            $clientId = $this->targetClient($a, $clientId);
        }

        return $this->db->transaction(function () use ($a, $name, $email, $clientId, $role) {
            $existing = $this->db->one('SELECT id, is_operator FROM users WHERE email = ?', [$email]);
            if ($existing !== null) {
                $uid = (int) $existing['id'];
                if ($role === Roles::OPERATOR || (int) $existing['is_operator'] === 1) {
                    throw new \InvalidArgumentException('Ezzel a címmel már van fiók.');
                }
                if ($this->db->value('SELECT 1 FROM memberships WHERE user_id = ? AND client_id = ?', [$uid, $clientId])) {
                    throw new \InvalidArgumentException('Ez a felhasználó már tagja ennek az ügyfélnek.');
                }
                $this->db->insert('memberships', ['user_id' => $uid, 'client_id' => $clientId, 'role' => $role, 'created_at' => Database::now()]);
                $this->audit->add($clientId, $a->userId(), 'Felhasználó hozzáadva', "$email · " . Roles::LABELS[$role]);
                return ['user_id' => $uid, 'token' => null];
            }
            $uid = $this->db->insert('users', ['email' => $email, 'name' => $name, 'status' => 'meghivva', 'is_operator' => $role === Roles::OPERATOR ? 1 : 0, 'created_at' => Database::now()]);
            if ($clientId !== null) {
                $this->db->insert('memberships', ['user_id' => $uid, 'client_id' => $clientId, 'role' => $role, 'created_at' => Database::now()]);
            }
            $token = $this->tokens->issue($uid, TokenService::INVITE, $a->userId());
            $this->audit->add($clientId, $a->userId(), 'Felhasználó meghívva', "$email · " . Roles::LABELS[$role]);
            return ['user_id' => $uid, 'token' => $token];
        });
    }

    /** Név és szerepkör módosítása (az aktuális vagy megadott ügyfélben). */
    public function update(Access $a, int $userId, string $name, ?int $clientId, string $role): void
    {
        $target = $this->manageable($a, $userId);
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('A név nem lehet üres.');
        }
        $this->db->update('users', ['name' => $name], ['id' => $userId]);
        if ((int) $target['is_operator'] !== 1) {
            if (!Roles::isClientRole($role)) {
                throw new \InvalidArgumentException('Ismeretlen szerepkör.');
            }
            $clientId = $this->targetClient($a, $clientId);
            $exists = $this->db->value('SELECT 1 FROM memberships WHERE user_id = ? AND client_id = ?', [$userId, $clientId]);
            if ($exists) {
                $this->db->update('memberships', ['role' => $role], ['user_id' => $userId, 'client_id' => $clientId]);
            } elseif ($a->isOperator()) {
                $this->db->insert('memberships', ['user_id' => $userId, 'client_id' => $clientId, 'role' => $role, 'created_at' => Database::now()]);
            } else {
                throw new AccessDenied('Ez a felhasználó nem tagja az ügyfelének.');
            }
        }
        $this->audit->add($clientId, $a->userId(), 'Felhasználó módosítva', $target['email'] . ' · ' . (Roles::LABELS[$role] ?? ''));
    }

    public function setDisabled(Access $a, int $userId, bool $disabled): void
    {
        $target = $this->manageable($a, $userId, true);
        if (!$a->isOperator() && $this->otherClientCount($userId, (int) $a->clientId()) > 0) {
            throw new AccessDenied('Ez a felhasználó más ügyfélnél is dolgozik; letiltását az üzemeltető végezheti.');
        }
        $status = $disabled ? 'letiltva' : ($target['password_hash'] === null ? 'meghivva' : 'aktiv');
        $this->db->update('users', ['status' => $status], ['id' => $userId]);
        $this->audit->add($a->clientId(), $a->userId(), $disabled ? 'Felhasználó letiltva' : 'Felhasználó engedélyezve', (string) $target['email']);
    }

    /**
     * Törlés. Ügyfél-adminisztrátornál, ha a felhasználó más ügyfélnél is tag,
     * csak a saját ügyfelétől veszi el a hozzáférést.
     *
     * @return string 'torolve' | 'eltavolitva'
     */
    public function remove(Access $a, int $userId): string
    {
        $target = $this->manageable($a, $userId, true);
        if (!$a->isOperator() && $this->otherClientCount($userId, (int) $a->clientId()) > 0) {
            $this->db->run('DELETE FROM memberships WHERE user_id = ? AND client_id = ?', [$userId, $a->clientId()]);
            $this->audit->add($a->clientId(), $a->userId(), 'Felhasználó eltávolítva az ügyféltől', (string) $target['email']);
            return 'eltavolitva';
        }
        $this->db->run('DELETE FROM users WHERE id = ?', [$userId]);
        $this->audit->add($a->clientId(), $a->userId(), 'Felhasználó törölve', (string) $target['email']);
        return 'torolve';
    }

    /** Új meghívó link (meghívott) vagy jelszó-visszaállító link (aktív felhasználó). */
    public function issueLink(Access $a, int $userId): array
    {
        $target = $this->manageable($a, $userId);
        if ($target['status'] === 'letiltva') {
            throw new \InvalidArgumentException('Letiltott felhasználónak nem adható ki link.');
        }
        $purpose = $target['status'] === 'meghivva' ? TokenService::INVITE : TokenService::RESET;
        $token = $this->tokens->issue($userId, $purpose, $a->userId());
        $this->audit->add($a->clientId(), $a->userId(), $purpose === TokenService::INVITE ? 'Meghívó link kiadva' : 'Jelszó-visszaállító link kiadva', (string) $target['email']);
        return ['purpose' => $purpose, 'token' => $token];
    }

    /** Meghívó elfogadása vagy jelszó-visszaállítás. */
    public function setPasswordWithToken(string $token, string $purpose, string $password): ?int
    {
        if (($problem = Authenticator::passwordProblem($password)) !== null) {
            throw new \InvalidArgumentException($problem);
        }
        return $this->db->transaction(function () use ($token, $purpose, $password) {
            $uid = $this->tokens->consume($token, $purpose);
            if ($uid === null) {
                return null;
            }
            $this->db->run("UPDATE users SET password_hash = ?, status = IF(status = 'letiltva', 'letiltva', 'aktiv') WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $uid]);
            $this->audit->add(null, $uid, $purpose === TokenService::INVITE ? 'Meghívó elfogadva' : 'Jelszó visszaállítva');
            return $uid;
        });
    }

    public function changeOwnPassword(Access $a, string $current, string $new): void
    {
        $hash = (string) $this->db->value('SELECT password_hash FROM users WHERE id = ?', [$a->userId()]);
        if (!password_verify($current, $hash)) {
            throw new \InvalidArgumentException('A jelenlegi jelszó nem megfelelő.');
        }
        if (($problem = Authenticator::passwordProblem($new)) !== null) {
            throw new \InvalidArgumentException($problem);
        }
        $this->db->update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], ['id' => $a->userId()]);
        $this->audit->add(null, $a->userId(), 'Saját jelszó módosítva');
    }

    /** Első üzemeltető létrehozása (csak üres adatbázisnál). */
    public function createFirstOperator(string $name, string $email, string $password): int
    {
        if ((int) $this->db->value('SELECT COUNT(*) FROM users') > 0) {
            throw new AccessDenied('A rendszer már be van állítva.');
        }
        $email = Authenticator::normalizeEmail($email);
        if (trim($name) === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Adjon meg nevet és érvényes e-mail-címet.');
        }
        if (($problem = Authenticator::passwordProblem($password)) !== null) {
            throw new \InvalidArgumentException($problem);
        }
        $id = $this->db->insert('users', ['email' => $email, 'name' => trim($name), 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'status' => 'aktiv', 'is_operator' => 1, 'created_at' => Database::now()]);
        $this->audit->add(null, $id, 'Rendszer beállítva', 'első üzemeltető: ' . $email);
        return $id;
    }

    private function targetClient(Access $a, ?int $clientId): int
    {
        $clientId ??= $a->clientId();
        if ($clientId === null) {
            throw new \InvalidArgumentException('Előbb hozzon létre egy ügyfelet.');
        }
        if (!$a->isOperator() && $clientId !== $a->clientId()) {
            throw new AccessDenied('Csak a saját ügyfele felhasználóit kezelheti.');
        }
        if (!$a->canAccessClient($clientId)) {
            throw new AccessDenied('Ehhez az ügyfélhez nincs hozzáférése.');
        }
        return $clientId;
    }

    /** @return array<string,mixed> */
    private function manageable(Access $a, int $userId, bool $notSelf = false): array
    {
        $a->require('users');
        $target = $this->db->one('SELECT id, email, name, status, is_operator, password_hash FROM users WHERE id = ?', [$userId]);
        if ($target === null) {
            throw new \InvalidArgumentException('Nincs ilyen felhasználó.');
        }
        if ($notSelf && $userId === $a->userId()) {
            throw new AccessDenied('Saját fiókját nem tilthatja le és nem törölheti.');
        }
        if (!$a->isOperator()) {
            if ((int) $target['is_operator'] === 1) {
                throw new AccessDenied('Az üzemeltető fiókját csak üzemeltető módosíthatja.');
            }
            if (!$this->db->value('SELECT 1 FROM memberships WHERE user_id = ? AND client_id = ?', [$userId, $a->clientId()])) {
                // Ne áruljuk el, hogy a felhasználó létezik-e másik ügyfélnél.
                throw new \InvalidArgumentException('Nincs ilyen felhasználó.');
            }
        }
        return $target;
    }

    private function otherClientCount(int $userId, int $clientId): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM memberships WHERE user_id = ? AND client_id <> ?', [$userId, $clientId]);
    }
}

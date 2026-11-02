<?php
declare(strict_types=1);

namespace Xperion\Auth;

use Xperion\Db\Database;

/**
 * Egy kérés hozzáférési köre: ki van belépve, melyik ügyfélben dolgozik,
 * és mit tehet ott. Az ügyfelet a szerver a tagságokból dönti el; a kliens
 * által kért ügyfél csak akkor érvényes, ha a felhasználó hozzáfér.
 */
final class Access
{
    /**
     * @param array<string,mixed> $user
     * @param array<int,string> $memberships client_id => szerepkör
     * @param list<array<string,mixed>> $clients a felhasználó által elérhető ügyfelek
     */
    private function __construct(
        public readonly array $user,
        private array $memberships,
        private array $clients,
        private ?int $clientId,
    ) {
    }

    public static function forUser(Database $db, int $userId, ?int $requestedClientId): ?self
    {
        $user = $db->one("SELECT id, email, name, status, is_operator, last_login_at FROM users WHERE id = ? AND status = 'aktiv'", [$userId]);
        if ($user === null) {
            return null;
        }
        $memberships = [];
        foreach ($db->all('SELECT client_id, role FROM memberships WHERE user_id = ?', [$userId]) as $m) {
            $memberships[(int) $m['client_id']] = (string) $m['role'];
        }
        $clients = (int) $user['is_operator'] === 1
            ? $db->all('SELECT id, name, status, website FROM clients ORDER BY name')
            : ($memberships ? $db->all('SELECT id, name, status, website FROM clients WHERE id IN (' . implode(',', array_map('intval', array_keys($memberships))) . ') ORDER BY name') : []);
        $ids = array_map(static fn ($c) => (int) $c['id'], $clients);
        $clientId = $requestedClientId !== null && in_array($requestedClientId, $ids, true) ? $requestedClientId : ($ids[0] ?? null);
        return new self($user, $memberships, $clients, $clientId);
    }

    public function userId(): int
    {
        return (int) $this->user['id'];
    }

    public function isOperator(): bool
    {
        return (int) $this->user['is_operator'] === 1;
    }

    /** Az aktuális ügyfél azonosítója (null, ha még nincs ügyfél). */
    public function clientId(): ?int
    {
        return $this->clientId;
    }

    /** @return array<string,mixed>|null */
    public function client(): ?array
    {
        foreach ($this->clients as $c) {
            if ((int) $c['id'] === $this->clientId) {
                return $c;
            }
        }
        return null;
    }

    /** @return list<array<string,mixed>> */
    public function clients(): array
    {
        return $this->clients;
    }

    public function canAccessClient(int $clientId): bool
    {
        foreach ($this->clients as $c) {
            if ((int) $c['id'] === $clientId) {
                return true;
            }
        }
        return false;
    }

    /** Szerepkör az aktuális ügyfélben (az üzemeltetőé mindenhol üzemeltető). */
    public function role(): ?string
    {
        if ($this->isOperator()) {
            return Roles::OPERATOR;
        }
        return $this->clientId === null ? null : ($this->memberships[$this->clientId] ?? null);
    }

    public function roleIn(int $clientId): ?string
    {
        return $this->isOperator() ? Roles::OPERATOR : ($this->memberships[$clientId] ?? null);
    }

    public function can(string $permission): bool
    {
        return Roles::grants($this->role(), $permission);
    }

    public function require(string $permission): void
    {
        if (!$this->can($permission)) {
            throw new AccessDenied('Ehhez a művelethez nincs jogosultsága.');
        }
    }
}

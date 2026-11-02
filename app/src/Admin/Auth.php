<?php
declare(strict_types=1);

namespace Xperion\Admin;

/**
 * Kulcsos belépés az admin felületre, munkamenet-sütivel, CSRF-tokennel
 * és a hibás próbálkozások korlátozásával (IP-nként 5 / 15 perc).
 */
final class Auth
{
    private const MAX_FAILS = 5;
    private const WINDOW = 900;
    private const IDLE = 43200; // 12 óra

    public function __construct(private string $adminKey, private string $failFile)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        session_name('xperion_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    public function configured(): bool
    {
        return strlen($this->adminKey) >= 16;
    }

    public function check(): bool
    {
        $this->start();
        if (empty($_SESSION['auth'])) {
            return false;
        }
        if (time() - (int) ($_SESSION['seen'] ?? 0) > self::IDLE) {
            $this->logout();
            return false;
        }
        $_SESSION['seen'] = time();
        return true;
    }

    /** @return string|null hibaüzenet, vagy null siker esetén */
    public function login(string $key, string $ip): ?string
    {
        $this->start();
        if (!$this->configured()) {
            return 'Az admin kulcs nincs beállítva a config.php-ban (legalább 16 karakter).';
        }
        if ($this->recentFails($ip) >= self::MAX_FAILS) {
            return 'Túl sok sikertelen próbálkozás. Próbálja újra 15 perc múlva.';
        }
        if (!hash_equals($this->adminKey, $key)) {
            $this->recordFail($ip);
            usleep(400_000);
            return 'Hibás kulcs. Ellenőrizze, és próbálja újra.';
        }
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $_SESSION['seen'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return null;
    }

    public function logout(): void
    {
        $this->start();
        $_SESSION = [];
        session_destroy();
    }

    public function csrf(): string
    {
        $this->start();
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public function validCsrf(string $token): bool
    {
        return $token !== '' && hash_equals($this->csrf(), $token);
    }

    private function recentFails(string $ip): int
    {
        $data = $this->fails();
        return count(array_filter($data[$ip] ?? [], static fn ($t) => $t > time() - self::WINDOW));
    }

    private function recordFail(string $ip): void
    {
        $data = $this->fails();
        $data[$ip] = array_values(array_filter($data[$ip] ?? [], static fn ($t) => $t > time() - self::WINDOW));
        $data[$ip][] = time();
        // régi IP-k törlése
        $data = array_filter($data, static fn ($list) => $list !== []);
        @file_put_contents($this->failFile, (string) json_encode($data), LOCK_EX);
    }

    /** @return array<string,list<int>> */
    private function fails(): array
    {
        $d = is_file($this->failFile) ? json_decode((string) file_get_contents($this->failFile), true) : [];
        return is_array($d) ? $d : [];
    }
}

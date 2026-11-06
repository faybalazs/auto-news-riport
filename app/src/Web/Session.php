<?php
declare(strict_types=1);

namespace Xperion\Web;

/** Munkamenet: biztonságos süti, CSRF-token, egyszeri üzenetek. */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('xperion');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/',
            'secure' => Http::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        // 12 óra tétlenség után kilépés
        if (isset($_SESSION['seen']) && time() - (int) $_SESSION['seen'] > 43200) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['seen'] = time();
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $userId;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
    }

    public static function clientId(): ?int
    {
        return isset($_SESSION['cid']) ? (int) $_SESSION['cid'] : null;
    }

    public static function setClientId(?int $id): void
    {
        $_SESSION['cid'] = $id;
    }

    public static function csrf(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function validCsrf(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals(self::csrf(), $token);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string, message:string}> */
    public static function takeFlash(): array
    {
        $f = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $f;
    }
}

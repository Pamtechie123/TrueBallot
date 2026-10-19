<?php
declare(strict_types=1);

namespace App\Core;

/** Small wrapper around PHP sessions, including one-request "flash" messages. */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            // Render handles HTTPS in front of the app and tells us through X-Forwarded-Proto.
            'secure'   => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        ]);
        session_start();

        // Messages saved during the previous request become readable now, then disappear.
        $_SESSION['_flash']      = $_SESSION['_flash_next'] ?? [];
        $_SESSION['_flash_next'] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Renew the session id (used after login to prevent session fixation). */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /** Remove everything stored in the session. */
    public static function flush(): void
    {
        $_SESSION = [];
    }

    /** Save a value for the next request only. */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    /** Read a value saved by the previous request. */
    public static function flashed(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash'][$key] ?? $default;
    }

    /** Old form value from the previous request. */
    public static function old(string $key, string $default = ''): string
    {
        $old = self::flashed('old', []);
        return (string) ($old[$key] ?? $default);
    }
}

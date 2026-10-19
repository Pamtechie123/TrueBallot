<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A password reset link. Only a fingerprint (SHA-256) of the secret token is stored,
 * so someone who reads the database cannot use the links. A link works once and expires.
 */
final class PasswordReset extends Model
{
    protected static string $table = 'password_resets';

    public const LIFETIME_MINUTES = 60;
    public const MAX_REQUESTS_PER_HOUR = 3;

    /** Make a new link for a user. Older unused links stop working. Returns the secret token. */
    public static function createFor(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $now   = date('Y-m-d H:i:s');

        static::run('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [$now, $userId]);
        static::run(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
            [$userId, self::fingerprint($token), date('Y-m-d H:i:s', time() + self::LIFETIME_MINUTES * 60), $now]
        );

        return $token;
    }

    /** How many links were requested for this user in the last hour. */
    public static function recentRequests(int $userId): int
    {
        return (int) static::run(
            'SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at >= ?',
            [$userId, date('Y-m-d H:i:s', time() - 3600)]
        )->fetchColumn();
    }

    /** The reset for this token if it exists, is unused and has not expired. */
    public static function findValid(string $token): ?PasswordReset
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $row = static::run(
            'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL LIMIT 1',
            [self::fingerprint($token)]
        )->fetch();

        if (!$row || strtotime((string) $row['expires_at']) < time()) {
            return null;
        }
        return new self($row);
    }

    /** Mark the link as used. False if someone else used it first. */
    public function consume(): bool
    {
        return static::run(
            'UPDATE password_resets SET used_at = ? WHERE id = ? AND used_at IS NULL',
            [date('Y-m-d H:i:s'), $this->id()]
        )->rowCount() === 1;
    }

    public function userId(): int
    {
        return (int) $this->get('user_id', 0);
    }

    private static function fingerprint(string $token): string
    {
        return hash('sha256', $token);
    }
}
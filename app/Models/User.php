<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Interfaces\RoleInterface;

/**
 * ABSTRACT user. Voter and Admin extend it and each decides what it may open (polymorphism).
 * Loading a user from the database automatically gives back the right kind of object.
 */
abstract class User extends Model implements RoleInterface
{
    protected static string $table = 'users';

    public const STATUSES = ['pending', 'approved', 'rejected', 'suspended'];

    /** Pick Admin or Voter from the role stored in the database. */
    protected static function hydrate(array $row): Model
    {
        return ($row['role'] ?? 'voter') === 'admin' ? new Admin($row) : new Voter($row);
    }

    public static function find(int $id): ?User
    {
        $user = parent::find($id);
        return $user instanceof User ? $user : null;
    }

    public static function findByEmail(string $email): ?User
    {
        $row = static::run('SELECT * FROM users WHERE email = ? LIMIT 1', [strtolower(trim($email))])->fetch();
        return $row ? static::hydrate($row) : null;
    }

    public static function emailExists(string $email): bool
    {
        return static::findByEmail($email) !== null;
    }

    /** Create a new voter. New accounts always start as "pending". Returns the new id. */
    public static function create(array $data): int
    {
        static::run(
            "INSERT INTO users (full_name, email, password_hash, age, state, sex, disability_status, role, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'voter', 'pending')",
            [
                $data['full_name'],
                strtolower($data['email']),
                password_hash($data['password'], PASSWORD_DEFAULT),
                (int) $data['age'],
                $data['state'],
                $data['sex'],
                $data['disability_status'],
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }

    // ---- reading the user's details ----

    public function fullName(): string
    {
        return (string) $this->get('full_name', '');
    }

    public function email(): string
    {
        return (string) $this->get('email', '');
    }

    public function passwordHash(): string
    {
        return (string) $this->get('password_hash', '');
    }

    public function age(): int
    {
        return (int) $this->get('age', 0);
    }

    public function state(): string
    {
        return (string) $this->get('state', '');
    }

    public function sex(): string
    {
        return (string) $this->get('sex', 'prefer_not_to_say');
    }

    public function disabilityStatus(): string
    {
        return (string) $this->get('disability_status', 'prefer_not_to_say');
    }

    public function status(): string
    {
        return (string) $this->get('status', 'pending');
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }

    /** Unique voter reference built from the user id, for example TB-000042. */
    public function voterNumber(): string
    {
        return 'TB-' . str_pad((string) $this->id(), 6, '0', STR_PAD_LEFT);
    }

    public function isApproved(): bool
    {
        return $this->status() === 'approved';
    }

    /** Rejected and suspended accounts cannot use the system. */
    public function isBlocked(): bool
    {
        return in_array($this->status(), ['rejected', 'suspended'], true);
    }

    // ---- voter management (used by the admin pages) ----

    /** Number of voters, optionally only those with one status. Admins are not counted. */
    public static function countVoters(?string $status = null): int
    {
        $sql    = "SELECT COUNT(*) FROM users WHERE role = 'voter'";
        $params = [];
        if ($status !== null) {
            $sql    .= ' AND status = ?';
            $params[] = $status;
        }
        return (int) static::run($sql, $params)->fetchColumn();
    }

    /** @return User[] voters, newest first, optionally only those with one status */
    public static function voters(?string $status = null): array
    {
        $sql    = "SELECT * FROM users WHERE role = 'voter'";
        $params = [];
        if ($status !== null) {
            $sql    .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY id DESC';

        return array_map(fn (array $row) => static::hydrate($row), static::run($sql, $params)->fetchAll());
    }

    /** @return User[] every administrator (they get emails about new registrations and applications) */
    public static function admins(): array
    {
        return array_map(
            fn (array $row) => static::hydrate($row),
            static::run("SELECT * FROM users WHERE role = 'admin'")->fetchAll()
        );
    }

    public static function updateStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Unknown status: ' . $status);
        }
        static::run("UPDATE users SET status = ? WHERE id = ? AND role = 'voter'", [$status, $id]);
    }

    /** Save a new password (stored as a hash, never as plain text). */
    public static function updatePassword(int $id, string $newPassword): void
    {
        static::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($newPassword, PASSWORD_DEFAULT), $id]);
    }

    /** Save the details a user can edit about themselves. The email is never changed here. */
    public static function updateDetails(int $id, array $data): void
    {
        static::run(
            'UPDATE users SET full_name = ?, age = ?, state = ?, sex = ?, disability_status = ? WHERE id = ?',
            [$data['full_name'], (int) $data['age'], $data['state'], $data['sex'], $data['disability_status'], $id]
        );
    }

    // ---- statistics for the admin dashboard ----

    /** Voters grouped by state, sex or disability_status. */
    public static function breakdown(string $column): array
    {
        if (!in_array($column, ['state', 'sex', 'disability_status'], true)) {
            throw new \InvalidArgumentException('Unknown column: ' . $column);
        }

        return static::run(
            "SELECT `$column` AS label, COUNT(*) AS total
             FROM users WHERE role = 'voter'
             GROUP BY `$column`
             ORDER BY total DESC, label ASC"
        )->fetchAll();
    }

    public static function ageBands(): array
    {
        return static::run(
            "SELECT CASE
                        WHEN age < 18 THEN 'Under 18'
                        WHEN age < 25 THEN '18-24'
                        WHEN age < 35 THEN '25-34'
                        WHEN age < 45 THEN '35-44'
                        WHEN age < 55 THEN '45-54'
                        ELSE '55 and over'
                    END AS label,
                    COUNT(*) AS total
             FROM users WHERE role = 'voter'
             GROUP BY label
             ORDER BY MIN(age)"
        )->fetchAll();
    }
}

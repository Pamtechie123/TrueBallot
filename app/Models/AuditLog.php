<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Activity record: which administrator did what, and when. Never store passwords here. */
final class AuditLog extends Model
{
    protected static string $table = 'audit_logs';

    public static function record(?int $actorUserId, string $action, string $details = ''): void
    {
        static::run(
            'INSERT INTO audit_logs (actor_user_id, action, details) VALUES (?, ?, ?)',
            [$actorUserId, $action, $details]
        );
    }

    /** @return AuditLog[] newest first */
    public static function recent(int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));

        $rows = static::run(
            "SELECT a.*, u.full_name AS actor_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.actor_user_id
             ORDER BY a.id DESC
             LIMIT $limit"
        )->fetchAll();

        return array_map(fn (array $row) => new static($row), $rows);
    }

    public function action(): string
    {
        return (string) $this->get('action', '');
    }

    public function details(): string
    {
        return (string) $this->get('details', '');
    }

    public function actorName(): string
    {
        return (string) $this->get('actor_name', 'System');
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }
}

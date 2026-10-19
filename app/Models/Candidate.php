<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** A person on the ballot. One row is created when an administrator approves an application. */
final class Candidate extends Model
{
    protected static string $table = 'candidates';

    public static function create(int $applicationId, int $positionId): int
    {
        static::run('INSERT INTO candidates (application_id, position_id) VALUES (?, ?)', [$applicationId, $positionId]);
        return (int) Database::connection()->lastInsertId();
    }

    /** The candidates for one position, with the name and manifesto from their application. @return Candidate[] */
    public static function forPosition(int $positionId): array
    {
        return array_map(
            fn (array $row) => static::hydrate($row),
            static::run(
                'SELECT c.id, c.application_id, c.position_id, a.user_id, a.display_name, a.manifesto
                 FROM candidates c
                 JOIN candidate_applications a ON a.id = c.application_id
                 WHERE c.position_id = ?
                 ORDER BY a.display_name',
                [$positionId]
            )->fetchAll()
        );
    }

    public static function belongsToPosition(int $candidateId, int $positionId): bool
    {
        return (int) static::run(
            'SELECT COUNT(*) FROM candidates WHERE id = ? AND position_id = ?',
            [$candidateId, $positionId]
        )->fetchColumn() > 0;
    }

    /** True if this user is on any ballot (their photo may then be shown to voters). */
    public static function isCandidate(int $userId): bool
    {
        return (int) static::run(
            'SELECT COUNT(*) FROM candidates c JOIN candidate_applications a ON a.id = c.application_id WHERE a.user_id = ?',
            [$userId]
        )->fetchColumn() > 0;
    }

    public static function countForPosition(int $positionId): int
    {
        return (int) static::run('SELECT COUNT(*) FROM candidates WHERE position_id = ?', [$positionId])->fetchColumn();
    }

    public static function countAll(): int
    {
        return (int) static::run('SELECT COUNT(*) FROM candidates')->fetchColumn();
    }

    /** The voter who is standing as this candidate (used to show their photo). */
    public function userId(): int
    {
        return (int) $this->get('user_id', 0);
    }

    public function displayName(): string
    {
        return (string) $this->get('display_name', '');
    }

    public function manifesto(): string
    {
        return (string) $this->get('manifesto', '');
    }
}

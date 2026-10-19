<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** A position in an election, such as President or Governor, with its own minimum candidate age. */
final class Position extends Model
{
    protected static string $table = 'positions';

    public static function find(int $id): ?Position
    {
        $position = parent::find($id);
        return $position instanceof Position ? $position : null;
    }

    public static function create(int $electionId, string $title, int $minCandidateAge, float $feeAmount): int
    {
        static::run(
            'INSERT INTO positions (election_id, title, min_candidate_age, fee_amount) VALUES (?, ?, ?, ?)',
            [$electionId, $title, $minCandidateAge, $feeAmount]
        );
        return (int) Database::connection()->lastInsertId();
    }

    /** @return Position[] */
    public static function forElection(int $electionId): array
    {
        return array_map(
            fn (array $row) => static::hydrate($row),
            static::run('SELECT * FROM positions WHERE election_id = ? ORDER BY id', [$electionId])->fetchAll()
        );
    }

    public static function countForElection(int $electionId): int
    {
        return (int) static::run('SELECT COUNT(*) FROM positions WHERE election_id = ?', [$electionId])->fetchColumn();
    }

    /**
     * Positions that voters can apply for right now (their election's application period is running).
     * @return Position[]
     */
    public static function openForApplications(): array
    {
        $ids = array_map(fn (Election $e) => $e->id(), Election::withPhase('applications_open'));
        if ($ids === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));

        return array_map(
            fn (array $row) => static::hydrate($row),
            static::run(
                "SELECT p.*, e.title AS election_title
                 FROM positions p
                 JOIN elections e ON e.id = p.election_id
                 WHERE p.election_id IN ($marks)
                 ORDER BY e.id DESC, p.id",
                $ids
            )->fetchAll()
        );
    }

    public function title(): string
    {
        return (string) $this->get('title', '');
    }

    public function electionId(): int
    {
        return (int) $this->get('election_id', 0);
    }

    public function minCandidateAge(): int
    {
        return (int) $this->get('min_candidate_age', 18);
    }

    /** Only filled when the position was loaded together with its election. */
    /** Application fee set by the administrator for this position (0 = free). */
    public function feeAmount(): float
    {
        return (float) $this->get('fee_amount', 0);
    }

    public function electionTitle(): string
    {
        return (string) $this->get('election_title', '');
    }
}
<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A recorded vote. The database allows only one vote per voter per position,
 * and this class checks every rule again on the server before saving.
 */
final class Vote extends Model
{
    protected static string $table = 'votes';

    public static function hasVoted(int $voterId, int $positionId): bool
    {
        return (int) static::run(
            'SELECT COUNT(*) FROM votes WHERE voter_id = ? AND position_id = ?',
            [$voterId, $positionId]
        )->fetchColumn() > 0;
    }

    /** What this voter already chose in an election. @return array<int,int> position id => candidate id */
    public static function choicesOf(int $voterId, int $electionId): array
    {
        $rows = static::run(
            'SELECT position_id, candidate_id FROM votes WHERE voter_id = ? AND election_id = ?',
            [$voterId, $electionId]
        )->fetchAll();

        $choices = [];
        foreach ($rows as $row) {
            $choices[(int) $row['position_id']] = (int) $row['candidate_id'];
        }
        return $choices;
    }

    /**
     * Save a voter's choices for one election. $choices is [position id => candidate id].
     * The controller runs this inside a transaction, so it is all saved or nothing is.
     * Returns how many votes were saved. Throws DomainException with a clear message when a rule is broken.
     */
    public static function cast(int $voterId, Election $election, array $choices): int
    {
        if (!$election->isOpenForVoting()) {
            throw new \DomainException('Voting is not open for this election right now.');
        }
        if ($choices === []) {
            throw new \DomainException('Please choose at least one candidate before you submit.');
        }

        $saved = 0;
        foreach ($choices as $positionId => $candidateId) {
            $position = Position::find((int) $positionId);

            if ($position === null || $position->electionId() !== $election->id()) {
                throw new \DomainException('One of the positions is not part of this election.');
            }
            if (!Candidate::belongsToPosition((int) $candidateId, $position->id())) {
                throw new \DomainException('That candidate is not on the ballot for ' . $position->title() . '.');
            }
            if (static::hasVoted($voterId, $position->id())) {
                throw new \DomainException('You have already voted for ' . $position->title() . '.');
            }

            static::run(
                'INSERT INTO votes (voter_id, election_id, position_id, candidate_id) VALUES (?, ?, ?, ?)',
                [$voterId, $election->id(), $position->id(), (int) $candidateId]
            );
            $saved++;
        }

        return $saved;
    }

    public static function countAll(): int
    {
        return (int) static::run('SELECT COUNT(*) FROM votes')->fetchColumn();
    }

    /** How many different voters took part in an election. */
    public static function votersIn(int $electionId): int
    {
        return (int) static::run(
            'SELECT COUNT(DISTINCT voter_id) FROM votes WHERE election_id = ?',
            [$electionId]
        )->fetchColumn();
    }

    /**
     * Results of one election, counted from the stored votes.
     * @return array<int,array{title:string,total:int,candidates:array<int,array{name:string,votes:int}>}>
     */
    public static function tally(int $electionId): array
    {
        $rows = static::run(
            'SELECT p.id AS position_id, p.title AS position_title,
                    c.id AS candidate_id, a.display_name, COUNT(v.id) AS total
             FROM positions p
             LEFT JOIN candidates c ON c.position_id = p.id
             LEFT JOIN candidate_applications a ON a.id = c.application_id
             LEFT JOIN votes v ON v.candidate_id = c.id
             WHERE p.election_id = ?
             GROUP BY p.id, p.title, c.id, a.display_name
             ORDER BY p.id, total DESC, a.display_name',
            [$electionId]
        )->fetchAll();

        $positions = [];
        foreach ($rows as $row) {
            $id = (int) $row['position_id'];
            $positions[$id] ??= ['title' => (string) $row['position_title'], 'total' => 0, 'candidates' => []];

            if ($row['candidate_id'] !== null) {
                $positions[$id]['candidates'][] = ['name' => (string) $row['display_name'], 'votes' => (int) $row['total']];
                $positions[$id]['total']       += (int) $row['total'];
            }
        }

        return array_values($positions);
    }
}

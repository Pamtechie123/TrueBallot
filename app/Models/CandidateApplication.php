<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * A voter's application to contest a position.
 * When an administrator approves it, a row is also added to the candidates table.
 */
final class CandidateApplication extends Model
{
    protected static string $table = 'candidate_applications';

    public const FEE_STATUSES = ['unpaid', 'recorded_paid', 'waived'];

    public static function find(int $id): ?CandidateApplication
    {
        $application = parent::find($id);
        return $application instanceof CandidateApplication ? $application : null;
    }

    public static function create(int $userId, int $positionId, string $displayName, string $manifesto, float $feeAmount): int
    {
        static::run(
            'INSERT INTO candidate_applications (user_id, position_id, display_name, manifesto, fee_amount)
             VALUES (?, ?, ?, ?, ?)',
            [$userId, $positionId, $displayName, $manifesto, $feeAmount]
        );
        return (int) Database::connection()->lastInsertId();
    }

    public static function existsFor(int $userId, int $positionId): bool
    {
        return (int) static::run(
            'SELECT COUNT(*) FROM candidate_applications WHERE user_id = ? AND position_id = ?',
            [$userId, $positionId]
        )->fetchColumn() > 0;
    }

    /** One voter's own applications, newest first. @return CandidateApplication[] */
    public static function forUser(int $userId): array
    {
        return array_map(
            fn (array $row) => static::hydrate($row),
            static::run(
                'SELECT a.*, p.title AS position_title, p.min_candidate_age, p.election_id AS election_id, e.title AS election_title
                 FROM candidate_applications a
                 JOIN positions p ON p.id = a.position_id
                 JOIN elections e ON e.id = p.election_id
                 WHERE a.user_id = ?
                 ORDER BY a.id DESC',
                [$userId]
            )->fetchAll()
        );
    }

    /** Applications with applicant, position and election details, for the administrator. @return CandidateApplication[] */
    public static function listForReview(?string $status = null): array
    {
        $sql = 'SELECT a.*, u.full_name, u.email, u.age, u.state,
                       p.title AS position_title, p.min_candidate_age,
                       p.election_id AS election_id, e.title AS election_title
                FROM candidate_applications a
                JOIN users u ON u.id = a.user_id
                JOIN positions p ON p.id = a.position_id
                JOIN elections e ON e.id = p.election_id';
        $params = [];
        if ($status !== null) {
            $sql    .= ' WHERE a.application_status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY a.id DESC';

        return array_map(fn (array $row) => static::hydrate($row), static::run($sql, $params)->fetchAll());
    }

    public static function findWithDetails(int $id): ?CandidateApplication
    {
        $row = static::run(
            'SELECT a.*, u.full_name, u.email, u.age, u.state,
                    p.title AS position_title, p.min_candidate_age,
                    p.election_id AS election_id, e.title AS election_title
             FROM candidate_applications a
             JOIN users u ON u.id = a.user_id
             JOIN positions p ON p.id = a.position_id
             JOIN elections e ON e.id = p.election_id
             WHERE a.id = ?',
            [$id]
        )->fetch();

        return $row ? static::hydrate($row) : null;
    }

    public static function countByStatus(string $status): int
    {
        return (int) static::run(
            'SELECT COUNT(*) FROM candidate_applications WHERE application_status = ?',
            [$status]
        )->fetchColumn();
    }

    // ---- decisions (the controller wraps these in a transaction) ----

    /** Approve a pending application and put the person on the ballot. */
    public function approve(int $adminId): void
    {
        $this->markReviewed('approved', $adminId);
        Candidate::create($this->id(), $this->positionId());
    }

    public function reject(int $adminId): void
    {
        $this->markReviewed('rejected', $adminId);
    }

    /** Recording the fee is a separate decision from approving the application. */
    public function setFeeStatus(string $feeStatus): void
    {
        if (!in_array($feeStatus, self::FEE_STATUSES, true)) {
            throw new \InvalidArgumentException('Unknown fee status: ' . $feeStatus);
        }
        static::run('UPDATE candidate_applications SET fee_status = ? WHERE id = ?', [$feeStatus, $this->id()]);
    }

    private function markReviewed(string $status, int $adminId): void
    {
        $changed = static::run(
            "UPDATE candidate_applications
             SET application_status = ?, reviewed_by = ?, reviewed_at = ?
             WHERE id = ? AND application_status = 'pending'",
            [$status, $adminId, date('Y-m-d H:i:s'), $this->id()]
        )->rowCount();

        if ($changed !== 1) {
            throw new \DomainException('This application has already been reviewed.');
        }
    }

    // ---- reading the application's details ----

    public function userId(): int
    {
        return (int) $this->get('user_id', 0);
    }

    public function positionId(): int
    {
        return (int) $this->get('position_id', 0);
    }

    public function displayName(): string
    {
        return (string) $this->get('display_name', '');
    }

    public function manifesto(): string
    {
        return (string) $this->get('manifesto', '');
    }

    public function feeAmount(): float
    {
        return (float) $this->get('fee_amount', 0);
    }

    public function feeStatus(): string
    {
        return (string) $this->get('fee_status', 'unpaid');
    }

    public function status(): string
    {
        return (string) $this->get('application_status', 'pending');
    }

    public function createdAt(): string
    {
        return (string) $this->get('created_at', '');
    }

    // Details that are only filled when loaded together with the applicant, position and election.

    public function applicantName(): string
    {
        return (string) $this->get('full_name', '');
    }

    public function applicantEmail(): string
    {
        return (string) $this->get('email', '');
    }

    public function applicantAge(): int
    {
        return (int) $this->get('age', 0);
    }

    public function applicantState(): string
    {
        return (string) $this->get('state', '');
    }

    public function positionTitle(): string
    {
        return (string) $this->get('position_title', '');
    }

    public function minCandidateAge(): int
    {
        return (int) $this->get('min_candidate_age', 18);
    }

    public function electionTitle(): string
    {
        return (string) $this->get('election_title', '');
    }

    public function election(): ?Election
    {
        return Election::find((int) $this->get('election_id', 0));
    }

    /** Where the election is now (see Election::phase()). */
    public function electionPhase(): string
    {
        return $this->election()?->phase() ?? '';
    }
}
<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * A person on the ballot. An administrator adds each candidate, so a candidate does not need a voter account.
 * The candidate keeps their own name, age, party, email, manifesto, fee status and photo.
 */
final class Candidate extends Model
{
    protected static string $table = 'candidates';

    public const FEE_STATUSES = ['unpaid', 'recorded_paid', 'waived'];

    private const WITH_PHOTO_VERSION = '(SELECT UNIX_TIMESTAMP(cp.updated_at) FROM candidate_photos cp WHERE cp.candidate_id = c.id) AS photo_version';

    public static function find(int $id): ?Candidate
    {
        $row = static::run(
            'SELECT c.*, p.title AS position_title, p.min_candidate_age, p.election_id AS election_id, e.title AS election_title, ' . self::WITH_PHOTO_VERSION . '
             FROM candidates c
             JOIN positions p ON p.id = c.position_id
             JOIN elections e ON e.id = p.election_id
             WHERE c.id = ?',
            [$id]
        )->fetch();

        return $row ? static::hydrate($row) : null;
    }

    /** @param array{position_id:int,user_id:int,full_name:string,age:int,party:string,email:string,manifesto:string,fee_amount:float,fee_status:string} $data */
    public static function create(array $data): int
    {
        static::run(
            'INSERT INTO candidates (position_id, user_id, full_name, age, party, email, manifesto, fee_amount, fee_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['position_id'], $data['user_id'], $data['full_name'], $data['age'],
                $data['party'] !== '' ? $data['party'] : null,
                $data['email'] !== '' ? $data['email'] : null,
                $data['manifesto'], $data['fee_amount'], $data['fee_status'],
            ]
        );
        return (int) Database::connection()->lastInsertId();
    }

    public static function updateDetails(int $id, array $data): void
    {
        static::run(
            'UPDATE candidates SET full_name = ?, age = ?, party = ?, email = ?, manifesto = ?, fee_status = ? WHERE id = ?',
            [
                $data['full_name'], $data['age'],
                $data['party'] !== '' ? $data['party'] : null,
                $data['email'] !== '' ? $data['email'] : null,
                $data['manifesto'], $data['fee_status'], $id,
            ]
        );
    }

    public static function delete(int $id): void
    {
        static::run('DELETE FROM candidates WHERE id = ?', [$id]);
    }

    public static function voteCount(int $id): int
    {
        return (int) static::run('SELECT COUNT(*) FROM votes WHERE candidate_id = ?', [$id])->fetchColumn();
    }

    /** True when this voter is already a candidate for any position in this election. */
    public static function userInElection(int $userId, int $electionId, int $exceptId = 0): bool
    {
        return (int) static::run(
            'SELECT COUNT(*) FROM candidates c JOIN positions p ON p.id = c.position_id
             WHERE c.user_id = ? AND p.election_id = ? AND c.id <> ?',
            [$userId, $electionId, $exceptId]
        )->fetchColumn() > 0;
    }

    /** The positions a voter has been put forward for. @return Candidate[] */
    public static function forUser(int $userId): array
    {
        return array_map(
            fn (array $row) => static::hydrate($row),
            static::run(
                'SELECT c.*, p.title AS position_title, p.min_candidate_age, p.election_id AS election_id, e.title AS election_title, ' . self::WITH_PHOTO_VERSION . '
                 FROM candidates c
                 JOIN positions p ON p.id = c.position_id
                 JOIN elections e ON e.id = p.election_id
                 WHERE c.user_id = ?
                 ORDER BY e.id DESC',
                [$userId]
            )->fetchAll()
        );
    }

    /** Use the voter's own registration photo as the candidate photo. */
    public static function copyPhotoFromUser(int $candidateId, int $userId): bool
    {
        $query = Database::connection()->prepare(
            'INSERT INTO candidate_photos (candidate_id, image, updated_at)
             SELECT ?, image, NOW() FROM photos WHERE user_id = ?
             ON DUPLICATE KEY UPDATE image = VALUES(image), updated_at = NOW()'
        );
        $query->execute([$candidateId, $userId]);
        return $query->rowCount() > 0;
    }

    public static function nameExistsInPosition(string $name, int $positionId, int $exceptId = 0): bool
    {
        return (int) static::run(
            'SELECT COUNT(*) FROM candidates WHERE position_id = ? AND LOWER(full_name) = LOWER(?) AND id <> ?',
            [$positionId, $name, $exceptId]
        )->fetchColumn() > 0;
    }

    /** The candidates for one position (used on the ballot). @return Candidate[] */
    public static function forPosition(int $positionId): array
    {
        return array_map(
            fn (array $row) => static::hydrate($row),
            static::run(
                'SELECT c.*, ' . self::WITH_PHOTO_VERSION . '
                 FROM candidates c
                 WHERE c.position_id = ?
                 ORDER BY c.full_name',
                [$positionId]
            )->fetchAll()
        );
    }

    /** Every candidate with their position and election, for the administrator. @return Candidate[] */
    public static function listWithDetails(): array
    {
        return array_map(
            fn (array $row) => static::hydrate($row),
            static::run(
                'SELECT c.*, p.title AS position_title, p.min_candidate_age, p.election_id AS election_id, e.title AS election_title, ' . self::WITH_PHOTO_VERSION . '
                 FROM candidates c
                 JOIN positions p ON p.id = c.position_id
                 JOIN elections e ON e.id = p.election_id
                 ORDER BY e.id DESC, p.id, c.full_name'
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

    public static function countForPosition(int $positionId): int
    {
        return (int) static::run('SELECT COUNT(*) FROM candidates WHERE position_id = ?', [$positionId])->fetchColumn();
    }

    public static function countAll(): int
    {
        return (int) static::run('SELECT COUNT(*) FROM candidates')->fetchColumn();
    }

    // ---- photo (stored in the database) ----

    public static function storePhoto(int $candidateId, string $jpeg): void
    {
        $query = Database::connection()->prepare(
            'INSERT INTO candidate_photos (candidate_id, image, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE image = VALUES(image), updated_at = NOW()'
        );
        $query->bindValue(1, $candidateId, \PDO::PARAM_INT);
        $query->bindValue(2, $jpeg, \PDO::PARAM_LOB);
        $query->execute();
    }

    public static function photoData(int $candidateId): ?string
    {
        $query = Database::connection()->prepare('SELECT image FROM candidate_photos WHERE candidate_id = ?');
        $query->execute([$candidateId]);
        $image = $query->fetchColumn();
        return $image === false ? null : (string) $image;
    }

    // ---- reading the candidate ----

    public function userId(): int
    {
        return (int) $this->get('user_id', 0);
    }

    public function displayName(): string
    {
        return (string) $this->get('full_name', '');
    }

    public function age(): int
    {
        return (int) $this->get('age', 0);
    }

    public function party(): string
    {
        return (string) $this->get('party', '');
    }

    public function email(): string
    {
        return (string) $this->get('email', '');
    }

    public function manifesto(): string
    {
        return (string) $this->get('manifesto', '');
    }

    public function positionId(): int
    {
        return (int) $this->get('position_id', 0);
    }

    public function feeAmount(): float
    {
        return (float) $this->get('fee_amount', 0);
    }

    public function feeStatus(): string
    {
        return (string) $this->get('fee_status', 'unpaid');
    }

    /** Changes whenever the photo changes, so browsers never show an old picture. 0 = no photo. */
    public function photoVersion(): int
    {
        return (int) $this->get('photo_version', 0);
    }

    public function hasPhoto(): bool
    {
        return $this->photoVersion() > 0;
    }

    public function photoUrl(): string
    {
        return url('/candidate-photo?id=' . $this->id() . '&v=' . $this->photoVersion());
    }

    // Only filled when loaded with find() or listWithDetails().

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
}
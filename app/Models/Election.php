<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * An election with four dates set by the administrator:
 *
 *   applications open -> applications close -> voting starts -> voting ends
 *
 * The phase follows the dates by itself. The only manual step is publishing the results
 * after voting has ended.
 */
final class Election extends Model
{
    protected static string $table = 'elections';

    public static function find(int $id): ?Election
    {
        $election = parent::find($id);
        return $election instanceof Election ? $election : null;
    }

    /** All dates must already be in database format (Y-m-d H:i:s). */
    public static function create(
        string $title,
        string $description,
        string $applicationsOpenAt,
        string $applicationsCloseAt,
        string $startsAt,
        string $endsAt,
        int $createdBy
    ): int {
        static::run(
            "INSERT INTO elections (title, description, applications_open_at, applications_close_at, starts_at, ends_at, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 'draft', ?)",
            [$title, $description === '' ? null : $description, $applicationsOpenAt, $applicationsCloseAt, $startsAt, $endsAt, $createdBy]
        );
        return (int) Database::connection()->lastInsertId();
    }

    /** @return Election[] newest first */
    public static function listAll(): array
    {
        return array_map(
            fn (array $row) => static::hydrate($row),
            static::run('SELECT * FROM elections ORDER BY id DESC')->fetchAll()
        );
    }

    /** @return Election[] newest first, only those that are in one of the given phases right now */
    public static function withPhase(string ...$phases): array
    {
        return array_values(array_filter(
            static::listAll(),
            fn (Election $election) => in_array($election->phase(), $phases, true)
        ));
    }

    public static function countAll(): int
    {
        return (int) static::run('SELECT COUNT(*) FROM elections')->fetchColumn();
    }

    // ---- reading the election's details ----

    public function title(): string
    {
        return (string) $this->get('title', '');
    }

    public function description(): string
    {
        return (string) $this->get('description', '');
    }

    /** Elections created before this feature have no application dates: applications were open until voting started. */
    public function applicationsOpenAt(): string
    {
        return (string) ($this->get('applications_open_at') ?? '2000-01-01 00:00:00');
    }

    public function applicationsCloseAt(): string
    {
        return (string) ($this->get('applications_close_at') ?? $this->startsAt());
    }

    public function startsAt(): string
    {
        return (string) $this->get('starts_at', '');
    }

    public function endsAt(): string
    {
        return (string) $this->get('ends_at', '');
    }

    /** Stored status. Only "results_published" matters, everything else follows the dates. */
    public function status(): string
    {
        return (string) $this->get('status', 'draft');
    }

    // ---- rules ----

    /**
     * upcoming -> applications_open -> waiting -> voting_open -> voting_closed -> results_published
     * Worked out from the current time (Nigerian time), so no one has to click anything to move on.
     */
    public function phase(): string
    {
        if ($this->status() === 'results_published') {
            return 'results_published';
        }

        $now = new \DateTimeImmutable('now');

        if ($now < new \DateTimeImmutable($this->applicationsOpenAt())) {
            return 'upcoming';
        }
        if ($now <= new \DateTimeImmutable($this->applicationsCloseAt())) {
            return 'applications_open';
        }
        if ($now < new \DateTimeImmutable($this->startsAt())) {
            return 'waiting';
        }
        if ($now <= new \DateTimeImmutable($this->endsAt())) {
            return 'voting_open';
        }
        return 'voting_closed';
    }

    public function isApplicationsOpen(): bool
    {
        return $this->phase() === 'applications_open';
    }

    public function isOpenForVoting(): bool
    {
        return $this->phase() === 'voting_open';
    }

    /** Positions can be added and candidates approved until voting starts. */
    public function canEditSetup(): bool
    {
        return in_array($this->phase(), ['upcoming', 'applications_open', 'waiting'], true);
    }

    public function canPublishResults(): bool
    {
        return $this->phase() === 'voting_closed';
    }

    // ---- admin shortcuts: move the election on now instead of waiting for the dates ----

    /** Stop taking candidate applications now. */
    public function closeApplications(): void
    {
        if (!$this->isApplicationsOpen()) {
            throw new \DomainException('Applications for this election are not open.');
        }
        static::run('UPDATE elections SET applications_close_at = ? WHERE id = ?', [self::justNow(), $this->id()]);
    }

    /** Open voting now (applications must already be closed). */
    public function startVoting(): void
    {
        if ($this->phase() !== 'waiting') {
            throw new \DomainException('Voting can only be started after applications have closed and before voting begins.');
        }
        static::run('UPDATE elections SET starts_at = ? WHERE id = ?', [self::justNow(), $this->id()]);
    }

    /** Close voting now, so the results can be published. */
    public function endVoting(): void
    {
        if (!$this->isOpenForVoting()) {
            throw new \DomainException('Voting is not open for this election.');
        }
        static::run('UPDATE elections SET ends_at = ? WHERE id = ?', [self::justNow(), $this->id()]);
    }

    /**
     * One second ago, in Nigerian time (set by PHP, not MySQL, because Aiven's clock is in UTC).
     * One second back so the new phase starts straight away.
     */
    private static function justNow(): string
    {
        return date('Y-m-d H:i:s', time() - 1);
    }

    /** The one manual step. Allowed only after voting has ended. */
    public function publishResults(): void
    {
        if (!$this->canPublishResults()) {
            throw new \DomainException('Results can be published only after voting has ended.');
        }

        $changed = static::run(
            "UPDATE elections SET status = 'results_published' WHERE id = ? AND status <> 'results_published'",
            [$this->id()]
        )->rowCount();

        if ($changed !== 1) {
            throw new \DomainException('The results of this election were already published.');
        }
    }
}
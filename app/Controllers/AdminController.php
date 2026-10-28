<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Validator;
use App\Interfaces\RoleInterface;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Election;
use App\Models\Position;
use App\Models\User;
use App\Models\Vote;
use App\Models\Voter;
use App\Services\Notifier;

/** Administrator pages: overview, voter approval, elections and positions, candidate review. */
final class AdminController extends Controller
{
    private const VOTER_FILTERS     = ['all', 'pending', 'approved', 'rejected', 'suspended'];
    private const CANDIDATE_FILTERS = ['pending', 'approved', 'rejected', 'all'];

    protected function requiredArea(): ?string
    {
        return RoleInterface::AREA_ADMIN;
    }

    // ------------------------------------------------------------------ overview

    public function index(): void
    {
        $stats = [
            'total'     => User::countVoters(),
            'pending'   => User::countVoters('pending'),
            'approved'  => User::countVoters('approved'),
            'rejected'  => User::countVoters('rejected'),
            'suspended' => User::countVoters('suspended'),
        ];

        $this->view('admin/index', [
            'stats'        => $stats,
            'totals'       => [
                'elections'           => Election::countAll(),
                'candidates'          => Candidate::countAll(),
                'votes'               => Vote::countAll(),
            ],
            'ageBands'     => User::ageBands(),
            'byState'      => User::breakdown('state'),
            'bySex'        => User::breakdown('sex'),
            'byDisability' => User::breakdown('disability_status'),
            'activity'     => AuditLog::recent(10),
        ], 'Admin dashboard');
    }

    // ------------------------------------------------------------------ voters

    public function voters(): void
    {
        $filter = (string) ($_GET['status'] ?? 'all');
        if (!in_array($filter, self::VOTER_FILTERS, true)) {
            $filter = 'all';
        }

        $this->view('admin/voters', [
            'filter'  => $filter,
            'filters' => self::VOTER_FILTERS,
            'voters'  => User::voters($filter === 'all' ? null : $filter),
        ], 'Voters');
    }

    public function updateVoterStatus(): void
    {
        $id     = (int) ($_POST['user_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        $return = (string) ($_POST['return'] ?? 'all');

        if (!in_array($status, ['approved', 'rejected', 'suspended'], true)) {
            throw new HttpException(400, 'That is not a valid status.');
        }

        $voter = User::find($id);
        if (!$voter instanceof Voter) {
            throw new HttpException(404, 'We could not find that voter.');
        }

        $adminId = Auth::id();

        // The change and its activity record are saved together, or not at all.
        Database::transaction(function () use ($id, $status, $voter, $adminId): void {
            User::updateStatus($id, $status);
            AuditLog::record(
                $adminId,
                'voter_' . $status,
                sprintf('%s (%s) set to %s', $voter->fullName(), $voter->voterNumber(), $status)
            );
        });

        Logger::info(sprintf('Admin #%d set voter #%d to %s', $adminId, $id, $status));
        Notifier::voterStatus($voter, $status);
        Session::flash('success', $voter->fullName() . ' is now ' . $status . '.');

        $this->redirect('/admin/voters?status=' . (in_array($return, self::VOTER_FILTERS, true) ? $return : 'all'));
    }

    // ------------------------------------------------------------------ elections and positions

    public function elections(): void
    {
        $items = [];

        foreach (Election::listAll() as $election) {
            $positions = [];
            foreach (Position::forElection($election->id()) as $position) {
                $positions[] = [
                    'position'   => $position,
                    'candidates' => Candidate::countForPosition($position->id()),
                ];
            }
            $items[] = ['election' => $election, 'positions' => $positions];
        }

        $this->view('admin/elections', ['items' => $items], 'Elections');
    }

    public function createElection(): void
    {
        $data = [
            'title'                 => $this->input('title'),
            'description'           => $this->input('description'),
            'applications_open_at'  => $this->input('applications_open_at'),
            'applications_close_at' => $this->input('applications_close_at'),
            'starts_at'             => $this->input('starts_at'),
            'ends_at'               => $this->input('ends_at'),
        ];

        $validator = Validator::make($data, [
            'title'                 => ['required', 'min:3', 'max:200'],
            'description'           => ['max:1000'],
            'applications_open_at'  => ['required', 'datetime'],
            'applications_close_at' => ['required', 'datetime', 'after:applications_open_at'],
            'starts_at'             => ['required', 'datetime', 'after:applications_close_at'],
            'ends_at'               => ['required', 'datetime', 'after:starts_at'],
        ], [
            'applications_open_at'  => 'Applications open',
            'applications_close_at' => 'Applications close',
            'starts_at'             => 'Voting starts',
            'ends_at'               => 'Voting ends',
        ]);

        if ($validator->fails()) {
            $this->backWithErrors('/admin/elections', $validator->errors(), $data);
        }

        $adminId = Auth::id();

        $electionId = Database::transaction(function () use ($data, $adminId): int {
            $id = Election::create(
                $data['title'],
                $data['description'],
                self::toDatabaseTime($data['applications_open_at']),
                self::toDatabaseTime($data['applications_close_at']),
                self::toDatabaseTime($data['starts_at']),
                self::toDatabaseTime($data['ends_at']),
                $adminId
            );
            AuditLog::record($adminId, 'election_created', sprintf('Election #%d: %s', $id, $data['title']));
            return $id;
        });

        $election = Election::find($electionId);
        if ($election !== null) {
            Notifier::electionCreated($election);
        }

        Session::flash('success', 'Election created. Now add its positions (for example President) so voters can apply.');
        $this->redirect('/admin/elections');
    }

    public function addPosition(): void
    {
        $election = Election::find((int) ($_POST['election_id'] ?? 0));
        if ($election === null) {
            throw new HttpException(404, 'We could not find that election.');
        }

        $data = [
            'title'   => $this->input('title'),
            'min_age' => $this->input('min_age'),
            'fee'     => $this->input('fee', '0'),
        ];

        $validator = Validator::make($data, [
            'title'   => ['required', 'min:2', 'max:150'],
            'min_age' => ['required', 'int', 'between:18,100'],
        ], ['min_age' => 'Minimum age']);

        if (!$validator->fails() && (!is_numeric($data['fee']) || (float) $data['fee'] < 0 || (float) $data['fee'] > 10000000)) {
            $this->failToElections('Application fee must be a number from 0 upwards (0 means free).');
        }

        if ($validator->fails()) {
            $messages = array_values($validator->errors());
            $this->failToElections($messages[0]);
        }

        if (!$election->canEditSetup()) {
            $this->failToElections('Positions can only be added before voting starts.');
        }

        $adminId = Auth::id();

        try {
            Database::transaction(function () use ($election, $data, $adminId): void {
                Position::create($election->id(), $data['title'], (int) $data['min_age'], round((float) $data['fee'], 2));
                AuditLog::record($adminId, 'position_added', sprintf('%s, minimum age %d, fee %s, in "%s"', $data['title'], (int) $data['min_age'], naira((float) $data['fee']), $election->title()));
            });
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {   // positions are unique inside an election
                $this->failToElections('This election already has a position with that title.');
            }
            throw $e;
        }

        Session::flash('success', 'Position added.');
        $this->redirect('/admin/elections');
    }

    /** Stop candidate applications now, instead of waiting for the closing date. */
    public function closeApplications(): void
    {
        $this->moveElection('close_applications', 'Applications are now closed. You can start voting when you are ready.');
    }

    /** Open voting now, instead of waiting for the start date. */
    public function startVoting(): void
    {
        $this->moveElection('start_voting', 'Voting is now open.');
    }

    /** End voting now, so the results can be published. */
    public function endVoting(): void
    {
        $this->moveElection('end_voting', 'Voting has ended. You can now publish the results.');
    }

    /** Shared by the three buttons above. */
    private function moveElection(string $action, string $successMessage): never
    {
        $election = Election::find((int) ($_POST['election_id'] ?? 0));
        if ($election === null) {
            throw new HttpException(404, 'We could not find that election.');
        }

        $adminId = Auth::id();

        try {
            Database::transaction(function () use ($election, $action, $adminId): void {
                match ($action) {
                    'close_applications' => $election->closeApplications(),
                    'start_voting'       => $election->startVoting(),
                    'end_voting'         => $election->endVoting(),
                };
                AuditLog::record($adminId, 'election_' . $action, sprintf('Election #%d: %s', $election->id(), $election->title()));
            });
        } catch (\DomainException $e) {
            $this->failToElections($e->getMessage());
        }

        Logger::info(sprintf('Admin #%d did %s on election #%d', $adminId, $action, $election->id()));
        Session::flash('success', '"' . $election->title() . '": ' . $successMessage);
        $this->redirect('/admin/elections');
    }

    /** The one manual step: publish the results once voting has ended. */
    public function publishResults(): void
    {
        $election = Election::find((int) ($_POST['election_id'] ?? 0));
        if ($election === null) {
            throw new HttpException(404, 'We could not find that election.');
        }

        $adminId = Auth::id();

        try {
            Database::transaction(function () use ($election, $adminId): void {
                $election->publishResults();
                AuditLog::record($adminId, 'election_results_published', sprintf('Election #%d: %s', $election->id(), $election->title()));
            });
        } catch (\DomainException $e) {
            $this->failToElections($e->getMessage());
        }

        Logger::info(sprintf('Admin #%d published the results of election #%d', $adminId, $election->id()));
        Notifier::resultsPublished($election);
        Session::flash('success', 'Results for "' . $election->title() . '" are now visible to every voter.');
        $this->redirect('/admin/elections');
    }

    // ------------------------------------------------------------------ candidate applications

    public function candidates(): void
    {
        $filter = (string) ($_GET['status'] ?? 'pending');
        if (!in_array($filter, self::CANDIDATE_FILTERS, true)) {
            $filter = 'pending';
        }

        $this->view('admin/candidates', [
            'filter'       => $filter,
            'filters'      => self::CANDIDATE_FILTERS,
            'applications' => CandidateApplication::listForReview($filter === 'all' ? null : $filter),
            'feeStatuses'  => CandidateApplication::FEE_STATUSES,
        ], 'Candidate applications');
    }

    /** Approve or reject an application. This does not touch the fee. */
    public function reviewApplication(): void
    {
        $decision = (string) ($_POST['decision'] ?? '');
        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw new HttpException(400, 'That is not a valid decision.');
        }

        $application = CandidateApplication::findWithDetails((int) ($_POST['application_id'] ?? 0));
        if ($application === null) {
            throw new HttpException(404, 'We could not find that application.');
        }

        $back = $this->candidateReturnPath();

        $election = $application->election();
        if ($decision === 'approve' && ($election === null || !$election->canEditSetup())) {
            Session::flash('error', 'Candidates can only be approved before voting starts.');
            $this->redirect($back);
        }

        $adminId = Auth::id();

        try {
            Database::transaction(function () use ($application, $decision, $adminId): void {
                if ($decision === 'approve') {
                    $application->approve($adminId);
                } else {
                    $application->reject($adminId);
                }
                AuditLog::record(
                    $adminId,
                    'candidate_' . $decision . 'd',
                    sprintf('%s for %s in "%s"', $application->displayName(), $application->positionTitle(), $application->electionTitle())
                );
            });
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect($back);
        }

        Notifier::applicationReviewed($application, $decision);
        Session::flash('success', $application->displayName() . ($decision === 'approve' ? ' is now on the ballot.' : ' was rejected.'));
        $this->redirect($back);
    }

    /** Record the fee for an application (demonstration only, no real payment). */
    public function updateFee(): void
    {
        $feeStatus = (string) ($_POST['fee_status'] ?? '');
        if (!in_array($feeStatus, CandidateApplication::FEE_STATUSES, true)) {
            throw new HttpException(400, 'That is not a valid fee status.');
        }

        $application = CandidateApplication::findWithDetails((int) ($_POST['application_id'] ?? 0));
        if ($application === null) {
            throw new HttpException(404, 'We could not find that application.');
        }

        $adminId  = Auth::id();
        $previous = $application->feeStatus();

        Database::transaction(function () use ($application, $feeStatus, $adminId, $previous): void {
            $application->setFeeStatus($feeStatus);
            AuditLog::record(
                $adminId,
                'candidate_fee_updated',
                sprintf('%s: fee changed from %s to %s', $application->displayName(), $previous, $feeStatus)
            );
        });

        Session::flash('success', 'Fee for ' . $application->displayName() . ' is now: ' . label($feeStatus) . '.');
        $this->redirect($this->candidateReturnPath());
    }

    // ------------------------------------------------------------------ small helpers

    /** Show an error on the elections page and stop. */
    private function failToElections(string $message): never
    {
        Session::flash('error', $message);
        $this->redirect('/admin/elections');
    }

    private function candidateReturnPath(): string
    {
        $return = (string) ($_POST['return'] ?? 'pending');
        return '/admin/candidates?status=' . (in_array($return, self::CANDIDATE_FILTERS, true) ? $return : 'pending');
    }

    /** 2026-10-18T14:30 (from the form) -> 2026-10-18 14:30:00 (for the database) */
    private static function toDatabaseTime(string $formValue): string
    {
        return str_replace('T', ' ', $formValue) . ':00';
    }
}
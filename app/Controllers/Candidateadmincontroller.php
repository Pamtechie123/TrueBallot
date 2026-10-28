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
use App\Models\Election;
use App\Models\Position;
use App\Models\User;
use App\Services\Notifier;
use App\Services\PhotoStorage;

/** Administrator: add, change and remove candidates. Only the administrator can put someone on a ballot. */
final class CandidateAdminController extends Controller
{
    protected function requiredArea(): ?string
    {
        return RoleInterface::AREA_ADMIN;
    }

    // ------------------------------------------------------------------ candidates

    public function index(): void
    {
        $this->view('admin/candidates', ['candidates' => Candidate::listWithDetails()], 'Candidates');
    }

    public function create(): void
    {
        $this->view('admin/candidate_form', [
            'candidate' => null,
            'groups'    => $this->editablePositions(),
            'voters'    => User::voters('approved'),
        ], 'Add candidate');
    }

    public function store(): void
    {
        $data = $this->candidateInput();
        $form = '/admin/candidates/new';

        $errors   = $this->validate($data);
        $position = Position::find((int) $data['position_id']);
        $election = $position !== null ? Election::find($position->electionId()) : null;
        $voter    = User::find((int) $data['user_id']);

        if ($errors === []) {
            if ($position === null || $election === null || !$election->canEditSetup()) {
                $errors['position_id'] = 'Candidates can only be added before voting starts.';
            } elseif ($voter === null || !($voter instanceof \App\Models\Voter) || !$voter->isApproved()) {
                $errors['user_id'] = 'Choose an approved voter.';
            } elseif ($voter->age() < $position->minCandidateAge()) {
                $errors['user_id'] = sprintf('%s is %d. The minimum age for %s is %d.', $voter->fullName(), $voter->age(), $position->title(), $position->minCandidateAge());
            } elseif (Candidate::userInElection($voter->id(), $election->id())) {
                $errors['user_id'] = $voter->fullName() . ' is already a candidate in this election. A voter can stand for only one position per election.';
            }
        }

        // A new photo is optional here: without one, the voter's own registration photo is used.
        $photo = null;
        if (PhotoStorage::wasUploaded($_FILES['photo'] ?? null)) {
            try {
                $photo = PhotoStorage::prepare($_FILES['photo']);
            } catch (\DomainException $e) {
                $errors['photo'] = $e->getMessage();
            }
        } elseif ($voter !== null && !PhotoStorage::exists($voter->id())) {
            $errors['photo'] = 'This voter has no photo. Please upload one.';
        }

        if ($errors !== []) {
            $this->backWithErrors($form, $errors, $data);
        }

        $adminId = Auth::id();
        $id = Database::transaction(function () use ($data, $position, $voter, $photo, $adminId): int {
            $id = Candidate::create([
                'position_id' => $position->id(),
                'user_id'     => $voter->id(),
                'full_name'   => $voter->fullName(),
                'age'         => $voter->age(),
                'party'       => $data['party'],
                'email'       => $voter->email(),
                'manifesto'   => $data['manifesto'],
                'fee_amount'  => $position->feeAmount(),
                'fee_status'  => $data['fee_status'],
            ]);
            if ($photo !== null) {
                Candidate::storePhoto($id, $photo);
            } else {
                Candidate::copyPhotoFromUser($id, $voter->id());
            }
            AuditLog::record($adminId, 'candidate_added', sprintf('%s for %s', $voter->fullName(), $position->title()));
            return $id;
        });

        Logger::info(sprintf('Admin #%d added candidate #%d', $adminId, $id));
        Notifier::candidateAdded($voter->email(), $voter->fullName(), $position->title(), $election->title());

        Session::flash('success', $voter->fullName() . ' is now a candidate for ' . $position->title() . '.');
        $this->redirect('/admin/candidates');
    }

    public function edit(): void
    {
        $candidate = $this->findCandidate((int) ($_GET['id'] ?? 0));
        $this->view('admin/candidate_form', [
            'candidate' => $candidate,
            'groups'    => [],
            'voters'    => [],
        ], 'Edit candidate');
    }

    public function update(): void
    {
        $candidate = $this->findCandidate((int) ($_POST['candidate_id'] ?? 0));
        $form      = '/admin/candidates/edit?id=' . $candidate->id();

        if (!$candidate->election()?->canEditSetup()) {
            Session::flash('error', 'Candidates cannot be changed once voting has started.');
            $this->redirect('/admin/candidates');
        }

        $data = $this->candidateInput();
        $data['position_id'] = (string) $candidate->positionId();   // the position cannot be changed here

        $data['user_id'] = (string) $candidate->userId();
        $errors = $this->validate($data);

        $photo = null;
        if (PhotoStorage::wasUploaded($_FILES['photo'] ?? null)) {
            try {
                $photo = PhotoStorage::prepare($_FILES['photo']);
            } catch (\DomainException $e) {
                $errors['photo'] = $e->getMessage();
            }
        } elseif (!$candidate->hasPhoto()) {
            $errors['photo'] = 'Please add the candidate\'s photo. It is required.';
        }

        if ($errors !== []) {
            $this->backWithErrors($form, $errors, $data);
        }

        $adminId = Auth::id();
        Database::transaction(function () use ($candidate, $data, $photo, $adminId): void {
            Candidate::updateDetails($candidate->id(), [
                'full_name' => $candidate->displayName(), 'age' => $candidate->age(), 'party' => $data['party'],
                'email' => $candidate->email(), 'manifesto' => $data['manifesto'], 'fee_status' => $data['fee_status'],
            ]);
            if ($photo !== null) {
                Candidate::storePhoto($candidate->id(), $photo);
            }
            AuditLog::record($adminId, 'candidate_updated', sprintf('%s for %s', $candidate->displayName(), $candidate->positionTitle()));
        });

        Session::flash('success', 'Saved the changes to ' . $candidate->displayName() . '.');
        $this->redirect('/admin/candidates');
    }

    public function delete(): void
    {
        $candidate = $this->findCandidate((int) ($_POST['candidate_id'] ?? 0));

        if (!$candidate->election()?->canEditSetup()) {
            Session::flash('error', 'Candidates cannot be removed once voting has started.');
            $this->redirect('/admin/candidates');
        }
        if (Candidate::voteCount($candidate->id()) > 0) {
            Session::flash('error', 'This candidate already has votes and cannot be removed.');
            $this->redirect('/admin/candidates');
        }

        $adminId = Auth::id();
        Database::transaction(function () use ($candidate, $adminId): void {
            Candidate::delete($candidate->id());
            AuditLog::record($adminId, 'candidate_removed', sprintf('%s for %s', $candidate->displayName(), $candidate->positionTitle()));
        });

        Session::flash('success', $candidate->displayName() . ' was removed from the ballot.');
        $this->redirect('/admin/candidates');
    }

    // ------------------------------------------------------------------ helpers

    private function findCandidate(int $id): Candidate
    {
        $candidate = Candidate::find($id);
        if ($candidate === null) {
            throw new HttpException(404, 'We could not find that candidate.');
        }
        return $candidate;
    }

    private function candidateInput(): array
    {
        return [
            'position_id' => $this->input('position_id'),
            'user_id'     => $this->input('user_id'),
            'party'       => $this->input('party'),
            'manifesto'   => $this->input('manifesto'),
            'fee_status'  => $this->input('fee_status', 'unpaid'),
        ];
    }

    private function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'position_id' => ['required', 'int'],
            'user_id'     => ['required', 'int'],
            'party'       => ['max:100'],
            'manifesto'   => ['required', 'min:20', 'max:2000'],
            'fee_status'  => ['required', 'in:' . implode(',', Candidate::FEE_STATUSES)],
        ], ['position_id' => 'Position', 'user_id' => 'Voter', 'fee_status' => 'Fee status']);

        return $validator->fails() ? $validator->errors() : [];
    }

    /** Elections that can still be changed, each with its positions. @return array<int,array{election:Election,positions:Position[]}> */
    private function editablePositions(): array
    {
        $groups = [];
        foreach (Election::listAll() as $election) {
            if ($election->canEditSetup()) {
                $positions = Position::forElection($election->id());
                if ($positions !== []) {
                    $groups[] = ['election' => $election, 'positions' => $positions];
                }
            }
        }
        return $groups;
    }
}
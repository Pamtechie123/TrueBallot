<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Validator;
use App\Interfaces\RoleInterface;
use App\Models\CandidateApplication;
use App\Models\Election;
use App\Models\Position;
use App\Services\Notifier;
use App\Services\PhotoStorage;

/** Approved voters apply to contest a position and follow their applications. */
final class CandidateController extends Controller
{
    protected function requiredArea(): ?string
    {
        return RoleInterface::AREA_VOTING;   // approved voters only
    }

    public function showApply(): void
    {
        $this->view('candidates/apply', [
            'positions' => Position::openForApplications(),
            'user'      => Auth::user(),
        ], 'Apply as a candidate');
    }

    public function apply(): void
    {
        $user = Auth::user();

        $data = [
            'position_id'  => $this->input('position_id'),
            'display_name' => $this->input('display_name'),
            'manifesto'    => $this->input('manifesto'),
        ];

        $validator = Validator::make($data, [
            'position_id'  => ['required', 'int'],
            'display_name' => ['required', 'min:3', 'max:150'],
            'manifesto'    => ['required', 'min:20', 'max:2000'],
        ], [
            'position_id'  => 'Position',
            'display_name' => 'Display name',
        ]);

        // Candidates need a photo for the ballot. Required if they don't have one yet, otherwise it replaces the old one.
        $errors = $validator->fails() ? $validator->errors() : [];
        $photo  = null;
        if (PhotoStorage::wasUploaded($_FILES['photo'] ?? null)) {
            try {
                $photo = PhotoStorage::prepare($_FILES['photo']);
            } catch (\DomainException $e) {
                $errors['photo'] = $e->getMessage();
            }
        } elseif (!PhotoStorage::exists($user->id())) {
            $errors['photo'] = 'Please add a photo. It will be shown next to your name on the ballot.';
        }

        if ($errors !== []) {
            $this->backWithErrors('/candidates/apply', $errors, $data);
        }

        $position = Position::find((int) $data['position_id']);
        $election = $position !== null ? Election::find($position->electionId()) : null;

        // The server checks everything again. The form cannot be trusted.
        if ($position === null || $election === null || !$election->isApplicationsOpen()) {
            $this->backWithErrors('/candidates/apply', ['position_id' => 'Please choose a position that is accepting applications.'], $data);
        }

        if ($user->age() < $position->minCandidateAge()) {
            $this->backWithErrors('/candidates/apply', [
                'position_id' => sprintf('You must be at least %d years old to apply for %s.', $position->minCandidateAge(), $position->title()),
            ], $data);
        }

        $duplicate = ['position_id' => 'You have already applied for this position.'];

        if (CandidateApplication::existsFor($user->id(), $position->id())) {
            $this->backWithErrors('/candidates/apply', $duplicate, $data);
        }

        try {
            CandidateApplication::create($user->id(), $position->id(), $data['display_name'], $data['manifesto'], $position->feeAmount());
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {   // two clicks at the same moment
                $this->backWithErrors('/candidates/apply', $duplicate, $data);
            }
            throw $e;
        }

        if ($photo !== null) {
            PhotoStorage::store($user->id(), $photo);
        }

        Logger::info(sprintf('Candidate application: user #%d for position #%d', $user->id(), $position->id()));
        Notifier::applicationReceived($user, $position, $election);
        Session::flash('success', 'Your application was submitted. An administrator will review it.');
        $this->redirect('/candidates/applications');
    }

    public function myApplications(): void
    {
        $this->view('candidates/applications', [
            'applications' => CandidateApplication::forUser(Auth::id()),
        ], 'My applications');
    }
}

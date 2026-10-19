<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Session;
use App\Interfaces\RoleInterface;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Models\Vote;
use App\Services\Notifier;

/** Shows the ballot for every election that is open for voting, and records the votes. */
final class VotingController extends Controller
{
    protected function requiredArea(): ?string
    {
        return RoleInterface::AREA_VOTING;   // approved voters only
    }

    public function index(): void
    {
        $voterId = Auth::id();
        $ballots = [];

        foreach (Election::withPhase('voting_open') as $election) {
            $alreadyChosen = Vote::choicesOf($voterId, $election->id());
            $positions     = [];
            $canStillVote  = false;

            foreach (Position::forElection($election->id()) as $position) {
                $candidates = Candidate::forPosition($position->id());
                $votedId    = $alreadyChosen[$position->id()] ?? null;
                $votedName  = null;

                foreach ($candidates as $candidate) {
                    if ($candidate->id() === $votedId) {
                        $votedName = $candidate->displayName();
                    }
                }
                if ($votedId === null && $candidates !== []) {
                    $canStillVote = true;
                }

                $positions[] = [
                    'position'   => $position,
                    'candidates' => $candidates,
                    'votedFor'   => $votedName,
                ];
            }

            $ballots[] = ['election' => $election, 'positions' => $positions, 'canStillVote' => $canStillVote];
        }

        $this->view('voting/ballot', ['ballots' => $ballots], 'Vote');
    }

    public function cast(): void
    {
        $voterId  = Auth::id();
        $election = Election::find((int) ($_POST['election_id'] ?? 0));
        if ($election === null) {
            throw new HttpException(404, 'We could not find that election.');
        }

        // The form sends choice[positionId] = candidateId. Keep only whole numbers.
        $choices = [];
        foreach ((array) ($_POST['choice'] ?? []) as $positionId => $candidateId) {
            if (ctype_digit((string) $positionId) && ctype_digit((string) $candidateId)) {
                $choices[(int) $positionId] = (int) $candidateId;
            }
        }

        try {
            $saved = Database::transaction(fn () => Vote::cast($voterId, $election, $choices));
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/vote');
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {   // the database refused a second vote
                Session::flash('error', 'You have already voted for one of those positions. Nothing was saved.');
                $this->redirect('/vote');
            }
            throw $e;
        }

        Logger::info(sprintf('Votes recorded: voter #%d, election #%d, %d position(s)', $voterId, $election->id(), $saved));
        Notifier::voteRecorded(Auth::user(), $election, $saved);
        Session::flash('success', 'Your vote has been recorded. Thank you for voting.');
        $this->redirect('/vote');
    }
}

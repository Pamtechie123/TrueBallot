<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Interfaces\RoleInterface;
use App\Models\Election;
use App\Models\User;
use App\Models\Vote;

/** Election results. Voters see them only after publishing. Administrators can follow them any time. */
final class ResultController extends Controller
{
    protected function requiredArea(): ?string
    {
        return RoleInterface::AREA_ANY;
    }

    public function index(): void
    {
        $isAdmin = Auth::user()->canAccess(RoleInterface::AREA_ADMIN);

        $elections = $isAdmin
            ? Election::withPhase('voting_open', 'voting_closed', 'results_published')
            : Election::withPhase('results_published');

        $results = array_map(fn (Election $election) => [
            'election'  => $election,
            'positions' => Vote::tally($election->id()),   // always counted from the stored votes
            'voters'    => Vote::votersIn($election->id()),
        ], $elections);

        $this->view('results/index', [
            'results'        => $results,
            'approvedVoters' => User::countVoters('approved'),
            'isAdmin'        => $isAdmin,
        ], 'Results');
    }
}

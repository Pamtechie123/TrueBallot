<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Interfaces\RoleInterface;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;

/** The voter's own page: account status, digital voter card and shortcuts. */
final class DashboardController extends Controller
{
    protected function requiredArea(): ?string
    {
        return RoleInterface::AREA_ANY;
    }

    public function index(): void
    {
        $user = Auth::user();

        // An administrator who opens this page is sent to their own dashboard.
        if ($user->homePath() !== '/dashboard') {
            $this->redirect($user->homePath());
        }

        $elections = [];
        $counts    = ['voting_open' => 0, 'applications_open' => 0, 'results_published' => 0];

        foreach (Election::listAll() as $election) {
            $phase = $election->phase();
            if (isset($counts[$phase])) {
                $counts[$phase]++;
            }
            $elections[] = ['election' => $election, 'phase' => $phase, 'positions' => Position::forElection($election->id())];
        }

        $this->view('dashboard/index', [
            'user'         => $user,
            'candidacies'  => Candidate::forUser($user->id()),
            'elections'    => $elections,
            'openCount'    => $counts['voting_open'],
            'applyCount'   => $counts['applications_open'],
            'resultsCount' => $counts['results_published'],
        ], 'My dashboard');
    }
}
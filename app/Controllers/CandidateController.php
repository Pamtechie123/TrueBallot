<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Models\Candidate;

/**
 * Candidates are added only by the administrator (see CandidateAdminController).
 * This controller only sends a candidate's photo, to logged-in users, for the ballot and results.
 */
final class CandidateController extends Controller
{
    protected function requiredArea(): ?string
    {
        return null;   // photo() checks for a logged-in user itself
    }

    public function photo(): void
    {
        if (!Auth::check()) {
            throw new HttpException(404, 'There is no photo to show.');
        }

        $image = Candidate::photoData((int) ($_GET['id'] ?? 0));
        if ($image === null) {
            throw new HttpException(404, 'There is no photo to show.');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . strlen($image));
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        echo $image;
        exit;
    }
}
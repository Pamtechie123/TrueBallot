<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Session;
use App\Interfaces\RoleInterface;
use App\Models\Candidate;
use App\Services\PhotoStorage;

/** User photos: show them, and let voters change or remove their own from the dashboard. */
final class ProfileController extends Controller
{
    protected function requiredArea(): ?string
    {
        return RoleInterface::AREA_ANY;   // each action adds its own check
    }

    /**
     * Sends a picture. Everyone can see their own photo and the photos of candidates on a ballot.
     * Administrators can see any user's photo. Use ?user=ID for someone else's photo.
     */
    public function photo(): void
    {
        $user   = Auth::user();
        $target = isset($_GET['user']) ? (int) $_GET['user'] : $user->id();

        $allowed = $target === $user->id()
            || $user->canAccess(RoleInterface::AREA_ADMIN)
            || Candidate::isCandidate($target);

        $image = $allowed ? PhotoStorage::data($target) : null;
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

    public function uploadPhoto(): void
    {
        Auth::authorise(RoleInterface::AREA_VOTING);   // approved voters only (the card is shown to them)

        try {
            PhotoStorage::save(Auth::id(), $_FILES['photo'] ?? null);
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/dashboard');
        }

        Logger::info('Photo saved for user #' . Auth::id());
        Session::flash('success', 'Your photo was saved and is now on your voter card.');
        $this->redirect('/dashboard');
    }

    public function deletePhoto(): void
    {
        Auth::authorise(RoleInterface::AREA_VOTING);

        PhotoStorage::delete(Auth::id());

        Logger::info('Photo removed for user #' . Auth::id());
        Session::flash('success', 'Your photo was removed.');
        $this->redirect('/dashboard');
    }
}
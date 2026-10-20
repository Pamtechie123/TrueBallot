<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Validator;
use App\Models\User;
use App\Services\Notifier;
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

        // ------------------------------------------------------------------ edit details and password

    public function edit(): void
    {
        $this->view('profile/edit', ['user' => Auth::user()], 'My profile');
    }

    public function update(): void
    {
        $user = Auth::user();

        $data = [
            'full_name'         => $this->input('full_name'),
            'age'               => $this->input('age'),
            'state'             => $this->input('state'),
            'sex'               => $this->input('sex'),
            'disability_status' => $this->input('disability_status'),
        ];

        $validator = Validator::make($data, [
            'full_name'         => ['required', 'min:3', 'max:150'],
            'age'               => ['required', 'int', 'between:18,120'],
            'state'             => ['required', 'max:100'],
            'sex'               => ['required', 'in:male,female,other,prefer_not_to_say'],
            'disability_status' => ['required', 'in:yes,no,prefer_not_to_say'],
        ], ['full_name' => 'Full name', 'disability_status' => 'Disability status']);

        if ($validator->fails()) {
            $this->backWithErrors('/profile', $validator->errors(), $data);
        }

        $changed = $data['full_name'] !== $user->fullName()
            || (int) $data['age'] !== $user->age()
            || $data['state'] !== $user->state()
            || $data['sex'] !== $user->sex()
            || $data['disability_status'] !== $user->disabilityStatus();

        if (!$changed) {
            Session::flash('success', 'Nothing was changed.');
            $this->redirect('/profile');
        }

        User::updateDetails($user->id(), $data);

        // A voter whose details changed must be approved again. Administrators do not.
        $needsReview = !$user->canAccess(RoleInterface::AREA_ADMIN) && $user->isApproved();
        if ($needsReview) {
            User::updateStatus($user->id(), 'pending');
        }

        Logger::info('Details updated by user #' . $user->id() . ($needsReview ? ' (back to pending)' : ''));

        if ($needsReview) {
            $fresh = User::find($user->id());
            if ($fresh !== null) {
                Notifier::detailsNeedReview($fresh);
            }
            Session::flash('success', 'Your details were saved. An administrator must approve your account again before you can vote or apply.');
            $this->redirect('/dashboard');
        }

        Session::flash('success', 'Your details were saved.');
        $this->redirect('/profile');
    }

    public function changePassword(): void
    {
        $user = Auth::user();

        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        $validator = Validator::make(
            ['current_password' => $current, 'new_password' => $new, 'new_password_confirm' => $confirm],
            [
                'current_password'     => ['required'],
                'new_password'         => ['required', 'min:8', 'max:72'],
                'new_password_confirm' => ['required', 'same:new_password'],
            ],
            ['current_password' => 'Current password', 'new_password' => 'New password', 'new_password_confirm' => 'Password confirmation']
        );
        if ($validator->fails()) {
            $this->backWithErrors('/profile', $validator->errors());
        }

        if (!password_verify($current, $user->passwordHash())) {
            $this->backWithErrors('/profile', ['current_password' => 'That is not your current password.']);
        }

        User::updatePassword($user->id(), $new);
        Logger::info('Password changed from the profile page, user #' . $user->id());
        Notifier::passwordChanged($user);

        Session::flash('success', 'Your password was changed.');
        $this->redirect('/profile');
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
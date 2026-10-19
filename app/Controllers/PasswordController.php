<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Validator;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\Notifier;

/** "Forgot your password?": ask for a link by email, then choose a new password. */
final class PasswordController extends Controller
{
    protected function requiredArea(): ?string
    {
        return null;   // for guests
    }

    public function showForgot(): void
    {
        $this->redirectIfLoggedIn();
        $this->view('auth/forgot', [], 'Forgot password');
    }

    public function sendLink(): void
    {
        $this->redirectIfLoggedIn();

        $email = strtolower($this->input('email'));

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);
        if ($validator->fails()) {
            $this->backWithErrors('/forgot-password', $validator->errors(), ['email' => $email]);
        }

        $user = User::findByEmail($email);

        if ($user !== null) {
            if (PasswordReset::recentRequests($user->id()) >= PasswordReset::MAX_REQUESTS_PER_HOUR) {
                Logger::warning('Too many password reset requests for user #' . $user->id());
            } else {
                $token = PasswordReset::createFor($user->id());
                Notifier::passwordReset($user, Notifier::link('/reset-password?token=' . $token), PasswordReset::LIFETIME_MINUTES);
                Logger::info('Password reset link requested for user #' . $user->id());
            }
        }

        // The same answer whether or not the email exists, so nobody can use this page to find out who is registered.
        Session::flash('success', 'If an account with that email exists, we have sent a link to reset the password. Check your inbox and your spam folder.');
        $this->redirect('/login');
    }

    public function showReset(): void
    {
        $this->redirectIfLoggedIn();

        $token = (string) ($_GET['token'] ?? '');
        if (PasswordReset::findValid($token) === null) {
            $this->invalidLink();
        }

        $this->view('auth/reset', ['token' => $token], 'Choose a new password');
    }

    public function reset(): void
    {
        $this->redirectIfLoggedIn();

        $token    = (string) ($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        $reset = PasswordReset::findValid($token);
        if ($reset === null) {
            $this->invalidLink();
        }

        $validator = Validator::make(
            ['password' => $password, 'password_confirm' => $confirm],
            ['password' => ['required', 'min:8', 'max:72'], 'password_confirm' => ['required', 'same:password']],
            ['password' => 'New password', 'password_confirm' => 'Password confirmation']
        );
        if ($validator->fails()) {
            $this->backWithErrors('/reset-password?token=' . $token, $validator->errors());
        }

        $userId = $reset->userId();

        // Spend the link and change the password together, or neither.
        $changed = Database::transaction(function () use ($reset, $userId, $password): bool {
            if (!$reset->consume()) {
                return false;
            }
            User::updatePassword($userId, $password);
            return true;
        });

        if (!$changed) {
            $this->invalidLink();
        }

        $user = User::find($userId);
        if ($user !== null) {
            Notifier::passwordChanged($user);
        }

        Logger::info('Password changed through a reset link, user #' . $userId);
        Session::flash('success', 'Your password has been changed. You can now log in.');
        $this->redirect('/login');
    }

    private function invalidLink(): never
    {
        Session::flash('error', 'That reset link is not valid or has expired. Please ask for a new one.');
        $this->redirect('/forgot-password');
    }

    private function redirectIfLoggedIn(): void
    {
        $user = Auth::user();
        if ($user !== null && !$user->isBlocked()) {
            $this->redirect($user->homePath());
        }
    }
}
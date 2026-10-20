<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Validator;
use App\Models\User;
use App\Services\Notifier;
use App\Services\PhotoStorage;

/** Registration, login and logout. */
final class AuthController extends Controller
{
    protected function requiredArea(): ?string
    {
        return null;   // these pages are for guests
    }

    public function showRegister(): void
    {
        $this->redirectIfLoggedIn();
        $this->view('auth/register', [], 'Register');
    }

    public function register(): void
    {
        $this->redirectIfLoggedIn();

        // Passwords are never trimmed or sent back to the form.
        $data = [
            'full_name'         => $this->input('full_name'),
            'email'             => strtolower($this->input('email')),
            'password'          => (string) ($_POST['password'] ?? ''),
            'password_confirm'  => (string) ($_POST['password_confirm'] ?? ''),
            'age'               => $this->input('age'),
            'state'             => $this->input('state'),
            'sex'               => $this->input('sex'),
            'disability_status' => $this->input('disability_status'),
        ];
        $old = array_diff_key($data, ['password' => 1, 'password_confirm' => 1]);

        $validator = Validator::make($data, [
            'full_name'         => ['required', 'min:3', 'max:150'],
            'email'             => ['required', 'email', 'max:150'],
            'password'          => ['required', 'min:8', 'max:72'],
            'password_confirm'  => ['required', 'same:password'],
            'age'               => ['required', 'int', 'between:18,120'],
            'state'             => ['required', 'max:100'],
            'sex'               => ['required', 'in:male,female,other,prefer_not_to_say'],
            'disability_status' => ['required', 'in:yes,no,prefer_not_to_say'],
        ], [
            'full_name'        => 'Full name',
            'password_confirm' => 'Password confirmation',
            'disability_status'=> 'Disability status',
        ]);

        // The photo is compulsory and must be a real picture.
        $errors = $validator->fails() ? $validator->errors() : [];
        $photo  = null;
        if (!PhotoStorage::wasUploaded($_FILES['photo'] ?? null)) {
            $errors['photo'] = 'Please add your photo. It is required.';
        } else {
            try {
                $photo = PhotoStorage::prepare($_FILES['photo']);
            } catch (\DomainException $e) {
                $errors['photo'] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            $this->backWithErrors('/register', $errors, $old);
        }

        if (User::emailExists($data['email'])) {
            $this->backWithErrors('/register', ['email' => 'An account with this email already exists.'], $old);
        }

        try {
            $id = User::create($data);
        } catch (\PDOException $e) {
            // Two people registering the same email at the same moment.
            if (($e->errorInfo[1] ?? 0) === 1062) {
                $this->backWithErrors('/register', ['email' => 'An account with this email already exists.'], $old);
            }
            throw $e;
        }

        if ($photo !== null) {
            PhotoStorage::store($id, $photo);
        }

        Logger::info('New voter registration, user #' . $id);

        $newUser = User::find($id);
        if ($newUser !== null) {
            Notifier::registrationReceived($newUser);
        }

        Session::flash('success', 'Registration received. An administrator will review your account. You can log in to check its status.');
        $this->redirect('/login');
    }

    public function showLogin(): void
    {
        $this->redirectIfLoggedIn();
        $this->view('auth/login', [], 'Log in');
    }

    public function login(): void
    {
        $this->redirectIfLoggedIn();

        $email    = strtolower($this->input('email'));
        $password = (string) ($_POST['password'] ?? '');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required']]
        );
        if ($validator->fails()) {
            $this->backWithErrors('/login', $validator->errors(), ['email' => $email]);
        }

        $user = Auth::attempt($email, $password);

        if ($user === null) {
            Logger::warning('Failed login attempt from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown address'));
            Session::flash('error', 'The email or password is not correct.');
            Session::flash('old', ['email' => $email]);
            $this->redirect('/login');
        }

        if ($user->isBlocked()) {
            Session::flash('error', 'Your account has been ' . $user->status() . '. Please contact the administrator.');
            $this->redirect('/login');
        }

        Auth::login($user);
        Notifier::loginAlert($user);
        $this->redirect($user->homePath());
    }

    public function logout(): void
    {
        Auth::logout();
        Session::flash('success', 'You have been logged out.');
        $this->redirect('/login');
    }

    private function redirectIfLoggedIn(): void
    {
        $user = Auth::user();
        if ($user !== null && !$user->isBlocked()) {
            $this->redirect($user->homePath());
        }
    }
}
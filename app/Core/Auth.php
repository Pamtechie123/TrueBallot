<?php
declare(strict_types=1);

namespace App\Core;

use App\Interfaces\RoleInterface;
use App\Models\User;

/**
 * Login, logout, the current user, form-forgery (CSRF) tokens and page access checks.
 */
final class Auth
{
    private static ?User $user = null;
    private static bool $loaded = false;

    /** The logged-in user as a Voter or Admin object, or null for a guest. */
    public static function user(): ?User
    {
        if (!self::$loaded) {
            $id = Session::get('user_id');
            self::$user   = $id !== null ? User::find((int) $id) : null;
            self::$loaded = true;
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user()?->id();
    }

    /** Check the email and password. Returns the user, or null when they do not match. */
    public static function attempt(string $email, string $password): ?User
    {
        $user = User::findByEmail($email);
        if ($user !== null && password_verify($password, $user->passwordHash())) {
            return $user;
        }
        return null;
    }

    public static function login(User $user): void
    {
        Session::regenerate();
        Session::put('user_id', $user->id());
        self::$user   = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        Session::flush();
        Session::regenerate();
        self::$user   = null;
        self::$loaded = true;
    }

    /**
     * Runs before every controller. Guests go to the login page, blocked accounts are signed out,
     * and a user who is not allowed in an area receives a 403 page.
     */
    public static function authorise(?string $area): void
    {
        if ($area === null) {
            return;
        }

        $user = self::user();

        if ($user === null) {
            Session::flash('error', 'Please log in to continue.');
            redirect('/login');
        }

        if ($user->isBlocked()) {
            $status = $user->status();
            self::logout();
            Session::flash('error', 'Your account has been ' . $status . '. Please contact the administrator.');
            redirect('/login');
        }

        if (!$user->canAccess($area)) {   // polymorphism: Voter and Admin answer differently
            $message = $area === RoleInterface::AREA_VOTING
                ? 'Your account must be approved by an administrator before you can do this.'
                : 'You do not have permission to open this page.';
            throw new HttpException(403, $message);
        }
    }

    public static function csrfToken(): string
    {
        if (Session::get('csrf') === null) {
            Session::put('csrf', bin2hex(random_bytes(32)));
        }
        return (string) Session::get('csrf');
    }

    public static function verifyCsrf(?string $token): bool
    {
        return $token !== null && hash_equals((string) Session::get('csrf', ''), $token);
    }
}

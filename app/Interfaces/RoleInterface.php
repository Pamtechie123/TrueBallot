<?php
declare(strict_types=1);

namespace App\Interfaces;

/**
 * What every kind of user (Voter, Admin, and any role added later) must be able to do.
 */
interface RoleInterface
{
    public const AREA_ANY    = 'any';     // any logged-in user
    public const AREA_VOTER  = 'voter';   // voter dashboard
    public const AREA_VOTING = 'voting';  // voting and candidate applications (approved voters only)
    public const AREA_ADMIN  = 'admin';   // administration pages

    public function roleName(): string;

    /** Where this user goes after logging in. */
    public function homePath(): string;

    /** Is this user allowed to open the given area? */
    public function canAccess(string $area): bool;
}

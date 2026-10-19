<?php
declare(strict_types=1);

namespace App\Models;

/** A normal user. Can vote and apply as a candidate once an administrator has approved the account. */
final class Voter extends User
{
    public function roleName(): string
    {
        return 'Voter';
    }

    public function homePath(): string
    {
        return '/dashboard';
    }

    public function canAccess(string $area): bool
    {
        return match ($area) {
            self::AREA_ANY, self::AREA_VOTER => true,
            self::AREA_VOTING                => $this->isApproved(),   // pending voters must wait
            default                          => false,
        };
    }
}

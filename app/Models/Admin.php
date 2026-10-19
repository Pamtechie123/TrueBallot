<?php
declare(strict_types=1);

namespace App\Models;

/** An administrator. Can manage the system but cannot vote. */
final class Admin extends User
{
    public function roleName(): string
    {
        return 'Administrator';
    }

    public function homePath(): string
    {
        return '/admin';
    }

    public function canAccess(string $area): bool
    {
        return match ($area) {
            self::AREA_ANY, self::AREA_ADMIN => true,
            default                          => false,
        };
    }
}

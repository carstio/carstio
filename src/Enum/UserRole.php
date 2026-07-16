<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The roles that admins can assign through the User Management UI.
 *
 * The Symfony role hierarchy (security.yaml) gives ROLE_ADMIN access to
 * everything ROLE_MECHANIC can do, so a user is intentionally given exactly
 * one of these roles - never both.
 */
enum UserRole: string
{
    case Admin = 'ROLE_ADMIN';
    case Mechanic = 'ROLE_MECHANIC';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}

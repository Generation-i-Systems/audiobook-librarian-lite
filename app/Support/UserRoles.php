<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The role taxonomy for the lite server.
 *
 * Lite has exactly three roles: unverified, user, and admin. An unverified
 * account may authenticate but may not reach any sync endpoint.
 */
final class UserRoles
{
    public const UNVERIFIED = 'unverified';
    public const USER = 'user';
    public const ADMIN = 'admin';

    /**
     * Roles offered when creating or editing a user in the admin UI,
     * as role => human label.
     *
     * @return array<string, string>
     */
    public static function selectable(): array
    {
        return [
            self::UNVERIFIED  => 'Unverified (no sync access)',
            self::USER        => 'User',
            self::ADMIN       => 'Admin',
        ];
    }

    /**
     * Every role the API and admin forms will accept.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::selectable());
    }

    /**
     * Roles an admin may assign when verifying a pending account.
     *
     * @return list<string>
     */
    public static function assignableOnVerify(): array
    {
        return [self::USER];
    }

    /**
     * A verified account is a standard user or an administrator.
     */
    public static function isVerified(?string $role): bool
    {
        return in_array($role, [self::USER, self::ADMIN], true);
    }
}

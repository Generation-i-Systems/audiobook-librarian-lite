<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The role taxonomy for the lite server.
 *
 * Lite hosts no book library and no LibriVox catalog, so the full server's
 * source-mode roles (library-user / librivox-user / hybrid-user) carry no
 * meaning here. They are still *accepted* so accounts created against a full
 * server keep working after a migration, but they are not offered in the admin
 * UI — new accounts get `user`.
 *
 * The only distinction lite actually enforces is verified vs. unverified:
 * an unverified account may authenticate but may not reach any sync endpoint.
 */
final class UserRoles
{
    public const UNVERIFIED = 'unverified';
    public const USER = 'user';
    public const ADMIN = 'admin';
    public const SUPER_ADMIN = 'super-admin';

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
            self::SUPER_ADMIN => 'Super Admin',
        ];
    }

    /**
     * Roles inherited from the full server. Accepted on input and preserved on
     * existing accounts, but never offered as a new choice.
     *
     * @return list<string>
     */
    public static function legacy(): array
    {
        return ['trial-user', 'full-user', 'library-user', 'librivox-user', 'hybrid-user'];
    }

    /**
     * Every role the API and admin forms will accept.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(array_keys(self::selectable()), self::legacy());
    }

    /**
     * Roles an admin may assign when verifying a pending account.
     *
     * @return list<string>
     */
    public static function assignableOnVerify(): array
    {
        return array_merge(
            array_values(array_diff(array_keys(self::selectable()), [self::UNVERIFIED])),
            self::legacy()
        );
    }

    /**
     * A verified account — anything that is not explicitly pending verification.
     *
     * Lite deliberately allows unknown roles through rather than enumerating
     * them: the only access decision it makes is "has an admin approved this
     * account yet?", and a legacy role from a full server must never lock a
     * real user out of their own listening history.
     */
    public static function isVerified(?string $role): bool
    {
        return $role !== null && $role !== '' && $role !== self::UNVERIFIED;
    }
}

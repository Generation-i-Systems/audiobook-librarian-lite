<?php

declare(strict_types=1);

namespace Tests\Api\Feature;

use App\Models\User;
use App\Support\UserRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Access rules for the authenticated API surface.
 *
 * On the full server this middleware also picked a book source (local vs.
 * LibriVox) from the caller's role and the request host. Lite hosts no book
 * catalog and has no source modes, so the only rule left is verified vs.
 * unverified — the host and X-Library-Profile cases that used to live here were
 * asserting behaviour that no longer exists.
 *
 * @see \Tests\Feature\Api\SyncAccessRoleTest for the same rule across the sync surface.
 */
class RequireLibraryRoleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: array<string, string>}
     */
    private function makeUser(string $role): array
    {
        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('test')->plainTextToken;

        return [$user, ['Authorization' => 'Bearer ' . $token]];
    }

    /**
     * @param array<string, string> $headers
     */
    private function getMe(array $headers, string $host = 'localhost'): \Illuminate\Testing\TestResponse
    {
        return $this
            ->withHeaders($headers)
            ->getJson("http://{$host}/api/v1/user");
    }

    public function testEveryVerifiedRoleIsAllowed(): void
    {
        foreach (array_diff(UserRoles::all(), [UserRoles::UNVERIFIED]) as $role) {
            [, $headers] = $this->makeUser($role);

            $this->assertSame(
                200,
                $this->getMe($headers)->status(),
                "Role '{$role}' should reach the authenticated API."
            );
        }
    }

    /**
     * `user` is the plain player role — the only one that means anything on a
     * server with no library. Enumerating the full server's library roles here
     * used to lock it out of every endpoint.
     */
    public function testPlainPlayerRoleIsAllowed(): void
    {
        [, $headers] = $this->makeUser(UserRoles::USER);
        $this->getMe($headers)->assertStatus(200);
    }

    public function testUnverifiedUserIsBlocked(): void
    {
        [, $headers] = $this->makeUser(UserRoles::UNVERIFIED);
        $this->getMe($headers)->assertStatus(403);
    }

    public function testUserWithNoRoleIsBlocked(): void
    {
        [$user, $headers] = $this->makeUser(UserRoles::USER);
        $user->forceFill(['role' => ''])->save();

        $this->getMe($headers)->assertStatus(403);
    }

    public function testUnauthenticatedRequestIsBlocked(): void
    {
        $this->getJson('http://localhost/api/v1/user')->assertStatus(401);
    }

    /**
     * Auth must not depend on which hostname the client connected through — a
     * self-hosted lite server is commonly reached by several names.
     */
    public function testAccessDoesNotDependOnTheRequestHost(): void
    {
        [, $headers] = $this->makeUser(UserRoles::USER);

        foreach (['localhost', 'books.example.test', 'lite.example.test'] as $host) {
            $this->assertSame(200, $this->getMe($headers, $host)->status(), "Blocked on host {$host}.");
        }
    }
}

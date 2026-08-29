<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\UserRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lite's whole purpose is syncing listening data, so both verified roles have
 * to reach the authenticated surface.
 */
class SyncAccessRoleTest extends TestCase
{
    use RefreshDatabase;

    /** Endpoints spanning the authenticated surface, as method => [uri, body, headers]. */
    private function authenticatedEndpoints(): array
    {
        return [
            'badges' => ['GET', '/api/v1/badges/user', [], []],
            'statistics' => ['GET', '/api/v1/statistics/overview', [], []],
            'reading stats' => ['GET', '/api/v1/reading-stats/user', [], []],
            'goals' => ['GET', '/api/v1/goals/listening', [], []],
            'event sync' => ['POST', '/api/v1/sync/events', ['events' => [], 'lastSyncTimestamp' => 0], ['X-Device-ID' => 'device-1']],
            'position sync' => ['GET', '/api/v1/sync/positions', [], ['X-Device-ID' => 'device-1']],
        ];
    }

    private function hit(string $method, string $uri, array $body, array $headers)
    {
        return $method === 'GET'
            ? $this->getJson($uri, $headers)
            : $this->postJson($uri, $body, $headers);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function verifiedRoleProvider(): array
    {
        $roles = array_diff(UserRoles::all(), [UserRoles::UNVERIFIED]);

        return array_combine(
            $roles,
            array_map(static fn (string $role): array => [$role], $roles)
        );
    }

    #[Test]
    #[DataProvider('verifiedRoleProvider')]
    public function a_verified_account_reaches_the_whole_authenticated_surface(string $role): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => $role]));

        foreach ($this->authenticatedEndpoints() as $label => [$method, $uri, $body, $headers]) {
            $response = $this->hit($method, $uri, $body, $headers);

            $this->assertNotSame(
                403,
                $response->status(),
                "Role '{$role}' was forbidden from {$label} ({$method} {$uri})."
            );
        }
    }

    #[Test]
    public function an_unverified_account_is_refused_everywhere(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRoles::UNVERIFIED]));

        foreach ($this->authenticatedEndpoints() as $label => [$method, $uri, $body, $headers]) {
            $response = $this->hit($method, $uri, $body, $headers);

            $this->assertSame(
                403,
                $response->status(),
                "An unverified account reached {$label} ({$method} {$uri})."
            );
        }
    }

    #[Test]
    public function a_standard_user_can_sync_and_read_back_its_own_events(): void
    {
        $user = User::factory()->create(['role' => UserRoles::USER]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/sync/events', [
            'lastSyncTimestamp' => 0,
            'events' => [[
                'id' => 'event-player-1',
                'bookTitle' => 'Dune',
                'bookAuthor' => 'Frank Herbert',
                'eventType' => 'BOOK_START',
                'timestampMs' => 1700000000000,
                'positionMs' => 0,
                'deviceId' => 'device-1',
                'timezone' => 'UTC',
                'createdAt' => 1700000000000,
            ]],
        ], ['X-Device-ID' => 'device-1']);

        $response->assertOk();
        $response->assertJsonPath('received', 1);

        $this->getJson('/api/v1/sync/events/book?title=Dune&author=' . urlencode('Frank Herbert'), ['X-Device-ID' => 'device-1'])
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('events.0.title', 'Dune')
            ->assertJsonPath('events.0.author', 'Frank Herbert');
    }
}

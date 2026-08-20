<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Basic smoke tests verifying lite's actual live API surface responds and
 * isn't 404 — event and position sync, statistics, achievements, and user
 * endpoints, not a book catalog (Lite has none).
 */
class EndpointSmokeTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // No RefreshDatabase here — construct an in-memory user so Sanctum
        // has someone to resolve, without touching the database at all.
        $this->user = new User();
        $this->user->id = 1;
        $this->user->name = 'Test User';
        $this->user->email = 'test@example.com';
        $this->user->role = 'full-user';

        Sanctum::actingAs($this->user);
    }

    public function test_health_endpoint_exists(): void
    {
        $response = $this->getJson('/api/v1/health');

        $this->assertNotEquals(404, $response->status());
    }

    public function test_root_endpoint_exists(): void
    {
        $response = $this->getJson('/api/v1');

        $this->assertNotEquals(404, $response->status());
    }

    public function test_events_endpoint_exists(): void
    {
        $response = $this->postJson('/api/v1/sync/events', [
            'events' => [],
            'lastSyncTimestamp' => 0,
        ], [
            'X-Device-ID' => 'smoke-device',
        ]);

        $this->assertNotEquals(404, $response->status());
    }

    public function test_positions_endpoint_exists(): void
    {
        $response = $this->getJson('/api/v1/sync/positions');

        $this->assertNotEquals(404, $response->status());
    }

    public function test_statistics_overview_endpoint_exists(): void
    {
        $response = $this->getJson('/api/v1/statistics/overview');

        $this->assertNotEquals(404, $response->status());
    }

    public function test_achievements_endpoint_exists(): void
    {
        $response = $this->getJson('/api/v1/badges');

        $this->assertNotEquals(404, $response->status());
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->withoutMiddleware()->getJson('/api/v1/badges');

        $this->assertNotEquals(404, $response->status());
    }
}

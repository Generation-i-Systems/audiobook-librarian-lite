<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StatisticsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private const TITLE = 'Project Hail Mary';
    private const AUTHOR = 'Andy Weir';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role'              => 'admin',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($this->user);
    }

    public function testReportSessionStoresAListeningSession(): void
    {
        $response = $this->postJson('/api/v1/statistics/report', [
            'title'              => self::TITLE,
            'author'             => self::AUTHOR,
            'session_start'      => '2026-08-01T10:00:00Z',
            'session_end'        => '2026-08-01T10:30:00Z',
            'start_position_ms'  => 0,
            'end_position_ms'    => 1_800_000,
            'playback_speed'     => 1.5,
            'pauses_count'       => 2,
            'actual_duration_ms' => 1_800_000,
        ], $this->headers());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('listening_statistics', [
            'title'  => self::TITLE,
            'author' => self::AUTHOR,
        ]);
    }

    public function testReportSessionAcceptsAFullServerClientPayload(): void
    {
        // Full-server clients send book_id alongside title/author, and spell an event's type
        // `event_type` rather than `type`.
        $response = $this->postJson('/api/v1/statistics/report', [
            'book_id'           => 29,
            'title'             => self::TITLE,
            'author'            => self::AUTHOR,
            'session_start'     => '2026-08-01T10:00:00Z',
            'session_end'       => '2026-08-01T10:30:00Z',
            'start_position_ms' => 0,
            'end_position_ms'   => 1_800_000,
            'events'            => [
                [
                    'id'          => 'evt-1',
                    'book_id'     => 29,
                    'timestamp'   => 1_785_000_000_000,
                    'event_type'  => 'PAUSE',
                    'position_ms' => 900_000,
                ],
            ],
        ], $this->headers());

        $response->assertStatus(201);
        $this->assertDatabaseHas('listening_statistics', ['title' => self::TITLE]);
    }

    public function testReportSessionRejectsASessionWithNoBookIdentity(): void
    {
        $response = $this->postJson('/api/v1/statistics/report', [
            'book_id'           => 29,
            'session_start'     => '2026-08-01T10:00:00Z',
            'session_end'       => '2026-08-01T10:30:00Z',
            'start_position_ms' => 0,
            'end_position_ms'   => 1_800_000,
        ], $this->headers());

        $response->assertStatus(422);
    }

    public function testTimelineStatsRespond(): void
    {
        $response = $this->getJson('/api/v1/statistics/timeline?group_by=day', $this->headers());

        $response->assertStatus(200);
    }

    public function testDayTimelineResponds(): void
    {
        $response = $this->getJson(
            '/api/v1/statistics/timeline/day?date=2026-08-01&timezone=America%2FDenver',
            $this->headers()
        );

        $response->assertStatus(200);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'X-Device-ID'   => 'device-1',
            'X-Device-Name' => 'Test Device',
        ];
    }
}

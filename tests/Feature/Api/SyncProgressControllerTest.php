<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\BookProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncProgressControllerTest extends TestCase
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

    public function testClientProgressIsStoredWhenTheServerHasNone(): void
    {
        $response = $this->postJson('/api/v1/sync/progress', [
            'clientTimestamp' => '2026-08-01T10:00:00Z',
            'progress'        => [
                [
                    'bookId'             => 29,
                    'title'              => self::TITLE,
                    'author'             => self::AUTHOR,
                    'positionMs'         => 3_600_000,
                    'progressPercentage' => 42.5,
                    'lastUpdated'        => '2026-08-01T10:00:00Z',
                ],
            ],
        ], $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('updates', []);

        $this->assertDatabaseHas('book_progress', [
            'user_id'                  => $this->user->id,
            'title'                    => self::TITLE,
            'author'                   => self::AUTHOR,
            'device_id'                => 'device-1',
            'current_position_seconds' => 3600,
        ]);
    }

    public function testNewerServerProgressComesBackAsAnUpdate(): void
    {
        BookProgress::create([
            'user_id'                  => $this->user->id,
            'title'                    => self::TITLE,
            'author'                   => self::AUTHOR,
            'device_id'                => 'other-device',
            'current_position_seconds' => 7200,
            'progress_percentage'      => 80.0,
            'last_listened_at'         => '2026-08-02T10:00:00Z',
            'updated_at'               => '2026-08-02T10:00:00Z',
        ]);

        $response = $this->postJson('/api/v1/sync/progress', [
            'clientTimestamp' => '2026-08-01T10:00:00Z',
            'progress'        => [
                [
                    'bookId'             => 29,
                    'title'              => self::TITLE,
                    'author'             => self::AUTHOR,
                    'positionMs'         => 3_600_000,
                    'progressPercentage' => 42.5,
                    'lastUpdated'        => '2026-08-01T10:00:00Z',
                ],
            ],
        ], $this->headers());

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'updates');
        $response->assertJsonPath('updates.0.positionMs', 7_200_000);
        $response->assertJsonPath('updates.0.source', 'server');
        // Lite answers with the identity it keys on, and echoes the client's own id back.
        $response->assertJsonPath('updates.0.title', self::TITLE);
        $response->assertJsonPath('updates.0.author', self::AUTHOR);
        $response->assertJsonPath('updates.0.bookId', 29);
    }

    public function testAnItemWithNoTitleIsSkippedWithoutFailingTheBatch(): void
    {
        $response = $this->postJson('/api/v1/sync/progress', [
            'clientTimestamp' => '2026-08-01T10:00:00Z',
            'progress'        => [
                [
                    'bookId'             => 29,
                    'positionMs'         => 3_600_000,
                    'progressPercentage' => 42.5,
                    'lastUpdated'        => '2026-08-01T10:00:00Z',
                ],
                [
                    'title'              => self::TITLE,
                    'author'             => self::AUTHOR,
                    'positionMs'         => 1_800_000,
                    'progressPercentage' => 20.0,
                    'lastUpdated'        => '2026-08-01T10:00:00Z',
                ],
            ],
        ], $this->headers());

        $response->assertStatus(200);
        $this->assertSame(1, BookProgress::where('user_id', $this->user->id)->count());
        $this->assertDatabaseHas('book_progress', ['title' => self::TITLE]);
    }

    /**
     * A full-server client keys its own record by bookId. Lite never looks anything
     * up by it, but it must come back untouched or the client cannot match the
     * update to the book it asked about.
     */
    public function testAFullServerClientGetsItsBookIdEchoedBackUnchanged(): void
    {
        BookProgress::create([
            'user_id'                  => $this->user->id,
            'title'                    => self::TITLE,
            'author'                   => self::AUTHOR,
            'device_id'                => 'other-device',
            'current_position_seconds' => 3600,
            'progress_percentage'      => 90.0,
            'last_listened_at'         => now(),
        ]);

        $response = $this->postJson('/api/v1/sync/progress', [
            'clientTimestamp' => '2026-08-01T10:00:00Z',
            'progress'        => [
                [
                    'bookId'             => 29,
                    'title'              => self::TITLE,
                    'author'             => self::AUTHOR,
                    'positionMs'         => 600_000,
                    'progressPercentage' => 10.0,
                    'lastUpdated'        => '2020-01-01T00:00:00Z',
                ],
            ],
        ], $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('updates.0.bookId', 29);
    }

    public function testItRequiresAuthentication(): void
    {
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/sync/progress', [
            'clientTimestamp' => '2026-08-01T10:00:00Z',
            'progress'        => [],
        ], $this->headers())->assertStatus(401);
    }

    public function testEmptyProgressListIsAccepted(): void
    {
        $response = $this->postJson('/api/v1/sync/progress', [
            'clientTimestamp' => '2026-08-01T10:00:00Z',
            'progress'        => [],
        ], $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('updates', []);
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

<?php

namespace Tests\Api\Feature;

use App\Models\BookProgress;
use App\Models\ListeningStatistic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

/**
 * Only getOverview, getDailyStatsOpenApi, and getReadingHistoryStats are
 * actually routed in routes/api.php. StatisticsController has many more
 * methods (session recording, weekly/trends/top-books/dashboard/timeline),
 * but none of them are wired to a route, so they're unreachable — tests for
 * them were removed rather than fixed. See the `todo` file for the full
 * list, in case some of these are meant to be wired up later.
 */
class StatisticsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user without running the problematic seeders
        $this->user = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create a Sanctum token for API authentication
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        // Use Sanctum::actingAs for API tests
        Sanctum::actingAs($this->user);
    }

    /**
     * Lite has no book library — a "book" for these tests is identified by
     * title/author, not a persisted model.
     */
    protected function fakeBookTitle(): string
    {
        return 'Book ' . random_int(100000, 999999);
    }

    public function test_overview_aggregates_listening_stats_across_multiple_devices_for_authenticated_user()
    {
        ListeningStatistic::create([
            'user_id' => $this->user->id,
            'title' => $this->fakeBookTitle(),
            'author' => 'Test Author',
            'device_id' => 'device-a',
            'seconds_listened' => 600,
            'session_type' => 'listening',
            'listening_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ListeningStatistic::create([
            'user_id' => $this->user->id,
            'title' => $this->fakeBookTitle(),
            'author' => 'Test Author',
            'device_id' => 'device-b',
            'seconds_listened' => 900,
            'session_type' => 'listening',
            'listening_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Device-ID' => 'device-a',
        ])->getJson('/api/v1/statistics/overview?period=all_time');

        $response->assertOk()
            ->assertJsonPath('total_listening_time_ms', (600 + 900) * 1000)
            ->assertJsonPath('books_started', 2)
            ->assertJsonPath('listening_minutes.day', 25)
            ->assertJsonPath('listening_minutes.week', 25)
            ->assertJsonPath('listening_minutes.month', 25);
    }

    public function test_overview_uses_completed_progress_for_books_finished_instead_of_completed_sessions(): void
    {
        $title = $this->fakeBookTitle();
        $author = 'Test Author';

        ListeningStatistic::create([
            'user_id' => $this->user->id,
            'title' => $title,
            'author' => $author,
            'device_id' => 'device-a',
            'seconds_listened' => 1200,
            'session_type' => 'listening',
            'listening_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        BookProgress::create([
            'user_id' => $this->user->id,
            'title' => $title,
            'author' => $author,
            'device_id' => 'device-a',
            'current_position_seconds' => 7200,
            'total_duration_seconds' => 7200,
            'progress_percentage' => 100,
            'completed' => true,
            'completed_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Device-ID' => 'device-a',
        ])->getJson('/api/v1/statistics/overview?period=all_time');

        $response->assertOk()
            ->assertJsonPath('books_started', 1)
            ->assertJsonPath('books_finished', 1);
    }

    public function test_get_reading_history_stats()
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user, 'api');

        \App\Models\UserBookStatus::create([
            'user_id' => $user->id,
            'title' => $this->fakeBookTitle(),
            'author' => 'Test Author',
            'status' => 'completed',
            'order' => 0,
            'finished_at' => now()->startOfMonth()->subMonth(),
        ]);

        \App\Models\UserBookStatus::create([
            'user_id' => $user->id,
            'title' => $this->fakeBookTitle(),
            'author' => 'Test Author',
            'status' => 'completed',
            'order' => 0,
            'finished_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/statistics/reading-history', ['X-Acting-As-Test' => '1']);

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => [
                    'period',
                    'count',
                ],
            ]);
    }

    public function test_reading_history_includes_completed_progress_without_user_book_status(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user, 'api');

        BookProgress::create([
            'user_id' => $user->id,
            'title' => $this->fakeBookTitle(),
            'author' => 'Test Author',
            'device_id' => 'test-device',
            'current_position_seconds' => 3600,
            'total_duration_seconds' => 3600,
            'progress_percentage' => 100,
            'completed' => true,
            'completed_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/statistics/reading-history', ['X-Acting-As-Test' => '1']);

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.count', 1);
    }
}

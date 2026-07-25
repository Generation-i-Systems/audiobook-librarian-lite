<?php

namespace Tests\Feature\Api;

use App\Models\Badge;
use App\Models\BookProgress;
use App\Models\ClientEvent;
use App\Models\Device;
use App\Models\ListeningEvent;
use App\Models\ListeningGoal;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBookStatus;
use App\Models\UserRecommendation;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Only /api/v1/sync/positions is a routed, live endpoint among what this file
 * exercises; /statistics/report, /statistics/sessions, and /analytics/event
 * are unrouted, so tests that hit those endpoints directly were removed.
 * BadgeService::evaluateUserBadges() itself is live (called from
 * EventController::sync and PositionSyncController::store), so tests that
 * call it directly remain.
 */
class BadgeSyncIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected string $deviceId = 'test-device-001';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->user = User::factory()->create([
            'role'              => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;

        Sanctum::actingAs($this->user);

        Device::create([
            'device_id'    => $this->deviceId,
            'user_id'      => $this->user->id,
            'name'         => 'Test Device',
            'platform'     => 'android',
            'sync_enabled' => true,
        ]);
    }

    protected function authHeaders(): array
    {
        return [
            'X-Device-ID'   => $this->deviceId,
            'X-Device-Name' => 'Test Device',
        ];
    }

    /**
     * Create a SESSION_END listening_events row - the event-sourced data BadgeService's
     * listening-based criteria (session_count, speed_variety, monthly_goal_streak, etc.) read
     * from. Badge evaluation no longer reads listening_statistics, which no current client
     * writes to.
     */
    protected function createSessionEndEvent(
        ?string $title = null,
        ?string $author = null,
        int $secondsListened = 1800,
        float $playbackSpeed = 1.0,
        ?string $listeningDate = null,
    ): ListeningEvent {
        $listeningDate ??= now()->toDateString();
        $timestampMs = \Carbon\Carbon::parse($listeningDate)->setTime(12, 0)->getTimestampMs();

        return ListeningEvent::create([
            'id'           => (string) Str::uuid(),
            'user_id'      => $this->user->id,
            'title'        => $title ?? 'Book ' . random_int(100000, 999999),
            'author'       => $author ?? 'Test Author',
            'event_type'   => 'SESSION_END',
            'timestamp_ms' => $timestampMs,
            'position_ms'  => 0,
            'metadata'     => [
                'sessionDurationMs'  => $secondsListened * 1000,
                'adjustedDurationMs' => $secondsListened * 1000,
                'playbackSpeed'      => $playbackSpeed,
            ],
            'device_id'    => $this->deviceId,
            'timezone'     => 'UTC',
            'sync_status'  => 'SYNCED',
            'created_at'   => $timestampMs,
            'synced_at'    => $timestampMs,
        ]);
    }

    protected function createTestBadge(array $criteria, string $category = 'listening'): Badge
    {
        return Badge::create([
            'key'           => 'test_' . Str::random(6),
            'name'          => 'Test Badge ' . Str::random(4),
            'description'   => 'A test badge',
            'icon'          => 'test_icon.png',
            'image_url'     => null,
            'category'      => $category,
            'tier'          => 'bronze',
            'points'        => 10,
            'criteria'      => $criteria,
            'is_active'     => true,
            'is_repeatable' => false,
            'sort_order'    => 1,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function position_sync_evaluates_badges_and_returns_them(): void
    {
        $title = 'Test Book';
        $author = 'Test Author';

        // Create a badge with criteria that should be met
        $this->createTestBadge(['session_count' => 1]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/sync/positions', [
                'client_timestamp' => now()->toIso8601String(),
                'positions'        => [
                    [
                        'title'                => $title,
                        'author'               => $author,
                        'position_ms'          => 900000,
                        'progress_percentage'  => 50.0,
                        'current_chapter'      => 3,
                        'current_chapter_name' => 'Chapter 3',
                        'is_finished'          => false,
                        'updated_at'           => now()->toIso8601String(),
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonStructure(['server_timestamp', 'accepted', 'conflicts']);

        // Verify position was synced
        $this->assertEquals(1, $response->json('accepted'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function badge_award_creates_message_for_authenticated_user(): void
    {
        Cache::flush();

        // Create a badge
        $badge = $this->createTestBadge(['session_count' => 1]);

        // Create listening data so criteria is met
        $this->createSessionEndEvent();

        // Evaluate badges directly via service
        $badgeService = app(BadgeService::class);
        $newBadges    = $badgeService->evaluateUserBadges((string) $this->user->id, $this->deviceId);

        $this->assertNotEmpty($newBadges, 'Badge should be awarded');

        // A message should have been created for the user
        $message = Message::where('recipient_id', $this->user->id)
            ->where('type', 'badge_earned')
            ->first();

        $this->assertNotNull($message, 'A badge_earned message should be created');
        $this->assertStringContainsString($badge->name, $message->content);
        $this->assertEquals($badge->id, $message->payload['badge_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function device_variety_returns_actual_device_count(): void
    {
        // Add another device
        Device::create([
            'device_id'    => 'second-device',
            'user_id'      => $this->user->id,
            'name'         => 'Second Device',
            'platform'     => 'ios',
            'sync_enabled' => true,
        ]);

        $badgeService = app(BadgeService::class);
        $reflection   = new \ReflectionMethod($badgeService, 'getDeviceVariety');
        $reflection->setAccessible(true);

        $count = $reflection->invoke($badgeService, (string) $this->user->id, $this->deviceId);

        $this->assertEquals(2, $count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function action_badge_awarded_when_client_event_recorded(): void
    {
        Cache::flush();

        $badge = $this->createTestBadge(['action_skin_changed' => 1], 'exploration');

        ClientEvent::create([
            'user_id'         => $this->user->id,
            'device_id'       => $this->deviceId,
            'event_type'      => 'skin_changed',
            'event_timestamp' => now(),
            'metadata'        => ['skin_id' => 'bold-driver'],
        ]);

        $badgeService = app(BadgeService::class);
        $newBadges    = $badgeService->evaluateUserBadges((string) $this->user->id, $this->deviceId);

        $this->assertNotEmpty($newBadges, 'Action badge should be awarded');
        $this->assertEquals($badge->id, $newBadges[0]->badge_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function multiple_action_events_count_correctly(): void
    {
        Cache::flush();

        $this->createTestBadge(['action_bookmark_created' => 3], 'discovery');

        for ($i = 0; $i < 3; $i++) {
            ClientEvent::create([
                'user_id'         => $this->user->id,
                'device_id'       => $this->deviceId,
                'event_type'      => 'bookmark_created',
                'event_timestamp' => now(),
                'metadata'        => [],
            ]);
        }

        $badgeService = app(BadgeService::class);
        $newBadges    = $badgeService->evaluateUserBadges((string) $this->user->id, $this->deviceId);

        $this->assertNotEmpty($newBadges, 'Badge requiring 3 events should be awarded');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function platform_install_badge_awarded_per_platform(): void
    {
        Cache::flush();

        $androidBadge = $this->createTestBadge(['action_app_installed_android' => 1], 'discovery');
        $iosBadge     = $this->createTestBadge(['action_app_installed_ios' => 1], 'discovery');

        ClientEvent::create([
            'user_id'         => $this->user->id,
            'device_id'       => $this->deviceId,
            'event_type'      => 'app_installed_android',
            'event_timestamp' => now(),
            'metadata'        => ['platform' => 'android'],
        ]);

        $badgeService = app(BadgeService::class);
        $newBadges    = $badgeService->evaluateUserBadges((string) $this->user->id, $this->deviceId);

        $earnedIds = array_map(fn ($ub) => $ub->badge_id, $newBadges);
        $this->assertContains($androidBadge->id, $earnedIds, 'Android badge should be awarded');
        $this->assertNotContains($iosBadge->id, $earnedIds, 'iOS badge should NOT be awarded');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function speed_variety_badge_requires_meaningful_time_at_each_speed(): void
    {
        Cache::flush();

        $badge = $this->createTestBadge(['speed_variety' => 3], 'speed');

        foreach ([1.1, 1.25, 1.5] as $speed) {
            $this->createSessionEndEvent(secondsListened: 600, playbackSpeed: $speed);
        }

        $badgeService = app(BadgeService::class);
        $this->assertSame([], $badgeService->evaluateUserBadges((string) $this->user->id, $this->deviceId));

        ListeningEvent::query()->delete();
        Cache::flush();

        foreach ([1.1, 1.25, 1.5] as $speed) {
            $this->createSessionEndEvent(secondsListened: 1800, playbackSpeed: $speed);
        }

        $newBadges = $badgeService->evaluateUserBadges((string) $this->user->id, $this->deviceId);

        $this->assertCount(1, $newBadges);
        /** @var \App\Models\UserBadge $firstBadge */
        $firstBadge = reset($newBadges);
        $this->assertEquals($badge->id, $firstBadge->badge_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function goal_streak_badges_require_actual_goals(): void
    {
        Cache::flush();

        $badge = $this->createTestBadge(['monthly_goal_streak' => 1], 'dedication');

        $this->createSessionEndEvent(secondsListened: 7200);

        $badgeService = app(BadgeService::class);
        $this->assertSame([], $badgeService->evaluateUserBadges((string) $this->user->id, $this->deviceId));

        ListeningGoal::create([
            'user_id' => $this->user->id,
            'period_type' => 'month',
            'metric' => 'total_hours',
            'target_minutes' => 60,
            'is_active' => true,
        ]);

        Cache::flush();
        $newBadges = $badgeService->evaluateUserBadges((string) $this->user->id, $this->deviceId);

        $this->assertCount(1, $newBadges);
        /** @var \App\Models\UserBadge $firstBadge */
        $firstBadge = reset($newBadges);
        $this->assertEquals($badge->id, $firstBadge->badge_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function library_badges_count_books_from_user_status_records(): void
    {
        Cache::flush();

        $badge = $this->createTestBadge(['library_size' => 1], 'collection');

        UserBookStatus::create([
            'user_id' => $this->user->id,
            'title' => 'Test Book',
            'author' => 'Test Author',
            'status' => 'queue',
            'order' => 1,
        ]);

        $badgeService = app(BadgeService::class);
        $newBadges = $badgeService->evaluateUserBadges((string) $this->user->id, $this->deviceId);

        $this->assertNotEmpty($newBadges);
        $this->assertEquals($badge->id, $newBadges[0]->badge_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function recommendation_badges_require_listening_to_recommended_books(): void
    {
        Cache::flush();

        $title = 'Test Book';
        $author = 'Test Author';
        $badge = $this->createTestBadge(['discovery_rate' => 1], 'discovery');
        $sender = User::factory()->create();

        UserRecommendation::create([
            'sender_id' => $sender->id,
            'recipient_id' => $this->user->id,
            'title' => $title,
            'author' => $author,
            'acknowledged_at' => now(),
        ]);

        $badgeService = app(BadgeService::class);
        $this->assertSame([], $badgeService->evaluateUserBadges((string) $this->user->id, $this->deviceId));

        $this->createSessionEndEvent(title: $title, author: $author, secondsListened: 1200);

        Cache::flush();
        $newBadges = $badgeService->evaluateUserBadges((string) $this->user->id, $this->deviceId);

        $this->assertCount(1, $newBadges);
        /** @var \App\Models\UserBadge $firstBadge */
        $firstBadge = reset($newBadges);
        $this->assertEquals($badge->id, $firstBadge->badge_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function badge_evaluation_uses_fresh_stats_after_progress_changes(): void
    {
        Cache::flush();

        $badge = $this->createTestBadge(['books_completed' => 1], 'completion');
        $badgeService = app(BadgeService::class);

        $this->assertSame([], $badgeService->evaluateUserBadges((string) $this->user->id, $this->deviceId));

        BookProgress::create([
            'user_id' => $this->user->id,
            'title' => 'Test Book',
            'author' => 'Test Author',
            'device_id' => $this->deviceId,
            'current_position_seconds' => 7200,
            'total_duration_seconds' => 7200,
            'progress_percentage' => 100,
            'completed' => true,
            'completed_at' => now(),
        ]);

        $newBadges = $badgeService->evaluateUserBadges((string) $this->user->id, $this->deviceId);

        $this->assertCount(1, $newBadges);
        /** @var \App\Models\UserBadge $firstBadge */
        $firstBadge = reset($newBadges);
        $this->assertEquals($badge->id, $firstBadge->badge_id);
    }
}

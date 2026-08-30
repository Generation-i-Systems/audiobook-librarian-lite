<?php

declare(strict_types=1);

namespace Tests\Web\Feature\Admin;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\UserRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BadgeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRoles::ADMIN]);
    }

    protected function validCriteriaPayload(): string
    {
        return json_encode([
            'version' => 2,
            'logic' => 'AND',
            'conditions' => [
                ['stat' => 'books_completed', 'operator' => '>=', 'value' => 5],
            ],
        ]);
    }

    public function testIndexRendersSuccessfully(): void
    {
        Badge::create([
            'key' => 'index_view_badge',
            'name' => 'Index View Badge',
            'description' => 'desc',
            'category' => 'milestone',
            'tier' => 'bronze',
            'points' => 5,
            'criteria' => ['books_completed' => 1],
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.badges.index'))
            ->assertOk()
            ->assertSee('Index View Badge');
    }

    public function testCreateRendersSuccessfully(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.badges.create'))
            ->assertOk()
            ->assertSee('Create New Badge');
    }

    public function testEditRendersSuccessfully(): void
    {
        $badge = Badge::create([
            'key' => 'edit_view_badge',
            'name' => 'Edit View Badge',
            'description' => 'desc',
            'category' => 'milestone',
            'tier' => 'bronze',
            'points' => 5,
            'criteria' => ['books_completed' => 1],
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.badges.edit', $badge))
            ->assertOk()
            ->assertSee('Edit Badge: Edit View Badge');
    }

    public function testStoreCreatesBadgeWithMultiConditionCriteria(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.badges.store'), [
            'key' => 'test_badge',
            'name' => 'Test Badge',
            'description' => 'A badge for testing',
            'category' => 'milestone',
            'tier' => 'gold',
            'points' => 25,
            'criteria' => $this->validCriteriaPayload(),
        ]);

        $response->assertRedirect(route('admin.badges.index'));

        $badge = Badge::where('key', 'test_badge')->first();
        $this->assertNotNull($badge);
        $this->assertSame('milestone', $badge->category);
        $this->assertSame(2, $badge->criteria['version']);
        $this->assertTrue($badge->evaluateCriteria(['books_completed' => 5]));
    }

    public function testStoreRejectsUnknownStatKey(): void
    {
        $badCriteria = json_encode([
            'version' => 2,
            'logic' => 'AND',
            'conditions' => [
                ['stat' => 'not_a_real_stat', 'operator' => '>=', 'value' => 5],
            ],
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.badges.store'), [
            'key' => 'bad_badge',
            'name' => 'Bad Badge',
            'description' => 'desc',
            'category' => 'milestone',
            'tier' => 'gold',
            'points' => 25,
            'criteria' => $badCriteria,
        ]);

        $response->assertSessionHasErrors('criteria');
        $this->assertNull(Badge::where('key', 'bad_badge')->first());
    }

    public function testStoreUploadsImageAndSetsImageUrl(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('badge.png', 64, 64)->size(10);

        $response = $this->actingAs($this->admin())->post(route('admin.badges.store'), [
            'key' => 'image_badge',
            'name' => 'Image Badge',
            'description' => 'desc',
            'category' => 'milestone',
            'tier' => 'gold',
            'points' => 10,
            'criteria' => $this->validCriteriaPayload(),
            'image_file' => $file,
        ]);

        $response->assertRedirect(route('admin.badges.index'));

        $badge = Badge::where('key', 'image_badge')->first();
        $this->assertNotNull($badge->image_url);
        Storage::disk('public')->assertExists(
            'badges/' . basename(parse_url($badge->image_url, PHP_URL_PATH))
        );
    }

    public function testDestroyDeactivatesRatherThanDeletingBadge(): void
    {
        $badge = Badge::create([
            'key' => 'to_deactivate',
            'name' => 'To Deactivate',
            'description' => 'desc',
            'category' => 'milestone',
            'tier' => 'bronze',
            'points' => 5,
            'criteria' => ['books_completed' => 1],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->delete(route('admin.badges.destroy', $badge));

        $response->assertRedirect(route('admin.badges.index'));
        $this->assertDatabaseHas('badges', ['id' => $badge->id, 'is_active' => false]);
        $this->assertDatabaseHas('badges', ['id' => $badge->id]);
    }

    public function testForceDestroyIsBlockedWhenBadgeHasBeenEarned(): void
    {
        $badge = Badge::create([
            'key' => 'earned_badge',
            'name' => 'Earned Badge',
            'description' => 'desc',
            'category' => 'milestone',
            'tier' => 'bronze',
            'points' => 5,
            'criteria' => ['books_completed' => 1],
            'is_active' => false,
        ]);

        UserBadge::create([
            'user_id' => 'some-user',
            'badge_id' => $badge->id,
            'earned_at' => now(),
            'criteria_met' => ['books_completed' => 1],
            'tier_level' => 1,
        ]);

        $response = $this->actingAs($this->admin())->delete(route('admin.badges.forceDestroy', $badge));

        $response->assertRedirect(route('admin.badges.index'));
        $this->assertDatabaseHas('badges', ['id' => $badge->id]);
    }

    public function testForceDestroyDeletesBadgeNeverEarned(): void
    {
        $badge = Badge::create([
            'key' => 'never_earned',
            'name' => 'Never Earned',
            'description' => 'desc',
            'category' => 'milestone',
            'tier' => 'bronze',
            'points' => 5,
            'criteria' => ['books_completed' => 1],
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin())->delete(route('admin.badges.forceDestroy', $badge));

        $response->assertRedirect(route('admin.badges.index'));
        $this->assertDatabaseMissing('badges', ['id' => $badge->id]);
    }

    public function testActivateReactivatesADeactivatedBadge(): void
    {
        $badge = Badge::create([
            'key' => 'reactivate_me',
            'name' => 'Reactivate Me',
            'description' => 'desc',
            'category' => 'milestone',
            'tier' => 'bronze',
            'points' => 5,
            'criteria' => ['books_completed' => 1],
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.badges.activate', $badge));

        $response->assertRedirect(route('admin.badges.index'));
        $this->assertDatabaseHas('badges', ['id' => $badge->id, 'is_active' => true]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookTagControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 'library-user'], $attributes));
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_it_stores_and_returns_user_scope_tags_for_a_book(): void
    {
        $this->actingUser();

        $response = $this->putJson('/api/v1/books/tags', [
            'title' => 'The Way of Kings',
            'author' => 'Brandon Sanderson',
            'scope' => 'user',
            'tags' => ['Series', '  epic-fantasy ', 'Series', ''],
        ]);

        $response->assertOk();
        $response->assertJson([
            'title' => 'The Way of Kings',
            'author' => 'Brandon Sanderson',
            'scope' => 'user',
            'tags' => ['Series', 'epic-fantasy'],
        ]);

        $showResponse = $this->getJson('/api/v1/books/tags?title=' . urlencode('The Way of Kings') . '&author=' . urlencode('Brandon Sanderson'));
        $showResponse->assertOk();
        $showResponse->assertJson([
            'system' => [],
            'groups' => [],
            'user' => ['Series', 'epic-fantasy'],
        ]);
    }

    public function test_non_admin_cannot_set_system_tags(): void
    {
        $this->actingUser();

        $response = $this->putJson('/api/v1/books/tags', [
            'title' => 'A Book',
            'scope' => 'system',
            'tags' => ['staff-pick'],
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_set_system_tags_visible_to_everyone(): void
    {
        $this->actingUser(['role' => 'admin']);

        $this->putJson('/api/v1/books/tags', [
            'title' => 'A Book',
            'author' => 'An Author',
            'scope' => 'system',
            'tags' => ['staff-pick'],
        ])->assertOk();

        $this->actingUser();
        $showResponse = $this->getJson('/api/v1/books/tags?title=' . urlencode('A Book') . '&author=' . urlencode('An Author'));
        $showResponse->assertOk();
        $showResponse->assertJsonPath('system', ['staff-pick']);
    }

    public function test_group_member_can_set_group_tags_invisible_to_non_members(): void
    {
        $member = $this->actingUser();
        $group = Group::query()->create(['name' => 'Book Club']);
        $group->members()->attach($member->id);

        $this->putJson('/api/v1/books/tags', [
            'title' => 'A Book',
            'scope' => 'group',
            'group_id' => $group->id,
            'tags' => ['book-club-pick'],
        ])->assertOk();

        $showResponse = $this->getJson('/api/v1/books/tags?title=' . urlencode('A Book'));
        $showResponse->assertOk();
        $showResponse->assertJson([
            'groups' => [
                ['groupId' => $group->id, 'groupName' => 'Book Club', 'tags' => ['book-club-pick']],
            ],
        ]);

        $this->actingUser();
        $outsiderResponse = $this->getJson('/api/v1/books/tags?title=' . urlencode('A Book'));
        $outsiderResponse->assertOk();
        $outsiderResponse->assertJson(['groups' => []]);
    }

    public function test_popular_tags_only_aggregates_system_scope(): void
    {
        $this->actingUser(['role' => 'admin']);
        $this->putJson('/api/v1/books/tags', [
            'title' => 'Book A',
            'scope' => 'system',
            'tags' => ['staff-pick'],
        ])->assertOk();
        $this->putJson('/api/v1/books/tags', [
            'title' => 'Book B',
            'scope' => 'system',
            'tags' => ['staff-pick', 'award-winner'],
        ])->assertOk();

        $this->actingUser();
        $this->putJson('/api/v1/books/tags', [
            'title' => 'Book A',
            'scope' => 'user',
            'tags' => ['my-secret-tag'],
        ])->assertOk();

        $response = $this->getJson('/api/v1/tags/popular');

        $response->assertOk();
        $response->assertJsonPath('tags.0', 'staff-pick');
        $response->assertJsonCount(2, 'tags');
        $response->assertJsonMissing(['tags' => ['my-secret-tag']]);
    }
}

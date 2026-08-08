<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BookTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_view_tags_index(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'hybrid-user']));

        $response = $this->get(route('admin.tags.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_add_system_tags_for_a_title_and_author(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);
        $response = $this->post(route('admin.tags.store'), [
            'title' => 'Dune',
            'author' => 'Frank Herbert',
            'tags' => 'staff-pick, sci-fi',
        ]);

        $response->assertRedirect(route('admin.tags.index'));
        $this->assertDatabaseHas('book_tags', [
            'book_title' => 'Dune',
            'book_author' => 'Frank Herbert',
            'scope' => 'system',
            'owner_key' => 'system',
        ]);
    }

    public function test_admin_sees_system_tags_with_usage_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        BookTag::create(['user_id' => $admin->id, 'book_title' => 'Dune', 'book_author' => 'Frank Herbert', 'scope' => 'system', 'tags' => ['staff-pick']]);
        BookTag::create(['user_id' => $admin->id, 'book_title' => 'Foundation', 'book_author' => 'Isaac Asimov', 'scope' => 'system', 'tags' => ['staff-pick']]);

        $this->actingAs($admin);
        $response = $this->get(route('admin.tags.index'));

        $response->assertOk();
        $response->assertSee('staff-pick');
        $response->assertSee('2');
    }

    public function test_admin_can_rename_a_system_tag_across_all_titles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        BookTag::create(['user_id' => $admin->id, 'book_title' => 'Dune', 'book_author' => 'Frank Herbert', 'scope' => 'system', 'tags' => ['staff-pick']]);
        BookTag::create(['user_id' => $admin->id, 'book_title' => 'Foundation', 'book_author' => 'Isaac Asimov', 'scope' => 'system', 'tags' => ['staff-pick', 'award-winner']]);

        $this->actingAs($admin);
        $response = $this->put(route('admin.tags.update', 'staff-pick'), ['name' => 'editors-choice']);

        $response->assertRedirect(route('admin.tags.index'));
        $this->assertDatabaseHas('book_tags', ['book_title' => 'Dune', 'tags' => json_encode(['editors-choice'])]);
        $this->assertDatabaseMissing('book_tags', ['book_title' => 'Dune', 'tags' => json_encode(['staff-pick'])]);
    }

    public function test_admin_can_delete_a_system_tag_across_all_titles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        BookTag::create(['user_id' => $admin->id, 'book_title' => 'Dune', 'book_author' => 'Frank Herbert', 'scope' => 'system', 'tags' => ['staff-pick', 'award-winner']]);

        $this->actingAs($admin);
        $response = $this->delete(route('admin.tags.destroy', 'staff-pick'));

        $response->assertRedirect(route('admin.tags.index'));
        $this->assertDatabaseHas('book_tags', ['book_title' => 'Dune', 'tags' => json_encode(['award-winner'])]);
    }
}

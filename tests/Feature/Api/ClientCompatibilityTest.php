<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'library-user',
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    #[Test]
    public function it_can_delete_bookmark_by_id_only()
    {
        $bookmark = Bookmark::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/bookmarks/{$bookmark->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('bookmarks', ['id' => $bookmark->id]);
    }
}

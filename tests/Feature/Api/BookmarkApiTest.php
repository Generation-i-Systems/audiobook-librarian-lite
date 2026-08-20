<?php

namespace Tests\Feature\Api;

use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarkApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $bookTitle = 'Project Hail Mary';
    protected string $bookAuthor = 'Andy Weir';

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'role' => 'full-user',
        ]);
    }

    /**
     * Test getting bookmarks for a book.
     */
    public function test_get_bookmarks(): void
    {
        Bookmark::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'title' => $this->bookTitle,
            'author' => $this->bookAuthor,
            'notes' => 'This is a test note',
            'is_auto' => false,
        ]);

        $response = $this->actingAs($this->user, 'web')
            ->getJson('/api/v1/bookmarks?' . http_build_query([
                'title' => $this->bookTitle,
                'author' => $this->bookAuthor,
            ]));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'bookmarks')
            ->assertJsonStructure([
                'bookmarks' => [
                    '*' => [
                        'id',
                        'title',
                        'author',
                        'position_ms',
                        'note',
                        'is_auto',
                        'created_at',
                    ]
                ]
            ]);

        $this->assertEquals('This is a test note', $response->json('bookmarks.0.note'));
    }

    /**
     * Test creating a bookmark.
     */
    public function test_create_bookmark(): void
    {
        $payload = [
            'title' => $this->bookTitle,
            'author' => $this->bookAuthor,
            'position_ms' => 120000,
            'note' => 'Plot twist at 2 minutes',
            'is_auto' => false,
        ];

        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/v1/bookmarks', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'title' => $this->bookTitle,
                'author' => $this->bookAuthor,
                'position_ms' => 120000,
                'note' => 'Plot twist at 2 minutes',
                'is_auto' => false,
            ]);

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $this->user->id,
            'title' => $this->bookTitle,
            'author' => $this->bookAuthor,
            'position' => 120,
            'notes' => 'Plot twist at 2 minutes',
            'is_auto' => false,
        ]);
    }

    /**
     * Test character mapping and compatibility.
     */
    public function test_create_bookmark_minimal(): void
    {
        $payload = [
            'title' => $this->bookTitle,
            'author' => $this->bookAuthor,
            'position_ms' => 60000,
        ];

        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/v1/bookmarks', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'title', 'author', 'position_ms', 'created_at']);

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $this->user->id,
            'title' => $this->bookTitle,
            'author' => $this->bookAuthor,
            'position' => 60,
            'chapter' => '1'
        ]);
    }

    /**
     * There is no book catalog in lite, so title/author are opaque and
     * client-supplied: an unrecognized book simply has no bookmarks yet, not a 404.
     */
    public function test_get_bookmarks_for_unknown_book_returns_empty(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->getJson('/api/v1/bookmarks?' . http_build_query([
                'title' => 'Unknown Book',
                'author' => 'Unknown Author',
            ]));

        $response->assertStatus(200)
            ->assertJsonCount(0, 'bookmarks');
    }
}

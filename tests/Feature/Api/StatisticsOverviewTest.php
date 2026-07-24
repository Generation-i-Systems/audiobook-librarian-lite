<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ListeningStatistic;

class StatisticsOverviewTest extends ApiTestCase
{
    public function test_overview_favorite_genres_aggregates_by_genre_string(): void
    {
        // Lite has no book library — genre is client-supplied and stored directly
        // on each listening_statistics row, not derived via a book/genre join.
        ListeningStatistic::create([
            'user_id' => $this->user->id,
            'device_id' => 'stats-device',
            'title' => 'A Sci-Fi Book',
            'author' => 'Some Author',
            'genre' => 'Science Fiction',
            'listening_date' => now()->toDateString(),
            'seconds_listened' => 2400,
            'session_type' => 'listening',
        ]);

        ListeningStatistic::create([
            'user_id' => $this->user->id,
            'device_id' => 'stats-device',
            'title' => 'A Mystery Book',
            'author' => 'Another Author',
            'genre' => 'Mystery',
            'listening_date' => now()->toDateString(),
            'seconds_listened' => 600,
            'session_type' => 'listening',
        ]);

        $response = $this->withHeader('X-Device-ID', 'stats-device')
            ->getJson('/api/v1/statistics/overview');

        $response->assertOk();
        $favoriteGenres = $response->json('favorite_genres');
        $this->assertContains('Science Fiction', $favoriteGenres);
        $this->assertContains('Mystery', $favoriteGenres);
        $this->assertSame('Science Fiction', $favoriteGenres[0]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Api;

use Tests\TestCase;

class CapabilitiesTest extends TestCase
{
    public function testCapabilitiesUseTheClientVocabulary(): void
    {
        $response = $this->getJson('/api/v1/health/capabilities');

        $response->assertStatus(200);
        $response->assertJson(['serverType' => 'ablibrarian-lite']);

        $capabilities = $response->json('capabilities');

        // Names the client's BackendCapability enum understands.
        foreach (['HISTORY_SYNC', 'STATS', 'BOOKMARKS_SYNC', 'BADGES'] as $capability) {
            $this->assertContains($capability, $capabilities);
        }

        // Older spellings kept for clients that only know those.
        foreach (['POSITION_SYNC', 'ACHIEVEMENTS'] as $legacy) {
            $this->assertContains($legacy, $capabilities);
        }

        // Lite hosts no catalog, so it must never claim catalog-backed features.
        foreach (['BROWSE', 'DOWNLOAD', 'PLAYLISTS', 'RECOMMENDATIONS', 'METADATA_MATCH', 'SKINS_GALLERY'] as $catalog) {
            $this->assertNotContains($catalog, $capabilities);
        }
    }
}

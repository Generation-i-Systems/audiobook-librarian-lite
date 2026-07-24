<?php

declare(strict_types=1);

namespace Tests\Api;

use Tests\TestCase;

class ApiRootTest extends TestCase
{
    /**
     * Test that the API root endpoint returns a successful response with expected structure.
     */
    public function test_api_root_endpoint_returns_success(): void
    {
        $response = $this->getJson('/api/v1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'name',
                'version',
                'documentation',
                'openapi',
                'resources',
            ]);
    }

    /**
     * Test that the API root endpoint lists available resources.
     */
    public function test_api_root_lists_resources(): void
    {
        $response = $this->getJson('/api/v1');

        $response->assertStatus(200);

        $data = $response->json();

        // Lite has no book library — check for resources that actually exist:
        // sync (bookmarks/positions/events), statistics, goals, devices.
        $this->assertArrayHasKey('sync', $data['resources']);
        $this->assertArrayHasKey('statistics', $data['resources']);
        $this->assertArrayHasKey('goals', $data['resources']);

        // Verify structure of a resource entry
        $syncRoutes = $data['resources']['sync'];
        $this->assertIsArray($syncRoutes);
        $this->assertNotEmpty($syncRoutes);

        $firstRoute = $syncRoutes[0];
        $this->assertArrayHasKey('method', $firstRoute);
        $this->assertArrayHasKey('uri', $firstRoute);
        $this->assertArrayHasKey('url', $firstRoute);
        // Name is optional in our implementation, but good to check if it exists or is null
    }

    /**
     * Test that the API root endpoint excludes non-API routes.
     */
    public function test_api_root_excludes_non_api_routes(): void
    {
        $response = $this->getJson('/api/v1');

        $data = $response->json();
        $resources = $data['resources'];

        // Flatten all URIs
        $uris = [];
        foreach ($resources as $resourceGroup) {
            foreach ($resourceGroup as $route) {
                $uris[] = $route['uri'];
            }
        }

        // Verify all URIs start with api/v1
        foreach ($uris as $uri) {
            $this->assertStringStartsWith('api/v1', $uri);
        }

        // Verify root itself is not in the list (if we chose to exclude it)
        // logic in controller: if ($uri === 'api/v1') { continue; }
        $this->assertNotContains('api/v1', $uris);
    }
}

<?php

namespace Tests\Api\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * API Health Check Tests
 *
 * Tests for the unauthenticated health check endpoints used by uptime monitors.
 */
#[Group('api-health')]
#[Group('api-spec')]
class ApiHealthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Ping endpoint returns OK
     */
    #[Test]
    public function testPingEndpointReturnsOk(): void
    {
        $response = $this->getJson('/api/v1/health/ping');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'service' => 'audiobook-librarian-api',
        ]);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'service',
        ]);
    }

    /**
     * Test: Health endpoint returns healthy status and reports live-table data availability
     */
    #[Test]
    public function testHealthEndpointReturnsHealthyStatus(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'healthy',
            'api_version' => 'v1',
        ]);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database',
                'data_availability' => [
                    'passed',
                    'counts' => [
                        'users',
                        'listening_statistics',
                        'bookmarks',
                        'devices',
                    ],
                    'total_records',
                ],
                'storage',
            ],
            'api_version',
        ]);
    }

    /**
     * Test: Validate endpoint confirms the live sync tables exist with expected columns
     */
    #[Test]
    public function testValidateSpecEndpointChecksLiveTables(): void
    {
        $response = $this->getJson('/api/v1/health/validate');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'spec_compliant',
            'api_version' => 'v1',
        ]);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'validations' => [
                'users' => ['passed', 'row_count'],
                'listening_statistics' => ['passed', 'row_count'],
                'bookmarks' => ['passed', 'row_count'],
                'devices' => ['passed', 'row_count'],
            ],
            'api_version',
        ]);
    }

    /**
     * Test: Health endpoints are accessible without authentication
     */
    #[Test]
    public function testHealthEndpointsAccessibleWithoutAuth(): void
    {
        // Ping should work without auth
        $response = $this->getJson('/api/v1/health/ping');
        $response->assertStatus(200);

        // Health should work without auth
        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(200);

        // Validate should work without auth
        $response = $this->getJson('/api/v1/health/validate');
        $response->assertStatus(200);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;

/**
 * API Health Check Controller
 *
 * Provides endpoints for uptime monitoring and DB health/spec validation.
 * These endpoints can be accessed without authentication.
 */
class ApiHealthController extends Controller
{
    protected HealthCheckService $healthCheckService;

    public function __construct(HealthCheckService $healthCheckService)
    {
        $this->healthCheckService = $healthCheckService;
    }

    /**
     * Basic health check - verifies API is responding
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'service' => 'audiobook-librarian-api',
        ]);
    }

    /**
     * Detailed health check - confirms the database is reachable and populated
     *
     * This endpoint validates:
     * - Database connectivity
     * - Data availability across the live sync tables (users, listening
     *   statistics, positions, events, badges, and devices)
     */
    public function health(): JsonResponse
    {
        $checks = [];
        $allPassed = true;

        // Check 1: Database connectivity
        $checks['database'] = $this->healthCheckService->checkDatabase();
        if (!$checks['database']['passed']) {
            $allPassed = false;
        }

        // Check 2: Data availability across live tables
        $checks['data_availability'] = $this->healthCheckService->checkDataAvailability();
        if (!$checks['data_availability']['passed']) {
            $allPassed = false;
        }

        // @phpstan-ignore-next-line
        return response()->json([
            'status' => $allPassed ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'api_version' => 'v1',
        ], $allPassed ? 200 : 503);
    }

    /**
     * Full spec validation - confirms the live sync tables exist, have the
     * expected columns, and are reachable
     */
    public function validateSpec(): JsonResponse
    {
        $validations = [];
        $allPassed = true;

        $tables = [
            'users' => ['id', 'email', 'role'],
            'bookmarks' => ['id', 'user_id', 'title', 'author'],
            'listening_statistics' => ['id', 'user_id', 'title', 'author'],
            'book_positions' => ['user_id', 'title', 'author', 'device_id', 'position_ms'],
            'listening_events' => ['id', 'user_id', 'title', 'author'],
            'badges' => ['id', 'key'],
            'user_badges' => ['id', 'user_id', 'badge_id'],
            'devices' => ['device_id', 'user_id'],
        ];

        foreach ($tables as $table => $requiredColumns) {
            $validations[$table] = $this->healthCheckService->validateTableSchema($table, $requiredColumns);
            if (!$validations[$table]['passed']) {
                $allPassed = false;
            }
        }

        return response()->json([
            'status' => $allPassed ? 'spec_compliant' : 'spec_violations_found',
            'timestamp' => now()->toIso8601String(),
            'validations' => $validations,
            'api_version' => 'v1',
        ], $allPassed ? 200 : 422);
    }
}

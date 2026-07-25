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
     *   statistics, bookmarks, devices)
     * - Storage volume availability and free space
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

        // Check 3: Storage volume accessibility and free space
        $checks['storage'] = $this->checkStorageVolumes();
        if (!$checks['storage']['passed']) {
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
            'listening_statistics' => ['id', 'user_id', 'title', 'author'],
            'bookmarks' => ['id', 'user_id'],
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

    /**
     * Check all configured storage volumes for accessibility and free space
     */
    protected function checkStorageVolumes(): array
    {
        $paths = $this->getStoragePaths();
        $volumes = [];
        $allHealthy = true;

        foreach ($paths as $name => $path) {
            $exists = is_dir($path);
            $readable = $exists && is_readable($path);

            $freeBytes = $readable ? disk_free_space($path) : false;
            $totalBytes = $readable ? disk_total_space($path) : false;

            $freeBytesInt = $freeBytes !== false ? (int) $freeBytes : null;
            $totalBytesInt = $totalBytes !== false ? (int) $totalBytes : null;
            $usedPercent = ($freeBytesInt !== null && $totalBytesInt !== null && $totalBytesInt > 0) ? round((($totalBytesInt - $freeBytesInt) / $totalBytesInt) * 100, 1) : null;

            $passed = $exists && $readable && $freeBytes !== false;
            if (!$passed) {
                $allHealthy = false;
            }

            $volumes[$name] = [
                'passed' => $passed,
                'path' => $path,
                'exists' => $exists,
                'readable' => $readable,
                'free_bytes' => $freeBytesInt,
                'total_bytes' => $totalBytesInt,
                'used_percent' => $usedPercent,
                'message' => $passed ? 'Volume accessible' : ($exists ? 'Volume not readable' : 'Directory does not exist'),
            ];
        }

        return [
            'passed' => $allHealthy,
            'message' => $allHealthy ? 'All storage volumes accessible' : 'One or more storage volumes have issues',
            'volumes' => $volumes,
        ];
    }

    /**
     * Return the named storage paths to monitor
     */
    protected function getStoragePaths(): array
    {
        $paths = [
            'books' => (string) config('filesystems.disks.books.root', ''),
            'trash' => (string) config('filesystems.disks.trash.root', ''),
        ];

        return array_filter($paths);
    }
}

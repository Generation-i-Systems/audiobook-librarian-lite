<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bookmark;
use App\Models\Device;
use App\Models\ListeningStatistic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Encapsulates the database checks used by ApiHealthController, keeping raw
 * DB/Schema access out of the controller layer.
 */
class HealthCheckService
{
    /**
     * Check database connectivity
     */
    public function checkDatabase(): array
    {
        try {
            User::query()->exists();

            return [
                'passed' => true,
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            Log::error('Health check: Database connection failed', ['error' => $e->getMessage()]);
            return [
                'passed' => false,
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check that the live sync tables are reachable and report their row counts
     */
    public function checkDataAvailability(): array
    {
        try {
            $counts = [
                'users' => User::count(),
                'listening_statistics' => ListeningStatistic::count(),
                'bookmarks' => Bookmark::count(),
                'devices' => Device::count(),
            ];

            return [
                'passed' => true,
                'message' => 'Data availability check completed',
                'counts' => $counts,
                'total_records' => array_sum($counts),
            ];
        } catch (\Exception $e) {
            Log::error('Health check: Data availability check failed', ['error' => $e->getMessage()]);
            return [
                'passed' => false,
                'message' => 'Data availability check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate that a table exists, is queryable, and has the expected columns
     *
     * @param string[] $requiredColumns
     */
    public function validateTableSchema(string $table, array $requiredColumns): array
    {
        try {
            if (!Schema::hasTable($table)) {
                return [
                    'passed' => false,
                    'message' => "Table '{$table}' does not exist",
                ];
            }

            $missingColumns = array_values(array_filter(
                $requiredColumns,
                fn (string $column): bool => !Schema::hasColumn($table, $column)
            ));

            $rowCount = DB::table($table)->count();

            return [
                'passed' => empty($missingColumns),
                'message' => empty($missingColumns) ? "Table '{$table}' valid" : 'Missing required columns',
                'missing_columns' => $missingColumns,
                'row_count' => $rowCount,
            ];
        } catch (\Exception $e) {
            Log::error("Health check: '{$table}' schema validation failed", ['error' => $e->getMessage()]);
            return [
                'passed' => false,
                'message' => 'Table validation failed',
                'error' => $e->getMessage(),
            ];
        }
    }
}

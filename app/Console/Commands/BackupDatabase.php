<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:database
        {--verify : Verify backup integrity after creation}
        {--suffix= : Add a suffix to distinguish backup source}
        {--connection= : Database connection to back up (defaults to the app default connection)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a backup of the database (MySQL, PostgreSQL, or SQLite)';

    private const SUPPORTED_DRIVERS = ['mysql', 'pgsql', 'sqlite'];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting database backup...');

        // Safety: never execute external backup tools during automated tests
        if (app()->environment('testing')) {
            $this->warn('Skipping database backup in testing environment.');
            Log::info('Backup skipped in testing environment');
            return Command::SUCCESS;
        }

        $connection = (string) ($this->option('connection') ?: config('database.default'));
        $driver = (string) config("database.connections.{$connection}.driver");

        if (!in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            $this->error("✗ Unsupported database driver '{$driver}' for connection '{$connection}'");
            Log::error('Database backup failed: unsupported driver', [
                'connection' => $connection,
                'driver' => $driver,
            ]);
            return Command::FAILURE;
        }

        // Create backup directory
        $backupDir = (string) config('app.database_backup_path');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = now()->format('Ymd_His');
        $suffix = $this->option('suffix');
        $suffixPart = $suffix ? "_{$suffix}" : '';

        $result = match ($driver) {
            'mysql' => $this->buildMysqlBackup($connection, $backupDir, $suffixPart, $timestamp),
            'pgsql' => $this->buildPgsqlBackup($connection, $backupDir, $suffixPart, $timestamp),
            'sqlite' => $this->buildSqliteBackup($connection, $backupDir, $suffixPart, $timestamp),
        };

        if (isset($result['error'])) {
            $this->error('✗ ' . $result['error']);
            Log::error('Database backup failed', [
                'connection' => $connection,
                'driver' => $driver,
                'reason' => $result['error'],
            ]);
            return Command::FAILURE;
        }

        [$backupFile, $dbLabel, $command] = [$result['file'], $result['db'], $result['command']];

        $this->line('Creating backup: ' . basename($backupFile));

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('✗ Backup failed');
            Log::error('Database backup failed', [
                'connection' => $connection,
                'driver' => $driver,
                'database' => $dbLabel,
                'output' => implode("\n", $output),
            ]);
            return Command::FAILURE;
        }

        // Compress the backup
        $compressCommand = 'gzip ' . escapeshellarg($backupFile);
        exec($compressCommand, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('✗ Failed to compress backup');
            return Command::FAILURE;
        }

        $compressedFile = $backupFile . '.gz';
        $fileSize = $this->formatBytes(filesize($compressedFile));

        $this->info('✓ Backup created successfully: ' . basename($compressedFile) . " ({$fileSize})");

        Log::info('Database backup created', [
            'file' => basename($compressedFile),
            'size' => $fileSize,
            'connection' => $connection,
            'driver' => $driver,
            'database' => $dbLabel,
            'suffix' => $suffix ?: 'none',
        ]);

        if ($this->option('verify')) {
            $this->verifyBackup($compressedFile);
        }

        $this->cleanupOldBackups($backupDir);

        return Command::SUCCESS;
    }

    /**
     * Build the mysqldump command for a MySQL/MariaDB connection.
     *
     * @return array{file: string, db: string, command: string}|array{error: string}
     */
    protected function buildMysqlBackup(string $connection, string $backupDir, string $suffixPart, string $timestamp): array
    {
        $dbHost = (string) config("database.connections.{$connection}.host");
        $dbPort = (string) config("database.connections.{$connection}.port");
        $dbName = (string) config("database.connections.{$connection}.database");
        $dbUser = (string) config("database.connections.{$connection}.username");
        $dbPassword = (string) config("database.connections.{$connection}.password");

        if ($dbName === '') {
            return ['error' => "No database name configured for connection '{$connection}'"];
        }

        $backupFile = "{$backupDir}/backup_{$dbName}{$suffixPart}_{$timestamp}.sql";

        $command = sprintf(
            'mysqldump -h%s -P%s -u%s -p%s --single-transaction --routines --triggers --events --add-drop-database --databases %s > %s',
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbPassword),
            escapeshellarg($dbName),
            escapeshellarg($backupFile)
        );

        return ['file' => $backupFile, 'db' => $dbName, 'command' => $command];
    }

    /**
     * Build the pg_dump command for a PostgreSQL connection.
     *
     * @return array{file: string, db: string, command: string}|array{error: string}
     */
    protected function buildPgsqlBackup(string $connection, string $backupDir, string $suffixPart, string $timestamp): array
    {
        $dbHost = (string) config("database.connections.{$connection}.host");
        $dbPort = (string) config("database.connections.{$connection}.port");
        $dbName = (string) config("database.connections.{$connection}.database");
        $dbUser = (string) config("database.connections.{$connection}.username");
        $dbPassword = (string) config("database.connections.{$connection}.password");

        if ($dbName === '') {
            return ['error' => "No database name configured for connection '{$connection}'"];
        }

        $backupFile = "{$backupDir}/backup_{$dbName}{$suffixPart}_{$timestamp}.sql";

        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s --no-owner --no-privileges --clean --if-exists -F p %s > %s',
            escapeshellarg($dbPassword),
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($backupFile)
        );

        return ['file' => $backupFile, 'db' => $dbName, 'command' => $command];
    }

    /**
     * Build the sqlite3 online-backup command for a SQLite connection.
     *
     * @return array{file: string, db: string, command: string}|array{error: string}
     */
    protected function buildSqliteBackup(string $connection, string $backupDir, string $suffixPart, string $timestamp): array
    {
        $dbPath = (string) config("database.connections.{$connection}.database");

        if ($dbPath === '' || $dbPath === ':memory:') {
            return ['error' => "Connection '{$connection}' has no on-disk SQLite database to back up"];
        }

        if (!is_file($dbPath)) {
            return ['error' => "SQLite database file not found: {$dbPath}"];
        }

        $dbName = pathinfo($dbPath, PATHINFO_FILENAME);
        $backupFile = "{$backupDir}/backup_{$dbName}{$suffixPart}_{$timestamp}.sqlite";

        // The sqlite3 CLI's ".backup" dot-command takes an online, consistent
        // snapshot via SQLite's backup API — safe even if the app is writing
        // to the database concurrently, unlike a plain file copy.
        $command = sprintf(
            'sqlite3 %s %s',
            escapeshellarg($dbPath),
            escapeshellarg('.backup ' . $backupFile)
        );

        return ['file' => $backupFile, 'db' => $dbName, 'command' => $command];
    }

    /**
     * Verify backup integrity
     */
    private function verifyBackup(string $backupFile): void
    {
        $this->line("Verifying backup integrity...");

        $command = "gunzip -t " . escapeshellarg($backupFile);
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            $this->info("✓ Backup integrity verified");
        } else {
            $this->error("✗ Backup integrity check failed");
            Log::error('Backup integrity check failed', [
                'file' => basename($backupFile)
            ]);
        }
    }

    /**
     * Clean up backups older than 30 days
     */
    private function cleanupOldBackups(string $backupDir): void
    {
        $this->line("Cleaning up old backups...");

        $files = glob($backupDir . '/backup_*.{sql,sqlite}.gz', GLOB_BRACE);
        $thirtyDaysAgo = now()->subDays(30)->timestamp;
        $deletedCount = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $thirtyDaysAgo) {
                unlink($file);
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->info("✓ Deleted {$deletedCount} old backup(s)");
        } else {
            $this->line("No old backups to delete");
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

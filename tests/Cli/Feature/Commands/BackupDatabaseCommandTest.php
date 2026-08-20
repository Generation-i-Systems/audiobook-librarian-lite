<?php

declare(strict_types=1);

namespace Tests\Cli\Feature\Commands;

use App\Console\Commands\BackupDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class BackupDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    private function invokeBuild(string $method, array $args): array
    {
        $command = new BackupDatabase();
        $reflection = new ReflectionMethod($command, $method);

        return $reflection->invoke($command, ...$args);
    }

    #[Test]
    public function testBuildsMysqlBackupCommandFromConnectionConfig(): void
    {
        config([
            'database.connections.mysql.host' => 'db-host',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'librarian',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => 'secret pass',
        ]);

        $result = $this->invokeBuild('buildMysqlBackup', ['mysql', '/backups', '_suffix', '20260101_000000']);

        $this->assertSame('/backups/backup_librarian_suffix_20260101_000000.sql', $result['file']);
        $this->assertSame('librarian', $result['db']);
        $this->assertStringContainsString('mysqldump', $result['command']);
        $this->assertStringContainsString("'librarian'", $result['command']);
        $this->assertStringContainsString("'secret pass'", $result['command']);
        $this->assertStringContainsString("> '/backups/backup_librarian_suffix_20260101_000000.sql'", $result['command']);
    }

    #[Test]
    public function testMysqlBackupFailsFastWhenNoDatabaseNameConfigured(): void
    {
        config(['database.connections.mysql.database' => '']);

        $result = $this->invokeBuild('buildMysqlBackup', ['mysql', '/backups', '', '20260101_000000']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('mysql', $result['error']);
    }

    #[Test]
    public function testBuildsPgsqlBackupCommandFromConnectionConfig(): void
    {
        config([
            'database.connections.pgsql.host' => 'pg-host',
            'database.connections.pgsql.port' => '5432',
            'database.connections.pgsql.database' => 'librarian',
            'database.connections.pgsql.username' => 'postgres',
            'database.connections.pgsql.password' => 'pg secret',
        ]);

        $result = $this->invokeBuild('buildPgsqlBackup', ['pgsql', '/backups', '', '20260101_000000']);

        $this->assertSame('/backups/backup_librarian_20260101_000000.sql', $result['file']);
        $this->assertSame('librarian', $result['db']);
        $this->assertStringContainsString('pg_dump', $result['command']);
        $this->assertStringContainsString("PGPASSWORD='pg secret'", $result['command']);
        $this->assertStringContainsString("-U 'postgres'", $result['command']);
    }

    #[Test]
    public function testPgsqlBackupFailsFastWhenNoDatabaseNameConfigured(): void
    {
        config(['database.connections.pgsql.database' => '']);

        $result = $this->invokeBuild('buildPgsqlBackup', ['pgsql', '/backups', '', '20260101_000000']);

        $this->assertArrayHasKey('error', $result);
    }

    #[Test]
    public function testBuildsSqliteBackupCommandForRealDatabaseFile(): void
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'lite_backup_test_');
        file_put_contents($dbFile, '');

        config(['database.connections.sqlite.database' => $dbFile]);

        $result = $this->invokeBuild('buildSqliteBackup', ['sqlite', '/backups', '', '20260101_000000']);

        $expectedName = pathinfo($dbFile, PATHINFO_FILENAME);
        $this->assertSame("/backups/backup_{$expectedName}_20260101_000000.sqlite", $result['file']);
        $this->assertSame($expectedName, $result['db']);
        $this->assertStringContainsString('sqlite3', $result['command']);
        $this->assertStringContainsString('.backup', $result['command']);

        unlink($dbFile);
    }

    #[Test]
    public function testSqliteBackupFailsFastForInMemoryDatabase(): void
    {
        config(['database.connections.sqlite.database' => ':memory:']);

        $result = $this->invokeBuild('buildSqliteBackup', ['sqlite', '/backups', '', '20260101_000000']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('no on-disk SQLite database', $result['error']);
    }

    #[Test]
    public function testSqliteBackupFailsFastWhenFileDoesNotExist(): void
    {
        config(['database.connections.sqlite.database' => '/nonexistent/path/does-not-exist.sqlite']);

        $result = $this->invokeBuild('buildSqliteBackup', ['sqlite', '/backups', '', '20260101_000000']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not found', $result['error']);
    }

    #[Test]
    public function testEndToEndSqliteBackupProducesARestorableGzippedSnapshot(): void
    {
        $backupDir = sys_get_temp_dir() . '/lite_backup_e2e_' . uniqid();
        mkdir($backupDir);

        $dbFile = $backupDir . '/source.sqlite';
        $pdo = new \PDO('sqlite:' . $dbFile);
        $pdo->exec('CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO widgets (name) VALUES ('gear')");
        unset($pdo);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $dbFile,
            'app.database_backup_path' => $backupDir,
        ]);

        app()->detectEnvironment(fn () => 'local');

        try {
            $this->artisan('backup:database', ['--suffix' => 'e2e'])->assertExitCode(0);
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }

        $gzFiles = glob($backupDir . '/backup_source_e2e_*.sqlite.gz');
        $this->assertCount(1, $gzFiles);

        $restored = $backupDir . '/restored.sqlite';
        exec('gunzip -c ' . escapeshellarg($gzFiles[0]) . ' > ' . escapeshellarg($restored));

        $restoredPdo = new \PDO('sqlite:' . $restored);
        $name = $restoredPdo->query('SELECT name FROM widgets WHERE id = 1')->fetchColumn();
        $this->assertSame('gear', $name);

        unset($restoredPdo);
        array_map('unlink', glob($backupDir . '/*'));
        rmdir($backupDir);
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;

class AppRefreshCommand extends Command
{
    protected $signature = 'app:refresh
        {--no-migrate : Skip migrations}
        {--no-build : Skip the frontend build entirely}
        {--force-build : Force the frontend build even when nothing relevant changed}
        {--no-queue-restart : Skip signaling queue workers to restart}
        {--no-composer-install : Skip composer install}
        {--no-autoload : Skip composer dump-autoload when composer install is skipped}
        {--no-permissions : Skip repairing writable storage/cache directory permissions}
        {--writable-group= : Group that should own writable directories (default: APP_REFRESH_WRITABLE_GROUP or www-data when available)}
        {--production : Re-cache config/route/event/view for production after clearing}
        {--no-opcache : Skip OPcache reset}
        {--no-fpm-reload : Skip the web server reload step}
        {--fpm-service= : Override the web server systemd service name (defaults to apache2)}';

    protected $description = 'Safely refresh a deployed Lite checkout after code changes.';

    public function handle(): int
    {
        $this->components->info('Starting application refresh');
        $this->clearCaches();
        $this->clearPackageDiscoveryCaches();

        if (! $this->option('no-permissions')) {
            $this->repairWritableDirectoryPermissions();
        }

        if (! $this->option('no-composer-install')) {
            $this->runComposerInstall();

            if ($this->usesProductionDependencies()) {
                return $this->restartAfterProductionComposerInstall();
            }
        } elseif (! $this->option('no-autoload')) {
            $this->dumpAutoload();
        }

        if (! $this->option('no-migrate')) {
            $this->runMigrations();
        } else {
            $this->components->warn('Skipping migrations (--no-migrate)');
        }

        $this->buildFrontendIfNeeded();

        if ($this->option('production') || app()->isProduction()) {
            $this->reCacheForProduction();
        }

        if (! $this->option('no-queue-restart')) {
            $this->components->task('Signaling queue workers to restart', fn (): bool => Artisan::call('queue:restart', [], $this->getOutput()) === self::SUCCESS);
        }

        if (! $this->option('no-opcache') && function_exists('opcache_reset')) {
            $this->components->task('Resetting OPcache (CLI process)', static fn (): bool => @opcache_reset());
        }

        if (! $this->option('no-fpm-reload')) {
            $this->reloadWebServer();
        }

        $this->components->info('Application refresh complete');

        return self::SUCCESS;
    }

    private function clearCaches(): void
    {
        $this->components->task('Clearing caches', fn (): bool => Artisan::call('optimize:clear', [], $this->getOutput()) === self::SUCCESS);
    }

    private function clearPackageDiscoveryCaches(): void
    {
        $this->components->task('Clearing package discovery caches', function (): bool {
            foreach (['packages.php', 'services.php'] as $file) {
                $path = base_path('bootstrap/cache/' . $file);
                if (is_file($path) && ! @unlink($path)) {
                    return false;
                }
            }

            return true;
        });
    }

    private function repairWritableDirectoryPermissions(): void
    {
        $this->components->task('Repairing writable directory permissions', function (): bool {
            $group = $this->writableGroup();
            foreach ([
                storage_path('app'), storage_path('app/private'), storage_path('app/public'),
                storage_path('framework'), storage_path('framework/cache'), storage_path('framework/cache/data'),
                storage_path('framework/sessions'), storage_path('framework/testing'), storage_path('framework/views'),
                storage_path('logs'), base_path('bootstrap/cache'),
            ] as $path) {
                if (! is_dir($path) && ! @mkdir($path, 02775, true) && ! is_dir($path)) {
                    $this->components->warn("Unable to create writable directory: {$path}");
                    continue;
                }
                $this->repairPathPermissions($path, $group);
            }
            return true;
        });
    }

    private function writableGroup(): ?string
    {
        $option = $this->option('writable-group');
        if (is_string($option) && $option !== '') {
            return $option;
        }
        $configured = getenv('APP_REFRESH_WRITABLE_GROUP');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }
        return function_exists('posix_getgrnam') && posix_getgrnam('www-data') === false ? null : 'www-data';
    }

    private function repairPathPermissions(string $path, ?string $group): void
    {
        $this->repairFilesystemEntry($path, $group);
        if (! is_readable($path)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD,
        );
        foreach ($iterator as $entry) {
            if ($entry instanceof SplFileInfo) {
                $this->repairFilesystemEntry($entry->getPathname(), $group);
            }
        }
    }

    private function repairFilesystemEntry(string $path, ?string $group): void
    {
        if ($group !== null) {
            @chgrp($path, $group);
        }
        if (is_dir($path)) {
            @chmod($path, 02777);
        } elseif (is_file($path)) {
            @chmod($path, (fileperms($path) & 0777) | 0660);
        }
    }

    private function runComposerInstall(): void
    {
        $command = ['composer', 'install', '--no-interaction', '--optimize-autoloader'];
        if ($this->usesProductionDependencies()) {
            $command[] = '--no-dev';
        }
        $this->components->task('composer install', fn (): bool => $this->runProcess($command, 300));
    }

    private function usesProductionDependencies(): bool
    {
        return app()->isProduction() || $this->option('production');
    }

    private function restartAfterProductionComposerInstall(): int
    {
        $command = [PHP_BINARY, base_path('artisan'), 'app:refresh', '--no-composer-install', '--no-autoload'];

        foreach (['no-migrate', 'no-build', 'force-build', 'no-queue-restart', 'no-permissions', 'production', 'no-opcache', 'no-fpm-reload'] as $option) {
            if ($this->option($option)) {
                $command[] = '--' . $option;
            }
        }

        foreach (['writable-group', 'fpm-service'] as $option) {
            $value = $this->option($option);
            if (is_string($value) && $value !== '') {
                $command[] = '--' . $option . '=' . $value;
            }
        }

        $this->components->info('Restarting application refresh after production Composer install');

        return $this->runProcess($command, 900) ? self::SUCCESS : self::FAILURE;
    }

    private function dumpAutoload(): void
    {
        $this->components->task('composer dump-autoload -o', fn (): bool => $this->runProcess(['composer', 'dump-autoload', '-o', '--no-interaction'], 120));
    }

    private function runMigrations(): void
    {
        $this->components->task('Migrating database', fn (): bool => Artisan::call('migrate', ['--force' => true], $this->getOutput()) === self::SUCCESS);
    }

    private function buildFrontendIfNeeded(): void
    {
        if ($this->option('no-build')) {
            $this->components->warn('Skipping frontend build (--no-build)');
            return;
        }
        if (! $this->option('force-build') && ! $this->frontendChangesDetected()) {
            $this->components->info('No frontend-relevant changes detected — skipping npm build');
            return;
        }
        $this->components->task($this->option('force-build') ? 'Forcing npm run build' : 'Running npm run build (changes detected)', fn (): bool => $this->runProcess(['npm', 'run', 'build'], 600));
    }

    private function frontendChangesDetected(): bool
    {
        $manifest = base_path('public/build/manifest.json');
        if (! is_file($manifest)) {
            return true;
        }
        $threshold = (int) filemtime($manifest);
        foreach ([base_path('resources'), base_path('vite.config.js'), base_path('vite.config.ts'), base_path('package.json'), base_path('package-lock.json')] as $path) {
            if ($this->pathHasNewerFile($path, $threshold)) {
                return true;
            }
        }
        return false;
    }

    private function pathHasNewerFile(string $path, int $threshold): bool
    {
        if (! file_exists($path)) {
            return false;
        }
        if (is_file($path)) {
            return (int) filemtime($path) > $threshold;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getMTime() > $threshold) {
                return true;
            }
        }
        return false;
    }

    private function reCacheForProduction(): void
    {
        $this->components->task('Caching config/route/event/view for production', function (): bool {
            foreach (['config:cache', 'route:cache', 'event:cache', 'view:cache'] as $command) {
                if (Artisan::call($command, [], $this->getOutput()) !== self::SUCCESS) {
                    return false;
                }
            }
            return true;
        });
    }

    private function reloadWebServer(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return;
        }
        $service = $this->option('fpm-service') ?: 'apache2';
        foreach ([['sudo', '-n', 'systemctl', 'reload', $service], ['systemctl', '--no-ask-password', 'reload', $service]] as $command) {
            $process = new Process($command, base_path());
            $process->setTimeout(30);
            $process->setInput('');
            $process->run();
            if ($process->isSuccessful()) {
                $this->components->info("Reloaded web server service: {$service}");
                return;
            }
        }
        $this->components->warn("Could not reload web server ({$service}) without prompting. Run it manually or pass --no-fpm-reload.");
    }

    /** @param array<int, string> $command */
    private function runProcess(array $command, int $timeout): bool
    {
        $process = new Process($command, base_path());
        $process->setTimeout($timeout);
        $process->run(function (string $type, string $buffer): void {
            $this->getOutput()->write($buffer);
        });
        return $process->isSuccessful();
    }
}

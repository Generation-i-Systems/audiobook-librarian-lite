<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AppRefreshCommandTest extends TestCase
{
    public function testCommandIsRegistered(): void
    {
        $this->assertArrayHasKey('app:refresh', Artisan::all());
    }

    public function testAllExternalDeploymentStepsCanBeSkipped(): void
    {
        $exit = Artisan::call('app:refresh', [
            '--no-migrate' => true,
            '--no-build' => true,
            '--no-queue-restart' => true,
            '--no-composer-install' => true,
            '--no-autoload' => true,
            '--no-permissions' => true,
            '--no-opcache' => true,
            '--no-fpm-reload' => true,
        ]);

        $this->assertSame(0, $exit);
    }

    public function testCommandClearsPackageDiscoveryCachesBeforeDeploymentSteps(): void
    {
        $cacheDirectory = base_path('bootstrap/cache');
        $packageCache = $cacheDirectory . '/packages.php';
        $serviceCache = $cacheDirectory . '/services.php';
        file_put_contents($packageCache, '<?php return [];');
        file_put_contents($serviceCache, '<?php return [];');

        $exit = Artisan::call('app:refresh', [
            '--no-migrate' => true,
            '--no-build' => true,
            '--no-queue-restart' => true,
            '--no-composer-install' => true,
            '--no-autoload' => true,
            '--no-permissions' => true,
            '--no-opcache' => true,
            '--no-fpm-reload' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertFileDoesNotExist($packageCache);
        $this->assertFileDoesNotExist($serviceCache);
    }
}

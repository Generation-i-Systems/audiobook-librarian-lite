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
}

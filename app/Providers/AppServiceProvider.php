<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! app()->runningUnitTests()) {
            // Set umask to ensure newly created cache/view files are group/world writable
            @umask(0002);

            // Prevent permission warnings on touch() in BladeCompiler from crashing view rendering
            $previousErrorHandler = set_error_handler(function (int $level, string $message, string $file = '', int $line = 0) use (&$previousErrorHandler) {
                if (str_contains($message, 'touch()') && (str_contains($file, 'BladeCompiler.php') || str_contains($message, 'framework/views'))) {
                    return true;
                }

                return $previousErrorHandler ? $previousErrorHandler($level, $message, $file, $line) : false;
            });
        }

        // Register custom Documentstore user provider
        Auth::provider('documentstore', function ($app, array $config) {
            return new \App\Auth\DocumentUserProvider(
                $app->make(\App\Contracts\DocumentStoreServiceInterface::class)
            );
        });

        if (app()->runningUnitTests()) {
            $defaultConnection = (string) config('database.default');
            $sqliteDatabase = (string) config('database.connections.sqlite.database');

            if ($defaultConnection !== 'sqlite' || $sqliteDatabase !== ':memory:') {
                throw new \RuntimeException(
                    'CRITICAL SAFETY FAILURE: Tests must run on in-memory SQLite. ' .
                    "Got database.default='{$defaultConnection}', sqlite.database='{$sqliteDatabase}'."
                );
            }
        }

        // Dynamically set the application's base URL based on the incoming request.
        // This allows the app to respond correctly via multiple domains
        // (like books.thelin.org, api.ablibrarian.com, etc.)
        if (! app()->runningInConsole()) {
            $scheme = (bool) config('app.force_https', true) ? 'https' : request()->getScheme();
            URL::forceRootUrl($scheme . '://' . request()->getHost());
        }

        // Force HTTPS scheme for generated links when enabled (but not during unit tests)
        if ((bool) config('app.force_https', true) && ! app()->runningUnitTests()) {
            URL::forceScheme('https');
        }
    }
}

<?php

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
        if (app()->runningUnitTests()) {
            $defaultConnection = (string) config('database.default');
            $sqliteDatabase = (string) config('database.connections.sqlite.database');

            if ($defaultConnection !== 'sqlite' || $sqliteDatabase !== ':memory:') {
                throw new \RuntimeException(
                    "CRITICAL SAFETY FAILURE: Tests must run on in-memory SQLite. " .
                    "Got database.default='{$defaultConnection}', sqlite.database='{$sqliteDatabase}'."
                );
            }
        }

        // Dynamically set the application's base URL based on the incoming request.
        // This allows the app to respond correctly via multiple domains
        // (like books.thelin.org, api.ablibrarian.com, etc.)
        if (!app()->runningInConsole()) {
            $scheme = (bool) config('app.force_https', true) ? 'https' : request()->getScheme();
            URL::forceRootUrl($scheme . '://' . request()->getHost());
        }

        // Force HTTPS scheme for generated links when enabled (but not during unit tests)
        if ((bool) config('app.force_https', true) && !app()->runningUnitTests()) {
            URL::forceScheme('https');
        }
    }
}

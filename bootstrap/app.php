<?php

declare(strict_types=1);

// CRITICAL: Run database safety check BEFORE Laravel bootstrap
require_once __DIR__ . '/database-safety-check.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function ($schedule): void {
        // Run database backup nightly at 2:00 AM
        $schedule->command('backup:database --verify')
            ->dailyAt('02:00')
            ->appendOutputTo(storage_path('logs/backup-cron.log'));

        // Run database backup weekly with extra verification on Sundays at 3:00 AM
        $schedule->command('backup:database --verify')
            ->weeklyOn(0, '03:00')
            ->appendOutputTo(storage_path('logs/backup-cron.log'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\ResolveLibraryProfileFromHost::class);
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request): string {
            return $request->is('admin/*') ? route('admin.login') : route('login');
        });

        $middleware->validateCsrfTokens(except: [
            'admin/adminer*',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\CheckAdminRole::class,
            'standard' => \App\Http\Middleware\RequireLibraryRole::class,
            'library' => \App\Http\Middleware\RequireLibraryRole::class,
            'active' => \App\Http\Middleware\EnsureActiveUser::class,
            'api.auth' => \App\Http\Middleware\ApiAuth::class,
            'idempotency' => \App\Http\Middleware\CheckIdempotency::class,
            'device.identify' => \App\Http\Middleware\IdentifyDevice::class,
        ]);
    })
    ->withProviders([])
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ensure API routes return JSON errors
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $statusCode = 500;
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'error' => true,
                        'message' => $e->getMessage(),
                        'errors' => $e->errors(),
                    ], 422);
                }

                if (method_exists($e, 'getStatusCode')) {
                    $statusCode = $e->getStatusCode();
                }

                return response()->json([
                    'error' => true,
                    'message' => $e->getMessage(),
                    'code' => $e->getCode() ?: $statusCode,
                ], $statusCode);
            }
        });

        // Prevent infinite loops when logging fails due to permission issues
        $exceptions->report(function (\Throwable $e) {
            $message = $e->getMessage();
            $file = $e->getFile();

            // Handle Monolog StreamHandler exceptions
            if (
                $e instanceof \UnexpectedValueException &&
                (str_contains($message, 'could not be opened in append mode') ||
                    str_contains($message, 'Permission denied') ||
                    str_contains($message, 'No such file or directory')) &&
                str_contains($message, 'logs')
            ) {
                return false;
            }

            // Handle general file operation exceptions in logs directory
            if (
                (str_contains($message, 'fopen') ||
                    str_contains($message, 'Permission denied') ||
                    str_contains($message, 'No such file or directory')) &&
                (str_contains($message, 'logs') || str_contains($file, 'logs'))
            ) {
                return false;
            }

            // Handle ErrorException for file operations
            if (
                $e instanceof \ErrorException &&
                (str_contains($message, 'Permission denied') ||
                    str_contains($message, 'No such file or directory')) &&
                (str_contains($message, 'logs') || str_contains($file, 'logs'))
            ) {
                return false;
            }

            return null;
        });
    })
    ->create();

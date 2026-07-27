<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AccountRequestController;
use App\Http\Controllers\AccountDeletionCancellationController;
use App\Http\Controllers\Api\EmailOtpController;
use Illuminate\Support\Facades\Route;

// Magic link OTP web routes (no auth required)
Route::get('/auth/magic/{token}', [EmailOtpController::class, 'magicLanding'])
    ->where('token', '[a-f0-9]{64}')
    ->name('auth.magic.landing');

Route::post('/auth/magic/{token}/continue', [EmailOtpController::class, 'magicContinue'])
    ->where('token', '[a-f0-9]{64}')
    ->middleware('web')
    ->name('auth.magic.continue');

Route::post('/auth/otp/request', [EmailOtpController::class, 'request'])
    ->name('auth.otp.request');

Route::get('/account-deletion/cancel/{token}', [AccountDeletionCancellationController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{64}');
Route::post('/account-deletion/cancel/{token}', [AccountDeletionCancellationController::class, 'cancel'])
    ->where('token', '[A-Za-z0-9]{64}');
Route::get('/account-deletion/cancelled', fn () => view('account-deletion.cancelled'));

Route::get('/', function () {
    return response()->json(['service' => 'AbLibrarian Lite', 'status' => 'ok']);
});

Route::middleware(['web', 'auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('account-requests')->name('account_requests.')->group(function () {
        Route::get('/', [AccountRequestController::class, 'index'])->name('index');
        Route::put('/{account_request}', [AccountRequestController::class, 'update'])->name('update');
        Route::delete('/{account_request}', [AccountRequestController::class, 'destroy'])->name('destroy');
    });
});

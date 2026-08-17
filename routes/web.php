<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\UserController as AdminUserWebController;
use App\Http\Controllers\AccountDeletionCancellationController;
use App\Http\Controllers\Api\EmailOtpController;
use App\Http\Controllers\AppConnectController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistrationController;
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

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::get('/register', [RegistrationController::class, 'create'])->middleware('guest')->name('register');
Route::post('/register', [RegistrationController::class, 'store'])->middleware(['guest', 'throttle:5,1'])->name('register.store');
Route::get('/app/connect/server', [AppConnectController::class, 'server'])->name('app.connect.server');
Route::get('/admin/login', [AdminLoginController::class, 'create'])->middleware('guest')->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'store'])->middleware(['guest', 'throttle:5,1'])->name('admin.login.store');
Route::post('/admin/logout', [AdminLoginController::class, 'destroy'])->middleware('auth')->name('admin.logout');

Route::get('/', function () {
    return view('landing');
})->name('landing');

Route::middleware(['web', 'auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', AdminUserWebController::class);
    Route::post('users/{id}/verify', [AdminUserWebController::class, 'verify'])
        ->name('users.verify');
});

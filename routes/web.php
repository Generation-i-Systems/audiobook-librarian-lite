<?php

declare(strict_types=1);

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

Route::get('/', function () {
    return response()->json(['service' => 'AbLibrarian Lite', 'status' => 'ok']);
});

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AdminGroupController;
use App\Http\Controllers\Api\BookTagController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\ApiCapabilitiesController;
use App\Http\Controllers\Api\ApiHealthController;
use App\Http\Controllers\Api\ApiRootController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BadgeApiController;
use App\Http\Controllers\Api\BookmarkApiController;
use App\Http\Controllers\Api\BookmarkSyncController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\EmailOtpController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FriendController;
use App\Http\Controllers\Api\ListeningGoalController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PositionSyncController;
use App\Http\Controllers\Api\ReadingStatsApiController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\DocsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // API Root
    Route::get('/', [ApiRootController::class, 'index'])->name('api.v1.root');
    Route::get('/openapi.json', [DocsController::class, 'openapi'])->name('api.v1.openapi');

    // Health check endpoints (no authentication required)
    Route::get('/health/ping', [ApiHealthController::class, 'ping']);
    Route::get('/health', [ApiHealthController::class, 'health']);
    Route::get('/health/validate', [ApiHealthController::class, 'validateSpec']);
    Route::get('/health/capabilities', [ApiCapabilitiesController::class, 'capabilities']);

    // Authentication Routes (outside auth middleware)
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/check-status', [AuthController::class, 'checkStatus']);
        Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
        Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
        Route::post('/auth/otp/request', [EmailOtpController::class, 'request']);
        Route::post('/auth/otp/verify', [EmailOtpController::class, 'verify']);
    });

    // Backwards compatible auth-prefixed routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
        Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
        Route::post('/otp/request', [EmailOtpController::class, 'request']);
        Route::post('/otp/verify', [EmailOtpController::class, 'verify']);
    });

    Route::middleware(['api.auth', 'standard'])->group(function () {
        Route::get('/user', function (Request $request) {
            $user = $request->user();
            $user->setAttribute(
                'groups',
                $user->groups()->get(['groups.id', 'groups.name'])
            );

            return $user;
        });

        // Authenticated: set initial password
        Route::post('/auth/set-initial-password', [EmailOtpController::class, 'setInitialPassword']);

        // Logout
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Account deletion (verify by email OTP, then schedule; cancellable within retention period)
        Route::post('/account', [AuthController::class, 'requestAccountDeletionVerification']);
        Route::delete('/account', [AuthController::class, 'deleteAccount']);
        Route::delete('/auth/account', [AuthController::class, 'deleteAccount']);

        // Book Tag Routes (keyed by title+author — lite has no book table)
        Route::get('/books/tags', [BookTagController::class, 'show']);
        Route::put('/books/tags', [BookTagController::class, 'update']);
        Route::get('/tags/popular', [BookTagController::class, 'popular']);

        // Listening Goals Routes
        Route::prefix('goals/listening')->group(function () {
            Route::get('/', [ListeningGoalController::class, 'index']);
            Route::get('/history', [ListeningGoalController::class, 'history']);
            Route::post('/', [ListeningGoalController::class, 'store']);
            Route::put('/{goal}', [ListeningGoalController::class, 'update']);
            Route::delete('/{goal}', [ListeningGoalController::class, 'destroy']);
            Route::get('/{goal}/breakdown', [ListeningGoalController::class, 'breakdown']);
        });

        // Badge Routes
        Route::prefix('badges')->group(function () {
            Route::get('/', [BadgeApiController::class, 'index']);
            Route::get('/user', [BadgeApiController::class, 'userBadges']);
            Route::get('/unnotified', [BadgeApiController::class, 'unnotified']);
            Route::post('/mark-notified', [BadgeApiController::class, 'markNotified']);
        });

        // Push notification token registration (no real provider wired up yet)
        Route::put('/push-token', [DeviceController::class, 'registerPushToken']);

        // Friend Group Routes
        Route::prefix('friends')->group(function () {
            Route::get('/', [FriendController::class, 'index']);
            Route::delete('/{userId}', [FriendController::class, 'destroy'])->where('userId', '[0-9]+');
            Route::post('/qr', [FriendController::class, 'createQrInvite']);
            Route::post('/qr/{token}/join', [FriendController::class, 'joinViaQr']);
            Route::post('/invitations', [FriendController::class, 'sendInvitation']);
            Route::get('/invitations', [FriendController::class, 'listInvitations']);
            Route::get('/invitations/unshown', [FriendController::class, 'unshownInvitations']);
            Route::post('/invitations/mark-shown', [FriendController::class, 'markInvitationsShown']);
            Route::post('/invitations/{invitationId}/accept', [FriendController::class, 'acceptInvitation'])->where('invitationId', '[0-9]+');
            Route::post('/invitations/{invitationId}/decline', [FriendController::class, 'declineInvitation'])->where('invitationId', '[0-9]+');
        });

        // Statistics Routes
        Route::get('/statistics/overview', [StatisticsController::class, 'getOverview']);
        Route::get('/statistics/daily', [StatisticsController::class, 'getDailyStatsOpenApi']);
        Route::get('/statistics/reading-history', [StatisticsController::class, 'getReadingHistoryStats']);

        // Device Management and Sync Routes
        Route::middleware('device.identify')->group(function () {
            Route::get('/devices', [DeviceController::class, 'index']);
            Route::put('/devices/{deviceId}', [DeviceController::class, 'update']);
            Route::delete('/devices/{deviceId}', [DeviceController::class, 'destroy']);
            Route::put('/devices/{deviceId}/sync-enabled', [DeviceController::class, 'updateSyncEnabled']);

            Route::prefix('sync')->group(function () {
                // Event Sync Routes
                Route::post('/events', [EventController::class, 'sync'])->middleware('idempotency');
                Route::get('/events/book', [EventController::class, 'getBookEvents']);
                Route::get('/events/stats', [EventController::class, 'getStats']);

                // Position Sync Routes
                Route::get('/positions', [PositionSyncController::class, 'index']);
                Route::get('/positions/show', [PositionSyncController::class, 'show']);
                Route::post('/positions', [PositionSyncController::class, 'store'])->middleware('idempotency');

                // Bookmark Sync Routes
                Route::get('/bookmarks', [BookmarkSyncController::class, 'index']);
                Route::get('/bookmarks/show', [BookmarkSyncController::class, 'show']);
                Route::post('/bookmarks', [BookmarkSyncController::class, 'store'])->middleware('idempotency');
                Route::delete('/bookmarks/{stringId}', [BookmarkSyncController::class, 'destroy']);
            });
        });

        // Bookmark Routes
        Route::get('/bookmarks', [BookmarkApiController::class, 'getBookmarksOpenApi']);
        Route::post('/bookmarks', [BookmarkApiController::class, 'createBookmarkOpenApi']);
        Route::delete('/bookmarks/{bookmark}', [BookmarkApiController::class, 'deleteBookmarkById']);

        // Reading Stats Routes
        Route::get('/reading-stats/daily', [ReadingStatsApiController::class, 'getDaily']);
        Route::get('/reading-stats/user', [ReadingStatsApiController::class, 'getUserStats']);
        Route::get('/reading-stats/streaks', [ReadingStatsApiController::class, 'getStreaks']);

        // Admin: Group management routes
        Route::middleware('admin')->prefix('admin/groups')->group(function () {
            Route::get('/', [AdminGroupController::class, 'index']);
            Route::get('/{group}', [AdminGroupController::class, 'show']);
            Route::post('/', [AdminGroupController::class, 'store']);
            Route::post('/{group}/members', [AdminGroupController::class, 'addMember']);
            Route::delete('/{group}/members/{user}', [AdminGroupController::class, 'removeMember']);
        });

        // Admin: User management routes
        Route::middleware('admin')->prefix('admin/users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']);
            Route::post('/', [AdminUserController::class, 'store']);
            Route::post('/{id}/send-otp', [AdminUserController::class, 'sendOtp']);
            Route::post('/{id}/send-welcome', [AdminUserController::class, 'sendWelcome']);
            Route::post('/{id}/verify', [AdminUserController::class, 'verify']);
        });
    });
});

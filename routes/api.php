<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ApiCapabilitiesController;
use App\Http\Controllers\Api\ApiHealthController;
use App\Http\Controllers\Api\ApiRootController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BadgeController;
use App\Http\Controllers\Api\BookmarkApiController;
use App\Http\Controllers\Api\BookmarkSyncController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\EmailOtpController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\ExternalReadApiController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\ListeningGoalController;
use App\Http\Controllers\Api\MessageApiController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PositionSyncController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\ReadingProgressApiController;
use App\Http\Controllers\Api\ReadingStatsApiController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\UserStatusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // API Root
    Route::get('/', [ApiRootController::class, 'index'])->name('api.v1.root');

    // Health check endpoints (no authentication required)
    Route::get('/health/ping', [ApiHealthController::class, 'ping']);
    Route::get('/health', [ApiHealthController::class, 'health']);
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
        Route::post('/auth/google', [AuthController::class, 'googleLogin']);
        Route::post('/auth/facebook', [AuthController::class, 'facebookLogin']);
        Route::post('/auth/apple', [AuthController::class, 'appleLogin']);
        Route::post('/auth/discord', [AuthController::class, 'discordLogin']);
    });

    // Backwards compatible auth-prefixed routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/check-status', [AuthController::class, 'checkStatus']);
        Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
        Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
        Route::post('/otp/request', [EmailOtpController::class, 'request']);
        Route::post('/otp/verify', [EmailOtpController::class, 'verify']);
        Route::post('/google', [AuthController::class, 'googleLogin']);
        Route::post('/facebook', [AuthController::class, 'facebookLogin']);
        Route::post('/apple', [AuthController::class, 'appleLogin']);
        Route::post('/discord', [AuthController::class, 'discordLogin']);
    });

    Route::middleware(['api.auth', 'standard'])->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        // User Profile Routes
        Route::get('/me', [UserApiController::class, 'me']);

        // User Status Routes
        Route::prefix('status')->group(function () {
            Route::get('/list/{statusType}', [UserStatusController::class, 'list']);
            Route::get('/history', [UserStatusController::class, 'history']);
            Route::get('/goals', [UserStatusController::class, 'goals']);
            Route::post('/{book}/set', [UserStatusController::class, 'set']);
            Route::post('/non-library/set', [UserStatusController::class, 'setNonLibrary']);
            Route::post('/queue/reorder', [UserStatusController::class, 'reorder']);
        });

        // Listening Goals Routes
        Route::prefix('goals/listening')->group(function () {
            Route::get('/', [ListeningGoalController::class, 'index']);
            Route::post('/', [ListeningGoalController::class, 'store']);
            Route::put('/{goal}', [ListeningGoalController::class, 'update']);
            Route::delete('/{goal}', [ListeningGoalController::class, 'destroy']);
        });

        // Sync Routes
        Route::prefix('sync')->middleware('idempotency')->group(function () {
            Route::post('/progress', [ProgressController::class, 'updateBookProgress']);
        });

        // Device Management and Sync Routes
        Route::middleware('device.identify')->group(function () {
            Route::get('/devices', [DeviceController::class, 'index']);
            Route::put('/devices/{deviceId}', [DeviceController::class, 'update']);
            Route::delete('/devices/{deviceId}', [DeviceController::class, 'destroy']);
            Route::put('/devices/{deviceId}/sync-enabled', [DeviceController::class, 'updateSyncEnabled']);

            Route::prefix('sync')->group(function () {
                // Position Sync Routes
                Route::get('/positions', [PositionSyncController::class, 'index']);
                Route::get('/positions/{bookId}', [PositionSyncController::class, 'show']);
                Route::post('/positions', [PositionSyncController::class, 'store'])->middleware('idempotency');

                // Bookmark Sync Routes
                Route::get('/bookmarks', [BookmarkSyncController::class, 'index']);
                Route::get('/bookmarks/{bookId}', [BookmarkSyncController::class, 'show']);
                Route::post('/bookmarks', [BookmarkSyncController::class, 'store'])->middleware('idempotency');
                Route::delete('/bookmarks/{stringId}', [BookmarkSyncController::class, 'destroy']);

                // Event Sync Routes
                Route::post('/events', [EventController::class, 'sync'])->middleware('idempotency');
                Route::get('/events/book/{bookId}', [EventController::class, 'getBookEvents']);
                Route::get('/events/stats', [EventController::class, 'getStats']);
            });
        });

        // Reading Progress Routes
        Route::post('/reading-progress/reset', [ReadingProgressApiController::class, 'reset']);
        Route::post('/reading-progress/{book}', [ReadingProgressApiController::class, 'update']);
        Route::get('/reading-progress/{book}', [ReadingProgressApiController::class, 'get']);

        // Bookmark Routes
        Route::get('/bookmarks/{book}', [BookmarkApiController::class, 'getBookmarksOpenApi']);
        Route::post('/bookmarks/{book}', [BookmarkApiController::class, 'createBookmarkOpenApi']);
        Route::delete('/bookmarks/{bookmark}', [BookmarkApiController::class, 'deleteBookmarkById']);

        // Legacy bookmark routes
        Route::get('/books/{book}/bookmarks', [BookmarkApiController::class, 'getBookmarks']);
        Route::post('/books/{book}/bookmarks', [BookmarkApiController::class, 'createBookmark']);
        Route::get('/books/{book}/bookmarks/{bookmark}', [BookmarkApiController::class, 'getBookmark']);
        Route::put('/books/{book}/bookmarks/{bookmark}', [BookmarkApiController::class, 'updateBookmark']);
        Route::patch('/books/{book}/bookmarks/{bookmark}', [BookmarkApiController::class, 'updateBookmark']);
        Route::delete('/books/{book}/bookmarks/{bookmark}', [BookmarkApiController::class, 'deleteBookmark']);

        // External/Previously Read Routes
        Route::get('/books/{book}/external-reads', [ExternalReadApiController::class, 'getExternalReads']);
        Route::post('/external-reads', [ExternalReadApiController::class, 'createExternalReadNonLibrary']);
        Route::post('/books/{book}/external-reads', [ExternalReadApiController::class, 'createExternalRead']);
        Route::get('/books/{book}/external-reads/{externalRead}', [ExternalReadApiController::class, 'getExternalRead']);
        Route::put('/books/{book}/external-reads/{externalRead}', [ExternalReadApiController::class, 'updateExternalRead']);
        Route::patch('/books/{book}/external-reads/{externalRead}', [ExternalReadApiController::class, 'updateExternalRead']);
        Route::delete('/books/{book}/external-reads/{externalRead}', [ExternalReadApiController::class, 'deleteExternalRead']);

        // Reading Stats Routes
        Route::post('/books/{book}/reading-stats/sessions', [ReadingStatsApiController::class, 'recordSession']);
        Route::get('/reading-stats/daily', [ReadingStatsApiController::class, 'getDaily']);
        Route::get('/books/{book}/reading-stats', [ReadingStatsApiController::class, 'getBookStats']);
        Route::get('/reading-stats/user', [ReadingStatsApiController::class, 'getUserStats']);
        Route::get('/reading-stats/streaks', [ReadingStatsApiController::class, 'getStreaks']);

        // Progress Routes
        Route::get('/progress/device', [ProgressController::class, 'getDeviceProgress']);
        Route::get('/progress/device/{deviceId}', [ProgressController::class, 'getDeviceProgressByPath']);
        Route::get('/progress', [ProgressController::class, 'getAllProgress']);
        Route::post('/progress/{book}/mark-completed', [ProgressController::class, 'markCompletedByPath']);
        Route::get('/progress/{book}', [ProgressController::class, 'getBookProgress']);
        Route::put('/progress/{book}', [ProgressController::class, 'updateBookProgress'])->middleware('idempotency');

        // Book Progress Routes (legacy)
        Route::get('/books/{book}/progress', [ProgressController::class, 'getProgress']);
        Route::put('/books/{book}/progress', [ProgressController::class, 'updateProgress']);
        Route::post('/books/{book}/progress/complete', [ProgressController::class, 'markCompleted']);
        Route::delete('/books/{book}/progress', [ProgressController::class, 'resetProgress']);

        // Statistics Routes
        Route::get('/statistics/overview', [StatisticsController::class, 'getOverview']);
        Route::get('/statistics/daily', [StatisticsController::class, 'getDailyStatsOpenApi']);
        Route::get('/statistics/timeline', [StatisticsController::class, 'getTimelineStats']);
        Route::get('/statistics/timeline/day', [StatisticsController::class, 'getDayTimeline']);
        Route::get('/statistics/reading-history', [StatisticsController::class, 'getReadingHistoryStats']);
        Route::get('/statistics/diagnostics', [StatisticsController::class, 'getDiagnostics']);
        Route::post('/statistics/report', [StatisticsController::class, 'reportSession']);
        Route::post('/statistics/sessions', [StatisticsController::class, 'recordSession']);
        Route::get('/statistics/legacy-daily', [StatisticsController::class, 'getDailyStats']);
        Route::get('/statistics/weekly', [StatisticsController::class, 'getWeeklyStats']);
        Route::get('/statistics/trends', [StatisticsController::class, 'getListeningTrends']);
        Route::get('/statistics/top-books', [StatisticsController::class, 'getTopBooks']);
        Route::get('/statistics/dashboard', [StatisticsController::class, 'getDashboardStats']);
        Route::get('/books/{book}/statistics', [StatisticsController::class, 'getBookStats']);

        // Recommendation Routes
        Route::prefix('recommendations')->group(function () {
            Route::post('/{book}', [RecommendationController::class, 'send']);
            Route::get('/inbox', [RecommendationController::class, 'inbox']);
            Route::post('/{recommendation}/acknowledge', [RecommendationController::class, 'acknowledge']);
        });
        Route::post('/books/{book}/recommend', [RecommendationController::class, 'send']);

        // Badge Routes
        Route::prefix('badges')->group(function () {
            Route::get('/', [BadgeController::class, 'index']);
            Route::get('/user', [BadgeController::class, 'userBadges']);
            Route::get('/stats', [BadgeController::class, 'userStats']);
            Route::get('/categories', [BadgeController::class, 'byCategory']);
            Route::get('/progress', [BadgeController::class, 'progress']);
            Route::get('/unnotified', [BadgeController::class, 'unnotified']);
            Route::post('/mark-notified', [BadgeController::class, 'markNotified']);
            Route::get('/leaderboard', [BadgeController::class, 'leaderboard']);
        });

        // Analytics Routes
        Route::post('/analytics/event', [AnalyticsController::class, 'recordEvent']);

        // Message Routes
        Route::get('/messages', [MessageApiController::class, 'index']);
        Route::post('/messages', [MessageApiController::class, 'store']);
        Route::post('/messages/{id}/acknowledge', [MessageApiController::class, 'acknowledge']);

        // Admin: User management routes
        Route::middleware('admin')->prefix('admin/users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']);
            Route::post('/', [AdminUserController::class, 'store']);
            Route::post('/{id}/send-otp', [AdminUserController::class, 'sendOtp']);
        });

        // Authenticated: set initial password
        Route::post('/auth/set-initial-password', [EmailOtpController::class, 'setInitialPassword']);

        // Feedback
        Route::post('/feedback', [FeedbackController::class, 'submit']);

        // Logout
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });
});

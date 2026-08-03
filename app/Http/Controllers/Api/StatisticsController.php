<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListeningEvent;
use App\Models\ListeningStatistic;
use App\Models\UserBookStatus;
use App\Models\User;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\BookCompletionService;
use App\Services\ControllerDatabaseService as ControllerDatabase;
use App\Services\ListeningActivityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StatisticsController extends Controller
{
    public function __construct(
        private readonly ListeningActivityService $listeningActivityService,
        private readonly BookCompletionService $bookCompletionService
    ) {
    }

    private function listeningStatsQuery(?int $userId, string $deviceId): \Illuminate\Database\Eloquent\Builder
    {
        $query = ListeningStatistic::query();

        if ($userId !== null) {
            $userDeviceIds = ControllerDatabase::table('devices')
                ->where('user_id', $userId)
                ->pluck('device_id')
                ->push($deviceId)
                ->filter()
                ->unique()
                ->values();

            $query->where(function ($statsQuery) use ($userId, $userDeviceIds) {
                $statsQuery->where('user_id', $userId);

                if ($userDeviceIds->isNotEmpty()) {
                    $statsQuery->orWhereIn('device_id', $userDeviceIds);
                }
            });
        } else {
            $query->where('device_id', $deviceId);
        }

        return $query;
    }

    /**
     * Get listening statistics overview (OpenAPI spec)
     */
    public function getOverview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|string|in:week,month,quarter,year,all_time',
        ]);

        $period = $validated['period'] ?? 'month';
        $userId = Auth::id();
        $deviceId = (string) ($request->header('X-Device-ID', 'unknown'));

        // Calculate date ranges based on period
        switch ($period) {
            case 'week':
                $startDate = now()->startOfWeek();
                break;
            case 'quarter':
                $startDate = now()->startOfQuarter();
                break;
            case 'year':
                $startDate = now()->startOfYear();
                break;
            case 'all_time':
                $startDate = null;
                break;
            case 'month':
            default:
                $startDate = now()->startOfMonth();
                break;
        }

        $query = $this->listeningStatsQuery($userId, $deviceId);

        if ($startDate) {
            $query->where('listening_date', '>=', $startDate->toDateString());
        }

        $stats = $query->selectRaw('
            SUM(seconds_listened) as total_listening_time_ms,
            AVG(seconds_listened) as average_session_duration_ms,
            COUNT(DISTINCT listening_date) as days_with_activity
        ')->first();

        $booksStarted = (clone $query)->select('title', 'author')->distinct()->count();

        if ($userId !== null) {
            $booksFinished = $this->bookCompletionService->getCompletedBookDatesForUser($userId, $startDate)->count();
        } else {
            $booksFinished = $this->bookCompletionService->completedProgressQuery($userId, $deviceId, $startDate)
                ->select('title', 'author')
                ->distinct()
                ->count();
        }

        // Calculate streaks
        $currentStreak = $this->calculateCurrentStreak($userId, $deviceId);
        $longestStreak = $this->calculateLongestStreak($userId, $deviceId);

        // Get favorite genres (top 5 most listened)
        $favoriteGenres = $this->getFavoriteGenres($userId, $deviceId, $startDate);

        // Get daily stats for the period
        $dailyStats = $this->getDailyStatsForPeriod($userId, $deviceId, $startDate);
        $listeningMinutes = $this->getListeningMinutesBreakdown($userId, $deviceId);

        return response()->json([
            'daily_stats'                 => $dailyStats,
            'total_listening_time_ms'     => ($stats->total_listening_time_ms ?? 0) * 1000,
            'books_started'               => $booksStarted,
            'books_finished'              => $booksFinished,
            'average_session_duration_ms' => ($stats->average_session_duration_ms ?? 0) * 1000,
            'favorite_genres'             => $favoriteGenres,
            'current_streak'              => $currentStreak,
            'longest_streak'              => $longestStreak,
            'listening_minutes'           => $listeningMinutes,
        ]);
    }

    /**
     * Get daily listening statistics (OpenAPI spec)
     */
    public function getDailyStatsOpenApi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d',
            'limit'      => 'nullable|integer|min:1|max:365',
        ]);

        $userId = Auth::id();
        $deviceId = (string) ($request->header('X-Device-ID', 'unknown'));

        $startDate = ($validated['start_date'] ?? null) ? Carbon::parse($validated['start_date']) : now()->subDays(29);
        $endDate   = ($validated['end_date'] ?? null) ? Carbon::parse($validated['end_date']) : now();
        $limit     = $validated['limit'] ?? 30;

        $startDateString = $startDate->toDateString();
        $endDateString   = $endDate->toDateString();

        $sessions = $this->listeningActivityService->getSessions($userId, $deviceId)
            ->filter(static fn (object $session): bool => $session->listening_date >= $startDateString
                && $session->listening_date <= $endDateString);

        $dailyStats = $sessions->groupBy('listening_date')
            ->map(static fn (Collection $daySessions, string $date): array => [
                'date'              => $date,
                'listening_time_ms' => $daySessions->sum('seconds_listened') * 1000,
                'sessions_count'    => $daySessions->count(),
                'books_listened'    => $daySessions
                    ->map(static fn (object $session): array => ['title' => $session->title, 'author' => $session->author])
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->sortKeysDesc()
            ->take($limit)
            ->values();

        return response()->json([
            'daily_stats' => $dailyStats,
        ]);
    }

    /**
     * Get timeline listening statistics, grouped by day or month
     */
    public function getTimelineStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from'               => 'nullable|date_format:Y-m-d',
            'to'                 => 'nullable|date_format:Y-m-d',
            'group_by'           => 'nullable|string|in:day,week,month,year',
            'detail_period_type' => 'nullable|string|in:day,week,month,year',
            'detail_period'      => 'nullable|string|max:20',
            'day_filter'         => 'nullable|string|in:any,weekday,weekend',
            'weekdays'           => 'nullable|array',
            'weekdays.*'         => 'integer|between:0,6',
        ]);

        $userId   = Auth::id();
        $deviceId = (string) ($request->header('X-Device-ID', 'unknown'));
        $from     = isset($validated['from']) ? Carbon::parse($validated['from']) : now()->subDays(29);
        $to       = isset($validated['to']) ? Carbon::parse($validated['to']) : now();
        $groupBy  = $validated['group_by'] ?? 'day';
        $dayFilter = $validated['day_filter'] ?? 'any';
        $weekdays = collect($validated['weekdays'] ?? [])->map(static fn ($day): int => (int) $day)->unique()->values()->all();

        $sessions = $this->filterSessionsForTimeline(
            $this->listeningActivityService->getSessions($userId, $deviceId),
            $from,
            $to,
            $dayFilter,
            $weekdays
        );

        $totalMs = $sessions->sum('seconds_listened') * 1000;
        $totalSessions = $sessions->count();

        $bars = $sessions
            ->groupBy(fn (object $session): string => $this->timelinePeriodKey($session->listening_date, $groupBy))
            ->map(static fn (Collection $periodSessions, string $period): array => [
                'period'            => $period,
                'listening_time_ms' => $periodSessions->sum('seconds_listened') * 1000,
                'sessions_count'    => $periodSessions->count(),
            ])
            ->sortKeys()
            ->values();

        $detail = null;
        if (isset($validated['detail_period_type'], $validated['detail_period'])) {
            $detail = $this->getTimelinePeriodDetails(
                $userId,
                $deviceId,
                $validated['detail_period_type'],
                $validated['detail_period'],
                $dayFilter,
                $weekdays
            );
        }

        return response()->json([
            'bars'    => $bars,
            'summary' => [
                'total_listening_time_ms' => (int) $totalMs,
                'total_sessions'          => $totalSessions,
                'from'                    => $from->toDateString(),
                'to'                      => $to->toDateString(),
                'group_by'                => $groupBy,
                'day_filter'              => $dayFilter,
                'weekdays'                => $weekdays,
            ],
            'detail' => $detail,
        ]);
    }

    /**
     * Get a day-level timeline: listening segments for a specific date.
     *
     * Each segment represents a contiguous listening interval derived from raw events.
     * Book titles are resolved server-side. Events from all devices for the authenticated
     * user are included and grouped per device to avoid cross-device interference.
     */
    public function getDayTimeline(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'     => 'required|date_format:Y-m-d',
            'timezone' => 'nullable|string|max:60',
        ]);

        $userId   = Auth::id();
        $timezone = $this->resolveTimezone($validated['timezone'] ?? null);
        $date     = Carbon::createFromFormat('Y-m-d', $validated['date'], $timezone)->startOfDay();
        $dayStartMs = $date->copy()->setTimezone('UTC')->getTimestampMs();
        $dayEndMs   = $date->copy()->addDay()->setTimezone('UTC')->getTimestampMs();

        $events = ListeningEvent::query()
            ->where('user_id', $userId)
            ->whereBetween('timestamp_ms', [$dayStartMs, $dayEndMs])
            ->whereIn('event_type', [
                'PLAY_START', 'PLAY_RESUME', 'SESSION_START',
                'PLAY_PAUSE', 'PLAY_STOP', 'SESSION_END', 'CHAPTER_CHANGE',
            ])
            ->orderBy('timestamp_ms')
            ->get();

        $nowMs = (int) (microtime(true) * 1000);

        $segments = $this->buildDaySegments($events->all(), $dayStartMs, $dayEndMs, $nowMs);

        return response()->json([
            'date'               => $validated['date'],
            'timezone'           => $timezone,
            'total_listening_ms' => array_sum(array_column($segments, 'duration_ms')),
            'segments'           => $segments,
        ]);
    }

    private function resolveTimezone(?string $tz): string
    {
        if ($tz === null) {
            return 'UTC';
        }
        try {
            new DateTimeZone($tz);
            return $tz;
        } catch (\Exception) {
            return 'UTC';
        }
    }

    /**
     * Reconstruct listening segments from raw events, grouped by device.
     *
     * @param  \App\Models\ListeningEvent[]  $events  Sorted by timestamp_ms ascending
     */
    private function buildDaySegments(
        array $events,
        int $dayStartMs,
        int $dayEndMs,
        int $nowMs
    ): array {
        $openEventTypes  = ['PLAY_START', 'PLAY_RESUME', 'SESSION_START'];
        $closeEventTypes = ['PLAY_PAUSE', 'PLAY_STOP', 'SESSION_END'];
        $minSegmentMs    = 5_000;

        $byDevice = [];
        foreach ($events as $event) {
            $byDevice[$event->device_id][] = $event;
        }

        $allSegments = [];
        foreach ($byDevice as $deviceSegments) {
            $open = null;
            foreach ($deviceSegments as $event) {
                $type     = $event->event_type;
                $metadata = is_array($event->metadata) ? $event->metadata : [];

                if (in_array($type, $openEventTypes, true)) {
                    if ($open !== null) {
                        $seg = $this->makeSegment($open, $event, $dayStartMs, true);
                        if ($seg !== null && $seg['duration_ms'] >= $minSegmentMs) {
                            $allSegments[] = $seg;
                        }
                    }
                    $open = $event;
                } elseif ($type === 'CHAPTER_CHANGE' && $open !== null) {
                    $seg = $this->makeSegment($open, $event, $dayStartMs, false);
                    if ($seg !== null && $seg['duration_ms'] >= $minSegmentMs) {
                        $allSegments[] = $seg;
                    }
                    // Re-open with new chapter info
                    $open = clone $event;
                    $open->event_type = 'PLAY_RESUME';
                } elseif (in_array($type, $closeEventTypes, true) && $open !== null) {
                    $seg = $this->makeSegment($open, $event, $dayStartMs, false);
                    if ($seg !== null && $seg['duration_ms'] >= $minSegmentMs) {
                        $allSegments[] = $seg;
                    }
                    $open = null;
                }
            }

            if ($open !== null) {
                $effectiveEnd    = (object) ['timestamp_ms' => min($nowMs, $dayEndMs), 'position_ms' => $open->position_ms];
                $seg = $this->makeSegment($open, $effectiveEnd, $dayStartMs, true);
                if ($seg !== null && $seg['duration_ms'] >= $minSegmentMs) {
                    $allSegments[] = $seg;
                }
            }
        }

        usort($allSegments, fn ($a, $b) => $a['start_ms'] <=> $b['start_ms']);

        return $allSegments;
    }

    private function makeSegment(
        object $open,
        object $close,
        int $dayStartMs,
        bool $isOrphaned
    ): ?array {
        $startMs = max((int) $open->timestamp_ms, $dayStartMs);
        $endMs   = (int) $close->timestamp_ms;
        if ($endMs <= $startMs) {
            return null;
        }

        $metadata = is_array($open->metadata ?? null) ? $open->metadata : [];

        return [
            'title'             => $open->title,
            'author'            => $open->author,
            'start_ms'          => $startMs,
            'end_ms'            => $endMs,
            'duration_ms'       => $endMs - $startMs,
            'start_position_ms' => (int) $open->position_ms,
            'end_position_ms'   => (int) $close->position_ms,
            'chapter_name'      => $metadata['chapterName'] ?? null,
            'chapter_index'     => isset($metadata['chapterIndex']) ? (int) $metadata['chapterIndex'] : null,
            'playback_speed'    => (float) ($metadata['playbackSpeed'] ?? 1.0),
            'device_id'         => $open->device_id,
            'is_orphaned'       => $isOrphaned,
        ];
    }

    private function getTimelinePeriodDetails(
        ?int $userId,
        string $deviceId,
        string $periodType,
        string $period,
        string $dayFilter = 'any',
        array $weekdays = []
    ): array {
        [$startDate, $endDate] = $this->resolveTimelineDetailRange($periodType, $period);

        $sessions = $this->filterSessionsForTimeline(
            $this->listeningActivityService->getSessions($userId, $deviceId),
            $startDate,
            $endDate,
            $dayFilter,
            $weekdays
        );

        $books = $sessions->groupBy(fn (object $session): string => $session->title . '|' . $session->author)
            ->map(static function (Collection $bookSessions): array {
                $seconds = $bookSessions->sum('seconds_listened');
                $first   = $bookSessions->first();

                return [
                    'title' => $first->title,
                    'author' => $first->author,
                    'total_seconds' => $seconds,
                    'total_minutes' => (int) floor($seconds / 60),
                    'session_count' => $bookSessions->count(),
                ];
            })
            ->sortByDesc('total_seconds')
            ->values();

        $totalSeconds = $sessions->sum('seconds_listened');

        return [
            'period_type' => $periodType,
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_seconds' => $totalSeconds,
            'total_minutes' => (int) floor($totalSeconds / 60),
            'session_count' => $sessions->count(),
            'books_count' => $books->count(),
            'books' => $books,
        ];
    }

    private function resolveTimelineDetailRange(string $periodType, string $period): array
    {
        return match ($periodType) {
            'day' => $this->resolveDayRange($period),
            'week' => $this->resolveWeekRange($period),
            'month' => $this->resolveMonthRange($period),
            'year' => $this->resolveYearRange($period),
            default => throw new \InvalidArgumentException('Unsupported detail period type.'),
        };
    }

    private function resolveDayRange(string $period): array
    {
        if (! Carbon::hasFormat($period, 'Y-m-d')) {
            abort(422, 'Invalid day period format. Use YYYY-MM-DD.');
        }

        $date = Carbon::createFromFormat('Y-m-d', $period);

        return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
    }

    private function resolveWeekRange(string $period): array
    {
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $period, $matches)) {
            abort(422, 'Invalid week period format. Use YYYY-Www.');
        }

        $date = Carbon::now()->setISODate((int) $matches[1], (int) $matches[2]);

        return [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()];
    }

    private function resolveMonthRange(string $period): array
    {
        if (! Carbon::hasFormat($period, 'Y-m')) {
            abort(422, 'Invalid month period format. Use YYYY-MM.');
        }

        $date = Carbon::createFromFormat('Y-m', $period);

        return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
    }

    private function resolveYearRange(string $period): array
    {
        if (! preg_match('/^\d{4}$/', $period)) {
            abort(422, 'Invalid year period format. Use YYYY.');
        }

        $date = Carbon::createFromFormat('Y', $period);

        return [$date->copy()->startOfYear(), $date->copy()->endOfYear()];
    }

    /**
     * @param Collection<int, (object{title: string, author: string, user_id: int, device_id: string,
     *   listening_date: string, seconds_listened: int, session_start: Carbon,
     *   metadata: array{playback_speed: mixed}}&\stdClass)> $sessions
     * @return Collection<int, (object{title: string, author: string, user_id: int, device_id: string,
     *   listening_date: string, seconds_listened: int, session_start: Carbon,
     *   metadata: array{playback_speed: mixed}}&\stdClass)>
     */
    private function filterSessionsForTimeline(
        Collection $sessions,
        Carbon $from,
        Carbon $to,
        string $dayFilter = 'any',
        array $weekdays = []
    ): Collection {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $inRange = static function (object $session) use ($fromDate, $toDate): bool {
            return $session->listening_date >= $fromDate && $session->listening_date <= $toDate;
        };
        $filtered = $sessions->filter($inRange);

        if (! empty($weekdays)) {
            // The `weekdays` request parameter uses MySQL's WEEKDAY() convention (0=Monday..
            // 6=Sunday), matching this endpoint's existing documented contract - not Carbon's
            // dayOfWeek convention (0=Sunday..6=Saturday), so translate before comparing.
            $normalizedDays = collect($weekdays)
                ->map(static fn ($day): int => (max(0, min(6, (int) $day)) + 1) % 7)
                ->unique()
                ->values();

            $matchesWeekday = static function (object $session) use ($normalizedDays): bool {
                return $normalizedDays->contains(Carbon::parse($session->listening_date)->dayOfWeek);
            };

            return $filtered->filter($matchesWeekday);
        }

        if ($dayFilter === 'weekday') {
            $isWeekday = static fn (object $session): bool => ! Carbon::parse($session->listening_date)->isWeekend();

            return $filtered->filter($isWeekday);
        }

        if ($dayFilter === 'weekend') {
            $isWeekend = static fn (object $session): bool => Carbon::parse($session->listening_date)->isWeekend();

            return $filtered->filter($isWeekend);
        }

        return $filtered;
    }

    /**
     * Group key for a listening_date under a given timeline grouping mode. Uses ISO
     * week-numbering (year 'o' + week 'W') for the 'week' grouping.
     */
    private function timelinePeriodKey(string $listeningDate, string $groupBy): string
    {
        $date = Carbon::parse($listeningDate);

        return match ($groupBy) {
            'week' => $date->format('o-\WW'),
            'month' => $date->format('Y-m'),
            'year' => $date->format('Y'),
            default => $date->format('Y-m-d'),
        };
    }

    /**
     * Report listening session (OpenAPI spec)
     */
    public function reportSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'                => 'required|string|max:255',
            'author'               => 'required|string|max:255',
            'genre'                => 'nullable|string|max:255',
            'session_start'        => 'required|date',
            'session_end'          => 'required|date|after:session_start',
            'start_position_ms'    => 'required|integer|min:0',
            'end_position_ms'      => 'required|integer|min:0',
            'playback_speed'       => 'nullable|numeric|min:0.1|max:5.0',
            'pauses_count'         => 'nullable|integer|min:0',
            'actual_duration_ms'   => 'nullable|integer|min:0', // Now nullable
            'events'               => 'nullable|array',         // Now nullable
            'events.*.timestamp'   => 'required|integer',
            'events.*.type'        => 'required|string',
            'events.*.position_ms' => 'required|integer|min:0',
            'events.*.metadata'    => 'nullable|array',
        ]);

        $userId = Auth::id();
        $deviceId = (string) ($request->header('X-Device-ID', 'unknown'));

        $sessionStart    = Carbon::parse($validated['session_start']);
        $sessionEnd      = Carbon::parse($validated['session_end']);
        $sessionDuration = abs($sessionEnd->diffInSeconds($sessionStart));

        // Use actual_duration_ms when available (real listening time from client),
        // fall back to wall-clock session duration, never use end_position_ms which is a book position
        $actualDurationMs = $validated['actual_duration_ms'] ?? 0;
        $secondsListened = (int) $sessionDuration;
        if ($actualDurationMs > 0) {
            $secondsListened = (int) floor($actualDurationMs / 1000);
        }
        $playbackSpeed = $validated['playback_speed'] ?? 1.0;

        $statistic = ListeningStatistic::createSession(
            $validated['title'],
            $validated['author'],
            $deviceId,
            $secondsListened,
            (int) ($validated['start_position_ms'] / 1000),
            (int) ($validated['end_position_ms'] / 1000),
            'listening',
            [
                'session_start'  => $validated['session_start'],
                'session_end'    => $validated['session_end'],
                'playback_speed' => $playbackSpeed,
                'pauses_count'   => $validated['pauses_count'] ?? 0,
            ],
            $userId !== null ? (string) $userId : null,
            $validated['actual_duration_ms'] ?? 0,
            $validated['events'] ?? [],
            $validated['genre'] ?? null
        );
        // Check for badge achievements after recording the session
        try {
            $badgeService = app(\App\Services\BadgeService::class);
            $badgeUserId = $userId !== null ? (string) $userId : $deviceId;
            $newBadges    = $badgeService->evaluateUserBadges($badgeUserId, $deviceId);

            $response = [
                'success' => true,
                'message' => 'Session reported successfully',
            ];

            // Include new badges in response if any were earned
            if (! empty($newBadges)) {
                $response['badges_earned'] = array_map(function ($userBadge) {
                    return [
                        'id'          => $userBadge->badge->id,
                        'key'         => $userBadge->badge->key,
                        'name'        => $userBadge->badge->name,
                        'description' => $userBadge->badge->description,
                        'icon'        => $userBadge->badge->icon,
                        'tier'        => $userBadge->badge->tier,
                        'points'      => $userBadge->badge->points,
                        'earned_at'   => $userBadge->earned_at->toISOString(),
                        'tier_level'  => $userBadge->tier_level,
                    ];
                }, $newBadges);
            }

            return response()->json($response, 201);
        } catch (\Exception $e) {
            // Log badge evaluation error but don't fail the session recording
            Log::warning('Badge evaluation failed after session recording', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Session reported successfully',
            ], 201);
        }
    }

    /**
     * Record a listening session (existing method for backward compatibility)
     */
    public function recordSession(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title'                  => 'required|string|max:255',
                'author'                 => 'required|string|max:255',
                'genre'                  => 'nullable|string|max:255',
                'device_id'              => 'required|string|max:255',
                'seconds_listened'       => 'required|integer|min:1',
                'start_position_seconds' => 'nullable|integer|min:0',
                'end_position_seconds'   => 'nullable|integer|min:0',
                'session_type'           => 'nullable|string|in:listening,completed,resumed,paused',
                'user_id'                => 'nullable|string|max:255',
                'metadata'               => 'nullable|array',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error'   => 'Validation failed',
                'message' => 'Invalid input data',
                'errors'  => $e->errors(),
            ], 422);
        }

        $statistic = ListeningStatistic::createSession(
            $validated['title'],
            $validated['author'],
            $validated['device_id'],
            $validated['seconds_listened'],
            $validated['start_position_seconds'] ?? null,
            $validated['end_position_seconds'] ?? null,
            $validated['session_type'] ?? 'listening',
            $validated['metadata'] ?? [],
            $this->stringUserId(Auth::id() ?? $validated['user_id'] ?? null),
            0,
            [],
            $validated['genre'] ?? null
        );

        // Check for badge achievements after recording the session
        try {
            $badgeService = app(\App\Services\BadgeService::class);
            $authUserId   = Auth::id();
            $badgeUserId  = $authUserId ? (string) $authUserId : $validated['device_id'];
            $newBadges    = $badgeService->evaluateUserBadges(
                $badgeUserId,
                $validated['device_id']
            );

            $response = [
                'success' => true,
                'message' => 'Listening session recorded successfully',
                'data'    => [
                    'id'                 => $statistic->id,
                    'title'              => $statistic->title,
                    'author'             => $statistic->author,
                    'device_id'          => $statistic->device_id,
                    'listening_date'     => $statistic->listening_date->toDateString(),
                    'seconds_listened'   => $statistic->seconds_listened,
                    'session_type'       => $statistic->session_type,
                    'formatted_duration' => $statistic->formatted_duration,
                ],
            ];

            // Include new badges in response if any were earned
            if (! empty($newBadges)) {
                $response['badges_earned'] = array_map(function ($userBadge) {
                    return [
                        'id'          => $userBadge->badge->id,
                        'key'         => $userBadge->badge->key,
                        'name'        => $userBadge->badge->name,
                        'description' => $userBadge->badge->description,
                        'icon'        => $userBadge->badge->icon,
                        'tier'        => $userBadge->badge->tier,
                        'points'      => $userBadge->badge->points,
                        'earned_at'   => $userBadge->earned_at->toISOString(),
                        'tier_level'  => $userBadge->tier_level,
                    ];
                }, $newBadges);
            }

            return response()->json($response, 201);
        } catch (\Exception $e) {
            // Log badge evaluation error but don't fail the session recording
            Log::warning('Badge evaluation failed after session recording', [
                'device_id' => $validated['device_id'],
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Listening session recorded successfully',
                'data'    => [
                    'id'                 => $statistic->id,
                    'title'              => $statistic->title,
                    'author'             => $statistic->author,
                    'device_id'          => $statistic->device_id,
                    'listening_date'     => $statistic->listening_date->toDateString(),
                    'seconds_listened'   => $statistic->seconds_listened,
                    'session_type'       => $statistic->session_type,
                    'formatted_duration' => $statistic->formatted_duration,
                ],
            ], 201);
        }
    }

    /**
     * Get daily statistics for a device
     */
    public function getDailyStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:255',
            'date'      => 'nullable|date_format:Y-m-d',
        ]);

        $date  = $validated['date'] ?? now()->toDateString();
        $stats = ListeningStatistic::getDailyStats($validated['device_id'], $date);

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }

    /**
     * Get weekly statistics for a device
     */
    public function getWeeklyStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id'  => 'required|string|max:255',
            'start_date' => 'nullable|date_format:Y-m-d',
        ]);

        $startDate = $validated['start_date'] ? Carbon::parse($validated['start_date']) : now()->startOfWeek();
        $endDate   = $startDate->copy()->endOfWeek();

        $stats = ListeningStatistic::where('device_id', $validated['device_id'])
            ->whereBetween('listening_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->select('listening_date', 'title', 'author', 'seconds_listened')
            ->get();

        $bookKey = static fn (object $stat): string => $stat->title . '|' . $stat->author;

        $dailyBreakdown = $stats->groupBy(static fn (ListeningStatistic $stat): string => $stat->listening_date->toDateString())
            ->map(function (Collection $dayStats, string $date) use ($bookKey): array {
                $totalSeconds = $dayStats->sum('seconds_listened');

                return [
                    'date'               => $date,
                    'total_seconds'      => $totalSeconds,
                    'books_listened'     => $dayStats->unique($bookKey)->count(),
                    'session_count'      => $dayStats->count(),
                    'formatted_duration' => $this->formatSeconds((int) $totalSeconds),
                ];
            })
            ->sortKeys()
            ->values();

        $weeklyTotal = $stats->sum('seconds_listened');
        $totalBooks  = $stats->unique($bookKey)->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'week_start'               => $startDate->toDateString(),
                'week_end'                 => $endDate->toDateString(),
                'total_seconds'            => $weeklyTotal,
                'total_books'              => $totalBooks,
                'formatted_total_duration' => $this->formatSeconds((int) $weeklyTotal),
                'daily_breakdown'          => $dailyBreakdown,
            ],
        ]);
    }

    /**
     * Get book-specific statistics
     */
    public function getBookStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'     => 'required|string|max:255',
            'author'    => 'required|string|max:255',
            'device_id' => 'nullable|string|max:255',
        ]);

        $stats = ListeningStatistic::getBookStats(
            $validated['title'],
            $validated['author'],
            $validated['device_id'] ?? null
        );

        return response()->json([
            'success' => true,
            // Lite has no book library — title comes from whatever the client
            // reported at session time; no cover metadata exists.
            'data'    => array_merge($stats, [
                'book' => [
                    'title'       => $stats['title'],
                    'author'      => $stats['author'],
                    'cover_image' => null,
                ],
            ]),
        ]);
    }

    /**
     * Get listening trends over time
     */
    public function getListeningTrends(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id'  => 'required|string|max:255',
            'period'     => 'nullable|string|in:week,month,year',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d',
        ]);

        $period = $validated['period'] ?? 'month';

        if (isset($validated['start_date']) && isset($validated['end_date'])) {
            $startDate = Carbon::parse($validated['start_date']);
            $endDate   = Carbon::parse($validated['end_date']);
        } else {
            switch ($period) {
                case 'week':
                    $startDate = now()->subWeeks(4)->startOfWeek();
                    $endDate   = now()->endOfWeek();
                    break;
                case 'year':
                    $startDate = now()->subYear()->startOfMonth();
                    $endDate   = now()->endOfMonth();
                    break;
                case 'month':
                default:
                    $startDate = now()->subMonths(3)->startOfMonth();
                    $endDate   = now()->endOfMonth();
                    break;
            }
        }

        $stats = ListeningStatistic::where('device_id', $validated['device_id'])
            ->whereBetween('listening_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->select('listening_date', 'title', 'author', 'seconds_listened')
            ->get();

        $bookKey = static fn (object $stat): string => $stat->title . '|' . $stat->author;

        // 'year' periods are bucketed by calendar month; 'week'/'month' periods are bucketed daily.
        $periodKey = static function (Carbon $listeningDate) use ($period): string {
            return $period === 'year' ? $listeningDate->format('Y-m') : $listeningDate->toDateString();
        };

        $trends = $stats->groupBy(fn (ListeningStatistic $stat): string => $periodKey($stat->listening_date))
            ->map(function (Collection $periodStats, string $key) use ($bookKey): array {
                $totalSeconds     = $periodStats->sum('seconds_listened');
                $sessionCount     = $periodStats->count();
                $avgSessionLength = $sessionCount > 0 ? $totalSeconds / $sessionCount : 0;

                return [
                    'date'                  => $key,
                    'total_seconds'         => $totalSeconds,
                    'books_listened'        => $periodStats->unique($bookKey)->count(),
                    'session_count'         => $sessionCount,
                    'avg_session_duration'  => round($avgSessionLength),
                    'formatted_duration'    => $this->formatSeconds((int) $totalSeconds),
                    'formatted_avg_session' => $this->formatSeconds((int) round($avgSessionLength)),
                ];
            })
            ->sortKeys()
            ->values();

        $totalSeconds = $stats->sum('seconds_listened');
        $totalBooks   = $stats->unique($bookKey)->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'period'                   => $period,
                'start_date'               => $startDate->toDateString(),
                'end_date'                 => $endDate->toDateString(),
                'total_seconds'            => $totalSeconds,
                'total_books'              => $totalBooks,
                'formatted_total_duration' => $this->formatSeconds((int) $totalSeconds),
                'average_daily_seconds'    => $trends->isNotEmpty() ? (int) round($totalSeconds / $trends->count()) : 0,
                'trends'                   => $trends,
            ],
        ]);
    }

    /**
     * Get top books by listening time
     */
    public function getTopBooks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id'   => 'nullable|string|max:255',
            'limit'       => 'nullable|integer|min:1|max:50',
            'period_days' => 'nullable|integer|min:1|max:365',
        ]);

        $query = ListeningStatistic::query()
            ->selectRaw('
                title,
                author,
                SUM(seconds_listened) as total_seconds,
                COUNT(*) as session_count,
                COUNT(DISTINCT listening_date) as days_listened,
                MIN(listening_date) as first_listened,
                MAX(listening_date) as last_listened
            ')
            ->groupBy('title', 'author')
            ->orderByDesc('total_seconds');

        if (isset($validated['device_id'])) {
            $query->where('device_id', $validated['device_id']);
        }

        if (isset($validated['period_days'])) {
            $startDate = now()->subDays($validated['period_days'])->toDateString();
            $query->where('listening_date', '>=', $startDate);
        }

        $limit    = $validated['limit'] ?? 10;
        $topBooks = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            // @phpstan-ignore-next-line
            'data'    => $topBooks->map(function ($stat) {
                /** @var \App\Models\ListeningStatistic $stat */
                return [
                    // Lite has no book library — title/author come from whatever the
                    // client reported at session time; no cover metadata exists.
                    'book'               => [
                        'title'       => $stat->title,
                        'author'      => $stat->author,
                        'cover_image' => null,
                    ],
                    // @phpstan-ignore-next-line
                    'total_seconds'      => (int) $stat->total_seconds,
                    // @phpstan-ignore-next-line
                    'session_count'      => $stat->session_count,
                    // @phpstan-ignore-next-line
                    'days_listened'      => $stat->days_listened,
                    // @phpstan-ignore-next-line
                    'first_listened'     => $stat->first_listened,
                    // @phpstan-ignore-next-line
                    'last_listened'      => $stat->last_listened,
                    // @phpstan-ignore-next-line
                    'formatted_duration' => $this->formatSeconds((int) $stat->total_seconds),
                ];
            }),
        ]);
    }

    /**
     * Get comprehensive dashboard statistics
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        $deviceId = $request->input('device_id') ?? $request->header('X-Device-ID');
        $userId   = Auth::id();

        if (! $deviceId && ! $userId) {
            return response()->json(['message' => 'device_id or authentication required.'], 400);
        }

        $today     = now()->toDateString();
        $thisWeek  = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        // Today's listening stats
        $todayStats = [
            'total_seconds' => 0,
            'books_listened' => 0,
            'session_count' => 0,
            'formatted_duration' => '0:00',
        ];

        $todayQuery = $this->listeningStatsQuery($userId, (string) ($deviceId ?? 'unknown'))
            ->whereDate('listening_date', $today);

        $todayRows = $todayQuery->select('title', 'author', 'seconds_listened')->get();
        $todayTotalSeconds = (int) $todayRows->sum('seconds_listened');

        $todayStats = [
            'total_seconds' => $todayTotalSeconds,
            'books_listened' => $todayRows->unique(static function (object $row): string {
                /** @var \App\Models\ListeningStatistic $row */
                return $row->title . '|' . $row->author;
            })->count(),
            'session_count' => $todayRows->count(),
            'formatted_duration' => $this->formatSeconds($todayTotalSeconds),
        ];

        // High-level user stats (from user_book_status)
        $userStats = [
            'total_completed'      => 0,
            'completed_this_month' => 0,
            'upcoming_goals'       => 0,
            'overdue_goals'        => 0,
        ];

        if ($userId) {
            $completedBookDates = $this->bookCompletionService->getCompletedBookDatesForUser($userId);

            $userStats['total_completed'] = $completedBookDates->count();
            $userStats['completed_this_month'] = $completedBookDates
                ->filter(fn (Carbon $date): bool => $date->gte($thisMonth))
                ->count();

            $userStats['upcoming_goals'] = UserBookStatus::where('user_id', $userId)
                ->whereIn('status', ['queue', 'in_progress'])
                ->where('target_date', '>=', now()->toDateString())
                ->count();

            $userStats['overdue_goals'] = UserBookStatus::where('user_id', $userId)
                ->whereIn('status', ['queue', 'in_progress'])
                ->where('target_date', '<', now()->toDateString())
                ->count();
        }

        // All-time listening stats
        $query = $this->listeningStatsQuery($userId, (string) ($deviceId ?? 'unknown'));

        $allTimeStats = (clone $query)->selectRaw('
                SUM(seconds_listened) as total_seconds,
                COUNT(*) as session_count,
                COUNT(DISTINCT listening_date) as days_listened
            ')
            ->first();

        $allTimeBooksListened = (clone $query)->select('title', 'author')->distinct()->count();

        $listeningMinutes = $this->getListeningMinutesBreakdown($userId, (string) ($deviceId ?? 'unknown'));

        return response()->json([
            'success' => true,
            'data'    => [
                'today'              => [
                    'total_seconds'      => $todayStats['total_seconds'],
                    'books_listened'     => $todayStats['books_listened'],
                    'session_count'      => $todayStats['session_count'],
                    'formatted_duration' => $todayStats['formatted_duration'],
                ],
                'user_tracking'      => $userStats,
                'listening_overview' => [
                    'total_seconds'            => $allTimeStats->total_seconds ?? 0,
                    'total_books'              => $allTimeBooksListened,
                    'days_active'              => $allTimeStats->days_listened ?? 0,
                    'formatted_total_duration' => $this->formatSeconds($allTimeStats->total_seconds ?? 0),
                ],
                'listening_minutes' => $listeningMinutes,
            ],
        ]);
    }

    private function getListeningMinutesBreakdown(?int $userId, string $deviceId): array
    {
        $query = $this->listeningStatsQuery($userId, $deviceId);

        $daySeconds = (clone $query)
            ->whereDate('listening_date', now()->toDateString())
            ->sum('seconds_listened');

        $weekSeconds = (clone $query)
            ->where('listening_date', '>=', now()->startOfWeek()->toDateString())
            ->sum('seconds_listened');

        $monthSeconds = (clone $query)
            ->where('listening_date', '>=', now()->startOfMonth()->toDateString())
            ->sum('seconds_listened');

        return [
            'day' => (int) floor($daySeconds / 60),
            'week' => (int) floor($weekSeconds / 60),
            'month' => (int) floor($monthSeconds / 60),
        ];
    }

    /**
     * Get reading progress stats by date (finished books per month/year)
     */
    public function getReadingHistoryStats(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        $groupBy = $request->input('group_by', 'month'); // month or year

        $completedBookDates = $this->bookCompletionService->getCompletedBookDatesForUser($user->id);

        $stats = $completedBookDates->groupBy(function (Carbon $date) use ($groupBy) {
            return $groupBy === 'year' ? $date->format('Y') : $date->format('Y-m');
        })->map(function ($items, $period) {
            return [
                'period' => (string) $period,
                'count'  => $items->count(),
            ];
        })->values();

        return response()->json($stats);
    }

    /**
     * Calculate current listening streak
     */
    private function calculateCurrentStreak(?int $userId, string $deviceId): int
    {
        return $this->listeningActivityService->getCurrentStreak($userId, $deviceId);
    }

    /**
     * Calculate longest listening streak
     */
    private function calculateLongestStreak(?int $userId, string $deviceId): int
    {
        return $this->listeningActivityService->getLongestStreak($userId, $deviceId);
    }

    /**
     * Get favorite genres for a user
     */
    private function getFavoriteGenres(?int $userId, string $deviceId, ?Carbon $startDate): array
    {
        // Genre is stored directly on each listening_statistics row (client-supplied
        // at report time), not derived via a book library join — lite has no books table.
        $query = $this->listeningStatsQuery($userId, $deviceId)
            ->whereNotNull('genre');

        if ($startDate) {
            $query->where('listening_date', '>=', $startDate->toDateString());
        }

        return $query->selectRaw('genre, SUM(seconds_listened) as total_time')
            ->groupBy('genre')
            ->orderByDesc('total_time')
            ->limit(5)
            ->pluck('genre')
            ->toArray();
    }

    /**
     * Get daily stats for a period
     */
    private function getDailyStatsForPeriod(?int $userId, string $deviceId, ?Carbon $startDate): array
    {
        $query = $this->listeningStatsQuery($userId, $deviceId);

        if ($startDate) {
            $query->where('listening_date', '>=', $startDate->toDateString());
        }

        $rawStats = $query->selectRaw('
            listening_date as date,
            SUM(seconds_listened) * 1000 as listening_time_ms,
            COUNT(*) as sessions_count
        ')
            ->groupBy('listening_date')
            ->orderByDesc('listening_date')
            ->limit(30)
            ->toBase()
            ->get();

        /** @var \Illuminate\Support\Collection<int, object> $rawStats */
        return $rawStats->map(function (object $stat) use ($userId, $deviceId) {
            /** @var \stdClass $stat */
            $booksListened = $this->listeningStatsQuery($userId, $deviceId)
                ->where('listening_date', $stat->date ?? '')
                ->select('title', 'author')
                ->distinct()
                ->get()
                ->toArray();

            return [
                'date'              => (string) ($stat->date ?? ''),
                'listening_time_ms' => (int) ($stat->listening_time_ms ?? 0),
                'sessions_count'    => (int) ($stat->sessions_count ?? 0),
                'books_listened'    => $booksListened,
            ];
        })->toArray();
    }

    /**
     * Format seconds into human readable format
     */
    protected function formatSeconds(int $seconds): string
    {
        $hours   = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs    = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    private function stringUserId(int|string|null $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        return (string) $userId;
    }

    public function getDiagnostics(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $totalEvents = ListeningEvent::where('user_id', $userId)->count();
        $syncedCount = ListeningEvent::where('user_id', $userId)->where('sync_status', 'SYNCED')->count();
        $pendingCount = ListeningEvent::where('user_id', $userId)->where('sync_status', 'PENDING_SYNC')->count();
        $failedCount = ListeningEvent::where('user_id', $userId)->where('sync_status', 'SYNC_FAILED')->count();

        $sessionStarts = ListeningEvent::where('user_id', $userId)
            ->where('event_type', 'SESSION_START')
            ->get(['id', 'title', 'author', 'device_id', 'timestamp_ms']);

        $maxWindowMs = 4 * 3_600_000;
        $orphanedCount = 0;

        foreach ($sessionStarts as $start) {
            $windowEnd = $start->timestamp_ms + $maxWindowMs;
            $hasEnd = ListeningEvent::where('user_id', $userId)
                ->where('title', $start->title)
                ->where('author', $start->author)
                ->where('device_id', $start->device_id)
                ->where('event_type', 'SESSION_END')
                ->where('timestamp_ms', '>', $start->timestamp_ms)
                ->where('timestamp_ms', '<=', $windowEnd)
                ->exists();
            if (!$hasEnd) {
                $orphanedCount++;
            }
        }

        return response()->json([
            'orphaned_sessions' => $orphanedCount,
            'total_events' => $totalEvents,
            'pending_sync' => $pendingCount,
            'sync_failed' => $failedCount,
            'synced' => $syncedCount,
        ]);
    }
}

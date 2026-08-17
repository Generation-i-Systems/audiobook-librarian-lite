<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookProgress;
use App\Models\ListeningGoal;
use App\Models\Playlist;
use App\Models\UserBookStatus;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\BookCompletionService;
use App\Services\ControllerDatabaseService as ControllerDatabase;

class ListeningGoalController extends Controller
{
    public function __construct(private readonly BookCompletionService $bookCompletionService)
    {
    }

    /** No series concept exists on lite (listening_statistics has no series column). */
    private const METRICS = 'total_hours,genre_hours,playlist_hours,fiction_hours,nonfiction_hours,books_finished,author_hours,book_hours,book_completion';

    /** GET /goals/listening — list all active (not-yet-expired) listening goals with current progress */
    public function index(): JsonResponse
    {
        $goals = ListeningGoal::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('period_type', '!=', 'custom')
                    ->orWhereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->with(['genre', 'playlist'])
            ->orderBy('period_type')
            ->get()
            ->map(fn ($goal) => $this->formatGoalWithProgress($goal));

        return response()->json(['goals' => $goals]);
    }

    /** GET /goals/listening/history — expired or deactivated custom-period goals with final progress */
    public function history(): JsonResponse
    {
        $goals = ListeningGoal::where('user_id', Auth::id())
            ->where('period_type', 'custom')
            ->where(function ($query) {
                $query->where('end_date', '<', now()->toDateString())
                    ->orWhere('is_active', false);
            })
            ->with(['genre', 'playlist'])
            ->orderByDesc('end_date')
            ->get()
            ->map(fn ($goal) => $this->formatGoalWithProgress($goal));

        return response()->json(['goals' => $goals]);
    }

    /** POST /goals/listening — create a new listening goal */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_type'    => 'required|string|in:day,week,month,year,custom',
            'metric'         => 'required|string|in:' . self::METRICS,
            'target_minutes' => 'required|integer|min:1|max:14400',
            'genre_id'       => 'nullable|integer|exists:genres,id',
            'playlist_id'    => 'nullable|integer|exists:playlists,id',
            'author_name'    => 'nullable|string|max:255',
            'book_title'     => 'nullable|string|max:255',
            'book_author'    => 'nullable|string|max:255',
            'start_date'     => 'required_if:period_type,custom|nullable|date',
            'end_date'       => 'required_if:period_type,custom|nullable|date',
        ]);

        $this->assertCustomRangeConsistency($validated['period_type'], $validated['start_date'] ?? null, $validated['end_date'] ?? null);
        $this->assertBookCompletionRequirements(
            $validated['metric'],
            $validated['period_type'],
            $validated['book_title'] ?? null,
            $validated['book_author'] ?? null
        );
        $this->assertPlaylistOwnership($validated['playlist_id'] ?? null);

        $goal = ListeningGoal::create([
            'user_id'        => Auth::id(),
            'period_type'    => $validated['period_type'],
            'metric'         => $validated['metric'],
            'target_minutes' => $validated['target_minutes'],
            'genre_id'       => $validated['genre_id'] ?? null,
            'playlist_id'    => $validated['playlist_id'] ?? null,
            'author_name'    => $validated['author_name'] ?? null,
            'book_title'     => $validated['book_title'] ?? null,
            'book_author'    => $validated['book_author'] ?? null,
            'start_date'     => $validated['start_date'] ?? null,
            'end_date'       => $validated['end_date'] ?? null,
            'is_active'      => true,
        ]);

        $goal->load(['genre', 'playlist']);
        return response()->json(['goal' => $this->formatGoalWithProgress($goal)], 201);
    }

    /** PUT /goals/listening/{goal} — update a goal */
    public function update(Request $request, ListeningGoal $goal): JsonResponse
    {
        abort_if($goal->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'period_type'    => 'sometimes|string|in:day,week,month,year,custom',
            'metric'         => 'sometimes|string|in:' . self::METRICS,
            'target_minutes' => 'sometimes|integer|min:1|max:14400',
            'genre_id'       => 'nullable|integer|exists:genres,id',
            'playlist_id'    => 'nullable|integer|exists:playlists,id',
            'author_name'    => 'nullable|string|max:255',
            'book_title'     => 'nullable|string|max:255',
            'book_author'    => 'nullable|string|max:255',
            'start_date'     => 'sometimes|nullable|date',
            'end_date'       => 'sometimes|nullable|date',
            'is_active'      => 'sometimes|boolean',
        ]);

        $resolvedPeriodType = $validated['period_type'] ?? $goal->period_type;
        $resolvedMetric = $validated['metric'] ?? $goal->metric;
        $resolvedBookTitle = array_key_exists('book_title', $validated) ? $validated['book_title'] : $goal->book_title;
        $resolvedBookAuthor = array_key_exists('book_author', $validated) ? $validated['book_author'] : $goal->book_author;
        $resolvedStartDate = array_key_exists('start_date', $validated) ? $validated['start_date'] : $goal->start_date?->toDateString();
        $resolvedEndDate = array_key_exists('end_date', $validated) ? $validated['end_date'] : $goal->end_date?->toDateString();
        $this->assertCustomRangeConsistency($resolvedPeriodType, $resolvedStartDate, $resolvedEndDate);
        $this->assertBookCompletionRequirements($resolvedMetric, $resolvedPeriodType, $resolvedBookTitle, $resolvedBookAuthor);
        $this->assertPlaylistOwnership($validated['playlist_id'] ?? null);

        $goal->update($validated);
        $goal->load(['genre', 'playlist']);

        return response()->json(['goal' => $this->formatGoalWithProgress($goal)]);
    }

    /** DELETE /goals/listening/{goal} — delete a goal */
    public function destroy(ListeningGoal $goal): JsonResponse
    {
        abort_if($goal->user_id !== Auth::id(), 403);
        $goal->delete();
        return response()->json(['message' => 'Goal deleted']);
    }

    /** GET /goals/listening/{goal}/breakdown — which books/days are contributing to progress */
    public function breakdown(ListeningGoal $goal): JsonResponse
    {
        abort_if($goal->user_id !== Auth::id(), 403);

        [$periodStart, $periodEnd] = $this->resolvePeriod($goal);
        $progressAmount = $this->computeProgressAmount($goal, $periodStart, $periodEnd);
        $progressPercent = $this->progressPercent($goal, $progressAmount);

        $entries = match ($goal->metric) {
            'books_finished'  => $this->booksFinishedEntries($periodStart, $periodEnd),
            'book_completion' => $this->bookCompletionEntries($goal, $progressAmount),
            default           => $this->hourEntries($goal, $periodStart, $periodEnd),
        };

        return response()->json([
            'period_start'     => $periodStart->toDateString(),
            'period_end'       => $periodEnd->toDateString(),
            'elapsed_percent'  => $this->elapsedPercent($periodStart, $periodEnd),
            'progress_percent' => $progressPercent,
            'metric'           => $goal->metric,
            'entries'          => $entries,
        ]);
    }

    /**
     * Laravel's `after_or_equal:start_date` validation rule crashes with a
     * DateMalformedStringException (it falls back to parsing the literal string "start_date" as
     * a date) whenever start_date resolves to null - which required_if/sometimes freely allow
     * mid-validation. Doing the date-order comparison here instead, once both fields are known to
     * be non-empty, sidesteps that entirely.
     */
    private function assertCustomRangeConsistency(string $periodType, ?string $startDate, ?string $endDate): void
    {
        if ($periodType === 'custom') {
            abort_if(empty($startDate) || empty($endDate), 422, 'custom period requires start_date and end_date');
            abort_if(Carbon::parse($startDate)->gt(Carbon::parse($endDate)), 422, 'end_date must be on or after start_date');
        } else {
            abort_if(!empty($startDate) || !empty($endDate), 422, 'start_date/end_date are only allowed when period_type is custom');
        }
    }

    private function assertBookCompletionRequirements(string $metric, string $periodType, ?string $bookTitle, ?string $bookAuthor): void
    {
        if ($metric !== 'book_completion') {
            return;
        }

        abort_if($periodType !== 'custom', 422, 'book_completion goals require period_type=custom');
        abort_if(empty($bookTitle) || empty($bookAuthor), 422, 'book_completion goals require book_title and book_author');
    }

    private function assertPlaylistOwnership(?int $playlistId): void
    {
        if (empty($playlistId)) {
            return;
        }

        abort_if(
            Playlist::where('id', $playlistId)->where('user_id', Auth::id())->doesntExist(),
            403,
            'Playlist not found'
        );
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolvePeriod(ListeningGoal $goal): array
    {
        return match ($goal->period_type) {
            'day'    => [now()->startOfDay(), now()->endOfDay()],
            'week'   => [now()->startOfWeek(Carbon::SUNDAY), now()->endOfWeek(Carbon::SATURDAY)],
            'month'  => [now()->startOfMonth(), now()->endOfMonth()],
            'year'   => [now()->startOfYear(), now()->endOfYear()],
            'custom' => [Carbon::parse($goal->start_date)->startOfDay(), Carbon::parse($goal->end_date)->endOfDay()],
            default  => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    private function elapsedPercent(Carbon $periodStart, Carbon $periodEnd): float
    {
        $totalSpan = max(1, $periodStart->diffInSeconds($periodEnd));
        $elapsed = $periodStart->diffInSeconds(now()->lessThan($periodEnd) ? now() : $periodEnd);

        return round(min(100, max(0, ($elapsed / $totalSpan) * 100)), 1);
    }

    private function computeProgressAmount(ListeningGoal $goal, Carbon $periodStart, Carbon $periodEnd): int
    {
        $userId = Auth::id();

        if ($goal->metric === 'books_finished') {
            return $this->bookCompletionService
                ->getCompletedBookDatesForUser($userId, $periodStart)
                ->filter(fn (Carbon $date): bool => $date->lte($periodEnd))
                ->count();
        }

        if ($goal->metric === 'book_completion') {
            return $this->bookCompletionProgressMinutes($goal);
        }

        $seconds = $this->scopedListeningQuery($goal, $periodStart, $periodEnd)
            ->sum('listening_statistics.seconds_listened');

        return (int) round($seconds / 60);
    }

    /**
     * Progress for a book_completion goal is the book's actual playback position, not
     * accumulated listening minutes in the goal's date range - re-listening or scrubbing must
     * not corrupt "how much of the book is done." Lite has no numeric book id, so the book is
     * identified by the same (title, author) pair used everywhere else on this backend.
     */
    private function bookCompletionProgressMinutes(ListeningGoal $goal): int
    {
        $userId = Auth::id();
        $title = $goal->book_title ?? '__no_match__';
        $author = $goal->book_author ?? '__no_match__';

        $isCompleted = UserBookStatus::where('user_id', $userId)
            ->where('title', $title)
            ->where('author', $author)
            ->where('status', 'completed')
            ->exists();

        if ($isCompleted) {
            return $goal->target_minutes;
        }

        // Furthest position across all of the user's devices, not whichever device synced most
        // recently - a stale sync from a device that's behind must not regress progress.
        $furthestPositionSeconds = BookProgress::where('user_id', $userId)
            ->where('title', $title)
            ->where('author', $author)
            ->max('current_position_seconds');

        if ($furthestPositionSeconds === null) {
            return 0;
        }

        return min($goal->target_minutes, (int) round($furthestPositionSeconds / 60));
    }

    private function scopedListeningQuery(ListeningGoal $goal, Carbon $periodStart, Carbon $periodEnd)
    {
        $userId = Auth::id();
        $deviceIds = ControllerDatabase::table('devices')
            ->where('user_id', $userId)
            ->pluck('device_id');

        $query = ControllerDatabase::table('listening_statistics')
            ->where(function ($statsQuery) use ($userId, $deviceIds): void {
                $statsQuery->where('listening_statistics.user_id', $userId);

                if ($deviceIds->isNotEmpty()) {
                    $statsQuery->orWhereIn('listening_statistics.device_id', $deviceIds);
                }
            })
            ->whereRaw('DATE(listening_statistics.listening_date) >= ?', [$periodStart->toDateString()])
            ->whereRaw('DATE(listening_statistics.listening_date) <= ?', [$periodEnd->toDateString()]);

        switch ($goal->metric) {
            case 'genre_hours':
                // Lite has no book library — genre is client-supplied and stored
                // directly on each listening_statistics row.
                $genreName = \App\Models\Genre::find($goal->genre_id)?->name;
                $query->where('listening_statistics.genre', $genreName ?? '__no_match__');
                break;
            case 'fiction_hours':
            case 'nonfiction_hours':
                $wantFiction = $goal->metric === 'fiction_hours';
                $genreNames = \App\Models\Genre::where('is_fiction', $wantFiction)->pluck('name');
                $query->whereIn('listening_statistics.genre', $genreNames);
                break;
            case 'playlist_hours':
                $query->join('user_book_status', function ($join) use ($userId, $goal) {
                    $join->on('user_book_status.title', '=', 'listening_statistics.title')
                        ->on('user_book_status.author', '=', 'listening_statistics.author')
                        ->where('user_book_status.user_id', $userId)
                        ->where('user_book_status.playlist_id', $goal->playlist_id);
                });
                break;
            case 'author_hours':
                $query->where('listening_statistics.author', $goal->author_name ?? '__no_match__');
                break;
            case 'book_hours':
                $query->where('listening_statistics.title', $goal->book_title ?? '__no_match__')
                    ->where('listening_statistics.author', $goal->book_author ?? '__no_match__');
                break;
        }

        return $query;
    }

    /** @return array<int, array{type:string,title:string,finished_at:string}> */
    private function booksFinishedEntries(Carbon $periodStart, Carbon $periodEnd): array
    {
        $userId = Auth::id();

        return $this->bookCompletionService
            ->getCompletedBooksWithTitles($userId, $periodStart, $periodEnd)
            ->sortByDesc('finished_at')
            ->map(fn (array $entry): array => [
                'type'        => 'book',
                'title'       => $entry['title'],
                'finished_at' => $entry['finished_at']->toDateString(),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{type:string,title:?string,progress_minutes:int,target_minutes:int,remaining_minutes:int,target_date:?string}> */
    private function bookCompletionEntries(ListeningGoal $goal, int $progressAmount): array
    {
        return [[
            'type'              => 'book_completion',
            'title'             => $goal->book_title,
            'progress_minutes'  => $progressAmount,
            'target_minutes'    => $goal->target_minutes,
            'remaining_minutes' => max(0, $goal->target_minutes - $progressAmount),
            'target_date'       => $goal->end_date?->toDateString(),
        ]];
    }

    /** @return array<int, array{type:string,date:string,minutes:int,books:array<int,array{title:string,minutes:int}>}> */
    private function hourEntries(ListeningGoal $goal, Carbon $periodStart, Carbon $periodEnd): array
    {
        $rows = $this->scopedListeningQuery($goal, $periodStart, $periodEnd)
            ->selectRaw(
                'DATE(listening_statistics.listening_date) as listening_day, ' .
                'listening_statistics.title as title, ' .
                'SUM(listening_statistics.seconds_listened) as secs'
            )
            ->groupBy('listening_day', 'listening_statistics.title')
            ->orderByDesc('listening_day')
            ->get();

        $byDate = $rows->groupBy('listening_day');

        return $byDate->map(function ($dayRows, $date): array {
            $books = $dayRows->map(fn ($row): array => [
                'title'   => (string) $row->title,
                'minutes' => (int) round($row->secs / 60),
            ])->values()->all();

            return [
                'type'    => 'day',
                'date'    => (string) $date,
                'minutes' => array_sum(array_column($books, 'minutes')),
                'books'   => $books,
            ];
        })->sortByDesc('date')->values()->all();
    }

    private function progressPercent(ListeningGoal $goal, int $progressAmount): float
    {
        return $goal->target_minutes > 0
            ? min(100, round(($progressAmount / $goal->target_minutes) * 100, 1))
            : 0;
    }

    private function formatGoalWithProgress(ListeningGoal $goal): array
    {
        [$periodStart, $periodEnd] = $this->resolvePeriod($goal);
        $progressAmount = $this->computeProgressAmount($goal, $periodStart, $periodEnd);

        return [
            'id'               => $goal->id,
            'period_type'      => $goal->period_type,
            'metric'           => $goal->metric,
            'target_minutes'   => $goal->target_minutes,
            'progress_minutes' => $progressAmount,
            'progress_percent' => $this->progressPercent($goal, $progressAmount),
            'genre_id'         => $goal->genre_id,
            'genre_name'       => $goal->genre?->name,
            'playlist_id'      => $goal->playlist_id,
            'playlist_name'    => $goal->playlist?->name,
            'author_name'      => $goal->author_name,
            'book_title'       => $goal->book_title,
            'book_author'      => $goal->book_author,
            'start_date'       => $periodStart->toDateString(),
            'end_date'         => $periodEnd->toDateString(),
            'elapsed_percent'  => $this->elapsedPercent($periodStart, $periodEnd),
            'is_active'        => $goal->is_active,
            'created_at'       => $goal->created_at?->toIso8601String(),
        ];
    }
}

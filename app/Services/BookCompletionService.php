<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BookPosition;
use App\Models\BookProgress;
use App\Models\UserBookStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Determines which books a user has finished and when, merging the three completion
 * sources that exist in this codebase: user_book_status.status='completed' (finished_at),
 * book_progress.completed=true (completed_at), and the modern event-sourced book_positions
 * completion path (last_event_timestamp_ms). When multiple sources have a date for the same
 * book, the latest date wins.
 *
 * Unlike the main AbLibrarian Server, this Lite backend has no central book library, so
 * completions are keyed by a "title|author" composite string instead of a book_id, and
 * there is no way to filter completions by genre (no genre is stored on these tables).
 */
class BookCompletionService
{
    public function completedProgressQuery(?int $userId, string $deviceId, ?Carbon $startDate = null)
    {
        $query = BookProgress::query()
            ->where('completed', true)
            ->where('title', '!=', '');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        } else {
            $query->where('device_id', $deviceId);
        }

        if ($startDate !== null) {
            $query->where('completed_at', '>=', $startDate);
        }

        return $query;
    }

    /** @return Collection<non-falsy-string, Carbon> "title|author" => finished date */
    public function getCompletedBookDatesForUser(int $userId, ?Carbon $startDate = null): Collection
    {
        $bookKey = fn (?string $title, ?string $author): string => ($title ?? '') . '|' . ($author ?? '');

        $statusDates = UserBookStatus::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('title')
            ->whereNotNull('finished_at')
            ->get(['title', 'author', 'finished_at'])
            ->mapWithKeys(function (UserBookStatus $status) use ($bookKey): array {
                return [$bookKey($status->title, $status->author) => Carbon::parse((string) $status->finished_at)];
            });

        $progressDates = BookProgress::query()
            ->where('user_id', $userId)
            ->where('completed', true)
            ->where('title', '!=', '')
            ->whereNotNull('completed_at')
            ->get(['title', 'author', 'completed_at'])
            ->mapWithKeys(function (BookProgress $progress) use ($bookKey): array {
                return [$bookKey($progress->title, $progress->author) => Carbon::parse((string) $progress->completed_at)];
            });

        // The modern event-sourced completion path (BOOK_FINISH -> PositionMaterializer) writes
        // to book_positions, not book_progress/user_book_status. last_event_timestamp_ms is the
        // client-reported time of the finishing event.
        $positionDates = BookPosition::query()
            ->where('user_id', $userId)
            ->where('completed', true)
            ->get(['title', 'author', 'last_event_timestamp_ms'])
            ->mapWithKeys(function (BookPosition $position) use ($bookKey): array {
                return [$bookKey($position->title, $position->author) => Carbon::createFromTimestampMs((int) $position->last_event_timestamp_ms)];
            });

        $merged = $statusDates;

        foreach ($progressDates as $key => $date) {
            $existing = $merged->get($key);
            if (! $existing instanceof Carbon || $date->gt($existing)) {
                $merged->put($key, $date);
            }
        }

        foreach ($positionDates as $key => $date) {
            $existing = $merged->get($key);
            if (! $existing instanceof Carbon || $date->gt($existing)) {
                $merged->put($key, $date);
            }
        }

        if ($startDate !== null) {
            return $merged->filter(fn (Carbon $date): bool => $date->gte($startDate));
        }

        return $merged;
    }

    /** @return Collection<int, array{title:string,finished_at:Carbon}> */
    public function getCompletedBooksWithTitles(int $userId, ?Carbon $startDate, ?Carbon $endDate): Collection
    {
        $dates = $this->getCompletedBookDatesForUser($userId, $startDate);

        if ($endDate !== null) {
            $dates = $dates->filter(fn (Carbon $date): bool => $date->lte($endDate));
        }

        return $dates->map(fn (Carbon $date, string $key): array => [
            'title'       => explode('|', $key, 2)[0],
            'finished_at' => $date,
        ])->values();
    }
}

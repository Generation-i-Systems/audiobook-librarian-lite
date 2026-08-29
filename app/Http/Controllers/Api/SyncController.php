<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookProgress;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SyncController extends Controller
{
    /**
     * Reconcile per-book playback progress in one round trip.
     *
     * The client pushes what it has; anything the server holds a newer copy of comes back in
     * `updates` for the client to apply. Lite hosts no book catalog, so a book is identified by
     * title + author. Clients built against the full server also send `bookId`; it is echoed back
     * untouched so they can match the update to their own record, but it is never used to look
     * anything up here.
     */
    public function progress(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return response()->json([
                'error'   => true,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'clientTimestamp'               => 'required|date',
            'progress'                      => 'present|array',
            'progress.*.bookId'             => 'nullable|integer',
            'progress.*.title'              => 'nullable|string|max:255',
            'progress.*.author'             => 'nullable|string|max:255',
            'progress.*.positionMs'         => 'required|integer|min:0',
            'progress.*.progressPercentage' => 'required|numeric|min:0|max:100',
            'progress.*.lastUpdated'        => 'required|date',
        ]);

        $deviceId = (string) ($request->header('X-Device-ID') ?? 'unknown');

        $updates = [];

        foreach ($validated['progress'] as $item) {
            $title  = $item['title'] ?? null;
            $author = $item['author'] ?? null;

            // Without a title there is nothing to key on, so the item is skipped rather than
            // failing the whole batch — a client with a partially matched library still syncs
            // everything it could identify.
            if ($title === null || $title === '') {
                continue;
            }
            $author ??= '';

            $clientUpdated = Carbon::parse($item['lastUpdated']);

            $serverProgress = BookProgress::where('user_id', $user->id)
                ->where('title', $title)
                ->where('author', $author)
                ->orderBy('updated_at', 'desc')
                ->first();

            if ($serverProgress !== null && $serverProgress->updated_at > $clientUpdated) {
                $updates[] = [
                    'bookId'              => $item['bookId'] ?? null,
                    'title'               => $serverProgress->title,
                    'author'              => $serverProgress->author,
                    'positionMs'          => $serverProgress->current_position_seconds * 1000,
                    'progressPercentage'  => (float) $serverProgress->progress_percentage,
                    'source'              => 'server',
                    'lastUpdated'         => $serverProgress->updated_at->toISOString(),
                ];

                continue;
            }

            BookProgress::updateOrCreate(
                [
                    'user_id'   => $user->id,
                    'title'     => $title,
                    'author'    => $author,
                    'device_id' => $deviceId,
                ],
                [
                    'current_position_seconds' => (int) ($item['positionMs'] / 1000),
                    'progress_percentage'      => $item['progressPercentage'],
                    'last_listened_at'         => $clientUpdated,
                ]
            );
        }

        return response()->json([
            'serverTimestamp' => now()->toISOString(),
            'updates'         => $updates,
        ]);
    }
}

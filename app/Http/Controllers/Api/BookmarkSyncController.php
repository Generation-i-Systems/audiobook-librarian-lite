<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookmarkSyncController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = Bookmark::where('user_id', $user->id);

        if ($request->boolean('include_deleted', true)) {
            $query->withTrashed();
        }

        if ($request->has('since')) {
            $since = $this->parseSince((string) $request->input('since'));

            $query->where(function ($q) use ($since) {
                $q->where('created_at', '>', $since)
                    ->orWhere('updated_at', '>', $since)
                    ->orWhere('deleted_at', '>', $since);
            });
        }

        if ($request->has('title')) {
            $query->where('title', $request->input('title'))
                ->where('author', $request->input('author', ''));
        }

        $bookmarks = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'server_timestamp' => now()->toIso8601String(),
            'bookmarks' => $this->formatBookmarks($bookmarks),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
        ]);

        $query = Bookmark::where('user_id', $user->id)
            ->where('title', $validated['title'])
            ->where('author', $validated['author']);

        if ($request->boolean('include_deleted', true)) {
            $query->withTrashed();
        }

        $bookmarks = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'server_timestamp' => now()->toIso8601String(),
            'bookmarks' => $this->formatBookmarks($bookmarks),
        ]);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, Bookmark> $bookmarks
     */
    private function formatBookmarks($bookmarks): \Illuminate\Support\Collection
    {
        return $bookmarks->map(function (Bookmark $bookmark) {
            return [
                'string_id' => $bookmark->string_id,
                'title' => $bookmark->title,
                'author' => $bookmark->author,
                'device_id' => $bookmark->device_id,
                'device_name' => $bookmark->device_name,
                'position_ms' => $bookmark->position_ms,
                'note' => $bookmark->notes,
                'is_auto' => $bookmark->is_auto ?? false,
                'chapter_number' => $bookmark->chapter_number,
                'chapter_title' => $bookmark->chapter_title,
                'created_at' => $bookmark->created_at?->toIso8601String(),
                'updated_at' => $bookmark->updated_at?->toIso8601String(),
                'deleted_at' => $bookmark->deleted_at?->toIso8601String(),
            ];
        });
    }

    private function parseSince(string $value): Carbon
    {
        $normalizedValue = preg_replace('/ (?=\d{2}:\d{2}$)/', '+', $value) ?? $value;

        return Carbon::parse($normalizedValue);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        $deviceId = $request->header('X-Device-ID');
        $deviceName = $request->header('X-Device-Name');

        if (!$deviceId || !$deviceName) {
            return response()->json([
                'error' => 'X-Device-ID and X-Device-Name headers are required',
            ], 400);
        }

        $validated = $request->validate([
            'client_timestamp' => 'required|date',
            'bookmarks' => 'required|array|min:1',
            'bookmarks.*.string_id' => 'required|uuid',
            'bookmarks.*.title' => 'required|string|max:255',
            'bookmarks.*.author' => 'required|string|max:255',
            'bookmarks.*.position_ms' => 'required|integer|min:0',
            'bookmarks.*.note' => 'nullable|string',
            'bookmarks.*.is_auto' => 'nullable|boolean',
            'bookmarks.*.chapter_number' => 'nullable|integer',
            'bookmarks.*.chapter_title' => 'nullable|string|max:255',
            'bookmarks.*.created_at' => 'required|date',
        ]);

        $accepted = 0;
        $duplicatesSkipped = 0;

        foreach ($validated['bookmarks'] as $bm) {
            $existing = Bookmark::withTrashed()
                ->where('user_id', $user->id)
                ->where('string_id', $bm['string_id'])
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->update([
                    'title' => $bm['title'],
                    'author' => $bm['author'],
                    'device_id' => $deviceId,
                    'device_name' => $deviceName,
                    'position_ms' => $bm['position_ms'],
                    'notes' => $bm['note'] ?? null,
                    'is_auto' => $bm['is_auto'] ?? false,
                    'chapter_number' => $bm['chapter_number'] ?? null,
                    'chapter_title' => $bm['chapter_title'] ?? null,
                ]);

                $duplicatesSkipped++;
            } else {
                Bookmark::create([
                    'user_id' => $user->id,
                    'title' => $bm['title'],
                    'author' => $bm['author'],
                    'string_id' => $bm['string_id'],
                    'device_id' => $deviceId,
                    'device_name' => $deviceName,
                    'position_ms' => $bm['position_ms'],
                    'position' => (int) ($bm['position_ms'] / 1000),
                    'notes' => $bm['note'] ?? null,
                    'is_auto' => $bm['is_auto'] ?? false,
                    'chapter_number' => $bm['chapter_number'] ?? null,
                    'chapter_title' => $bm['chapter_title'] ?? null,
                    'chapter' => $bm['chapter_title'] ?? null,
                ]);

                $accepted++;
            }
        }

        return response()->json([
            'server_timestamp' => now()->toIso8601String(),
            'accepted' => $accepted,
            'duplicates_skipped' => $duplicatesSkipped,
        ]);
    }

    public function destroy(Request $request, string $stringId): JsonResponse
    {
        $user = auth()->user();

        $bookmark = Bookmark::where('user_id', $user->id)
            ->where('string_id', $stringId)
            ->first();

        if (!$bookmark) {
            return response()->json([
                'error' => 'Bookmark not found',
            ], 404);
        }

        $bookmark->delete();

        return response()->json([
            'string_id' => $stringId,
            'deleted_at' => $bookmark->deleted_at?->toIso8601String(),
        ]);
    }
}

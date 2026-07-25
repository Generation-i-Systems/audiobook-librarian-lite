<?php

namespace App\Http\Controllers\Api;

use App\Contracts\DocumentStoreServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BookmarkApiController extends Controller
{
    protected DocumentStoreServiceInterface $documentStoreService;


    public function __construct(DocumentStoreServiceInterface $documentStoreService)
    {
        $this->documentStoreService = $documentStoreService;
    }


    /**
     * Get all bookmarks for a book (OpenAPI spec version)
     */
    public function getBookmarksOpenApi(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
        ]);

        // Get user ID from authenticated user
        $userId = Auth::id();

        // Get bookmarks from the document store
        $bookmarks = $this->documentStoreService->getBookmarks($userId, $validated['title'], $validated['author']);

        // Format response to match OpenAPI spec
        $formattedBookmarks = [];
        foreach ($bookmarks as $bookmark) {
            $formattedBookmarks[] = [
                'id' => (int) ($bookmark['id'] ?? $bookmark['_id']),
                'title' => $bookmark['title'] ?? null,
                'author' => $bookmark['author'] ?? null,
                // @phpstan-ignore-next-line
                'position_ms' => ((int) ($bookmark['position'] ?? 0)) * 1000, // Convert to milliseconds
                'note' => $bookmark['notes'] ?? $bookmark['note'] ?? null,
                'is_auto' => (bool) ($bookmark['isAuto'] ?? $bookmark['is_auto'] ?? false),
                'created_at' => $bookmark['createdAt'] ?? $bookmark['created_at'] ?? now()->toISOString(),
            ];
        }

        return response()->json(['bookmarks' => $formattedBookmarks]);
    }

    /**
     * Create a new bookmark (OpenAPI spec version)
     */
    public function createBookmarkOpenApi(Request $request)
    {
        // Validate request (manual to ensure JSON 422 without relying on global handler)
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'position_ms' => 'required|integer|min:0',
            'note' => 'nullable|string',
            'is_auto' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get user ID from authenticated user
        $userId = Auth::id();

        // Create bookmark data
        $bookmarkData = [
            'user_id' => $userId,
            'title' => $request->input('title'),
            'author' => $request->input('author'),
            'chapter' => '1', // Default chapter for compatibility
            'position' => (int) ($request->input('position_ms') / 1000), // Convert from milliseconds
            'notes' => $request->input('note'),
            'is_auto' => $request->input('is_auto', false),
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ];

        // Insert bookmark into the document store
        $bookmarkId = $this->documentStoreService->createBookmark($bookmarkData);

        // Format response
        return response()->json([
            'id' => (int) $bookmarkId,
            'title' => $request->input('title'),
            'author' => $request->input('author'),
            'position_ms' => $request->input('position_ms'),
            'note' => $request->input('note'),
            'is_auto' => $request->input('is_auto', false),
            'created_at' => now()->toISOString(),
        ], 201);
    }

    /**
     * Delete a bookmark by ID (without book context)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteBookmarkById(Request $request, string $bookmarkId)
    {
        $userId = Auth::id();

        $result = $this->documentStoreService->deleteBookmarkById($bookmarkId, $userId);

        if (!$result) {
            return response()->json(['error' => 'Bookmark not found'], 404);
        }

        return response()->json(null, 204);
    }
}

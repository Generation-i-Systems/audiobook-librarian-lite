<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BookTagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookTagController extends Controller
{
    public function __construct(private readonly BookTagService $bookTagService)
    {
    }

    /** GET /books/tags?title=...&author=... — tags visible to the caller across all scopes. */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
        ]);
        $title = $validated['title'];
        $author = $validated['author'] ?? '';

        /** @var User $user */
        $user = Auth::user();

        return response()->json(array_merge(
            ['title' => $title, 'author' => $author],
            $this->bookTagService->visibleTags($user, $title, $author),
        ));
    }

    /** PUT /books/tags — replace the tag list for one scope (system/group/user). */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'scope' => ['required', 'string', 'in:system,group,user'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id', 'required_if:scope,group'],
            'tags' => ['required', 'array'],
            'tags.*' => ['nullable', 'string', 'max:64'],
        ]);
        $title = $validated['title'];
        $author = $validated['author'] ?? '';

        /** @var User $user */
        $user = Auth::user();

        $result = $this->bookTagService->updateTags(
            $user,
            $title,
            $author,
            $validated['scope'],
            $validated['group_id'] ?? null,
            $validated['tags'],
        );

        return response()->json(array_merge(['title' => $title, 'author' => $author], $result));
    }

    /**
     * GET /tags/popular — most-used system tags, for autocomplete suggestions.
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 20);

        return response()->json(['tags' => $this->bookTagService->popularTags($limit)]);
    }
}

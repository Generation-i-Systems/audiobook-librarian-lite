<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BookTagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TagController extends Controller
{
    public function __construct(private readonly BookTagService $bookTagService)
    {
    }

    public function index(): View
    {
        $tags = $this->bookTagService->systemTagsOverview();

        return view('admin.tags.index', ['tags' => $tags]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $tags = $this->splitTags($validated['tags'] ?? '');

        $this->bookTagService->updateTags(
            $user,
            $validated['title'],
            $validated['author'] ?? '',
            'system',
            null,
            $tags,
        );

        return redirect()->route('admin.tags.index')->with('success', 'System tags saved successfully!');
    }

    public function edit(string $tag): View
    {
        $titles = $this->bookTagService->titlesForSystemTag($tag);

        return view('admin.tags.edit', ['tag' => $tag, 'titles' => $titles]);
    }

    public function update(Request $request, string $tag): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
        ]);

        $this->bookTagService->renameSystemTag($tag, trim($validated['name']));

        return redirect()->route('admin.tags.index')->with('success', 'Tag renamed successfully!');
    }

    public function destroy(string $tag): RedirectResponse
    {
        $this->bookTagService->deleteSystemTag($tag);

        return redirect()->route('admin.tags.index')->with('success', 'Tag deleted successfully!');
    }

    /**
     * @return array<int, string>
     */
    private function splitTags(string $rawTags): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/[,\n]+/', $rawTags) ?: []),
            fn (string $tag): bool => $tag !== '',
        ));
    }
}

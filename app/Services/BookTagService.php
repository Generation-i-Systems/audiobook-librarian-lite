<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BookTag;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Collection;

class BookTagService
{
    /**
     * @return array{system: array<int, string>, groups: array<int, array{groupId: int, groupName: ?string, tags: array<int, string>}>, user: array<int, string>}
     */
    public function visibleTags(User $user, string $title, string $author): array
    {
        $groupIds = $user->groups()->pluck('groups.id');

        $ownerKeys = array_merge(
            ['system'],
            $groupIds->map(fn (int $id): string => "group:{$id}")->all(),
            ["user:{$user->id}"],
        );

        $rows = BookTag::query()
            ->where('book_title', $title)
            ->where('book_author', $author)
            ->whereIn('owner_key', $ownerKeys)
            ->get();

        $systemTags = $rows->firstWhere('scope', 'system')->tags ?? [];
        $userTags = $rows->firstWhere('owner_key', "user:{$user->id}")->tags ?? [];

        $groupRows = $rows->where('scope', 'group');
        $groupNames = Group::query()->whereIn('id', $groupRows->pluck('group_id'))->pluck('name', 'id');
        $groups = $groupRows->map(fn (BookTag $row): array => [
            'groupId' => $row->group_id,
            'groupName' => $groupNames->get($row->group_id),
            'tags' => array_values($row->tags),
        ])->values()->all();

        return [
            'system' => array_values($systemTags),
            'groups' => $groups,
            'user' => array_values($userTags),
        ];
    }

    /**
     * @param array<int, string> $tags
     * @return array{scope: string, groupId: ?int, tags: array<int, string>}
     */
    public function updateTags(User $user, string $title, string $author, string $scope, ?int $groupId, array $tags): array
    {
        if ($scope === 'system') {
            abort_unless($user->isAdmin(), 403, 'Only admins can set system tags.');
        } elseif ($scope === 'group') {
            abort_unless(
                $user->groups()->where('groups.id', $groupId)->exists(),
                403,
                'You are not a member of this group.'
            );
        }

        $normalizedTags = $this->normalizeTags($tags);
        $ownerKey = BookTag::ownerKeyFor($scope, $groupId, $user->id);

        BookTag::query()->updateOrCreate(
            ['book_title' => $title, 'book_author' => $author, 'owner_key' => $ownerKey],
            [
                'user_id' => $user->id,
                'scope' => $scope,
                'group_id' => $scope === 'group' ? $groupId : null,
                'tags' => $normalizedTags,
            ]
        );

        return ['scope' => $scope, 'groupId' => $groupId, 'tags' => $normalizedTags];
    }

    /**
     * Only system-scope tags are aggregated: group/user tag names must never
     * leak into a suggestion list visible to everyone.
     *
     * @return Collection<int, string>
     */
    public function popularTags(int $limit = 20): Collection
    {
        $limit = min(50, max(1, $limit));

        /** @var array<string, array{name: string, count: int}> $counts */
        $counts = [];
        BookTag::query()->where('scope', 'system')->pluck('tags')->each(function (array $tags) use (&$counts): void {
            foreach ($tags as $tag) {
                $tag = (string) $tag;
                $key = mb_strtolower($tag);
                $counts[$key] ??= ['name' => $tag, 'count' => 0];
                $counts[$key]['count']++;
            }
        });

        return collect($counts)
            ->sortByDesc('count')
            ->take($limit)
            ->map(fn (array $entry): string => $entry['name'])
            ->values();
    }

    /**
     * Distinct system-scope tag names with usage counts and the title/author pairs
     * carrying them, for the admin Tags index page.
     *
     * @return Collection<int, array{name: string, count: int}>
     */
    public function systemTagsOverview(): Collection
    {
        /** @var array<string, array{name: string, count: int}> $counts */
        $counts = [];
        BookTag::query()->where('scope', 'system')->pluck('tags')->each(function (array $tags) use (&$counts): void {
            foreach ($tags as $tag) {
                $tag = (string) $tag;
                $key = mb_strtolower($tag);
                $counts[$key] ??= ['name' => $tag, 'count' => 0];
                $counts[$key]['count']++;
            }
        });

        return collect($counts)->sortBy('name')->values();
    }

    /**
     * @return Collection<int, array{title: string, author: string}>
     */
    public function titlesForSystemTag(string $tag): Collection
    {
        return BookTag::query()
            ->where('scope', 'system')
            ->whereJsonContains('tags', $tag)
            ->get(['book_title', 'book_author'])
            ->map(fn (BookTag $row): array => ['title' => $row->book_title, 'author' => $row->book_author])
            ->values();
    }

    public function renameSystemTag(string $oldName, string $newName): int
    {
        $rows = BookTag::query()->where('scope', 'system')->whereJsonContains('tags', $oldName)->get();

        foreach ($rows as $row) {
            $row->tags = $this->normalizeTags(array_map(
                fn (string $tag): string => mb_strtolower($tag) === mb_strtolower($oldName) ? $newName : $tag,
                $row->tags,
            ));
            $row->save();
        }

        return $rows->count();
    }

    public function deleteSystemTag(string $tag): int
    {
        $rows = BookTag::query()->where('scope', 'system')->whereJsonContains('tags', $tag)->get();

        foreach ($rows as $row) {
            $row->tags = array_values(array_filter(
                $row->tags,
                fn (string $existingTag): bool => mb_strtolower($existingTag) !== mb_strtolower($tag),
            ));
            $row->save();
        }

        return $rows->count();
    }

    /**
     * @param array<int, string> $tags
     * @return array<int, string>
     */
    public function normalizeTags(array $tags): array
    {
        $normalized = [];

        foreach ($tags as $tag) {
            $cleanTag = trim((string) $tag);

            if ($cleanTag === '') {
                continue;
            }

            $key = mb_strtolower($cleanTag);

            if (!array_key_exists($key, $normalized)) {
                $normalized[$key] = $cleanTag;
            }
        }

        return array_values($normalized);
    }
}

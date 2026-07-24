<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\UserBadge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BadgeApiController extends Controller
{
    /**
     * List all active badges, flagging which ones the authenticated user has earned.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;

        $earnedBadgeIds = UserBadge::forUser($userId)->pluck('badge_id')->unique();

        $badges = Badge::active()->ordered()->get()->map(fn (Badge $badge) => [
            'id' => $badge->id,
            'key' => $badge->key,
            'name' => $badge->name,
            'description' => $badge->description,
            'icon' => $badge->icon,
            'category' => $badge->category,
            'tier' => $badge->tier,
            'points' => $badge->points,
            'earned' => $earnedBadgeIds->contains($badge->id),
        ])->values();

        return response()->json([
            'badges' => $badges,
            'total_badges' => $badges->count(),
            'earned_badges' => $earnedBadgeIds->count(),
        ]);
    }

    /**
     * List only the badges the authenticated user has earned.
     */
    public function userBadges(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;

        /** @var \Illuminate\Database\Eloquent\Collection<int, UserBadge> $userBadges */
        $userBadges = UserBadge::getUserBadgesWithDetails($userId);

        $badges = $userBadges->map(fn (UserBadge $userBadge) => [
            'id' => $userBadge->badge->id,
            'key' => $userBadge->badge->key,
            'name' => $userBadge->badge->name,
            'description' => $userBadge->badge->description,
            'icon' => $userBadge->badge->icon,
            'category' => $userBadge->badge->category,
            'tier' => $userBadge->badge->tier,
            'points' => $userBadge->badge->points,
            'earned_at' => $userBadge->earned_at->toIso8601String(),
        ])->values();

        return response()->json([
            'badges' => $badges,
            'total_earned' => $badges->count(),
        ]);
    }

    /**
     * List the authenticated user's earned badges that have not yet been notified.
     */
    public function unnotified(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;

        /** @var \Illuminate\Database\Eloquent\Collection<int, UserBadge> $userBadges */
        $userBadges = UserBadge::forUser($userId)->unnotified()->with('badge')->get();

        $badges = $userBadges->map(fn (UserBadge $userBadge) => [
            'id' => $userBadge->badge->id,
            'key' => $userBadge->badge->key,
            'name' => $userBadge->badge->name,
            'description' => $userBadge->badge->description,
            'category' => $userBadge->badge->category,
            'tier' => $userBadge->badge->tier,
            'points' => $userBadge->badge->points,
            'is_repeatable' => $userBadge->badge->is_repeatable,
        ])->values();

        return response()->json([
            'count' => $badges->count(),
            'badges' => $badges,
        ]);
    }

    /**
     * Mark the given earned badges as notified for the authenticated user.
     */
    public function markNotified(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'badge_ids' => ['required', 'array', 'min:1'],
            'badge_ids.*' => ['integer'],
        ]);

        $userId = (string) $request->user()->id;

        $markedCount = UserBadge::forUser($userId)
            ->whereIn('badge_id', $validated['badge_ids'])
            ->update(['is_notified' => true]);

        return response()->json([
            'success' => true,
            'marked_count' => $markedCount,
        ]);
    }
}

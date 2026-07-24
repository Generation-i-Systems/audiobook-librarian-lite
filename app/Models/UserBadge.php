<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Badge $badge
 */
class UserBadge extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'badge_id',
        'earned_at',
        'criteria_met',
        'progress_value',
        'is_notified',
        'tier_level',
    ];

    protected $casts = [
        'earned_at' => 'datetime',
        'criteria_met' => 'array',
        'is_notified' => 'boolean',
    ];

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForDevice(Builder $query, string $deviceId): Builder
    {
        return $query->where('device_id', $deviceId);
    }

    public function scopeForUserOrDevice(Builder $query, string $userId, ?string $deviceId = null): Builder
    {
        $query->where('user_id', $userId);

        if ($deviceId) {
            $query->orWhere('device_id', $deviceId);
        }

        return $query;
    }

    public function scopeUnnotified(Builder $query): Builder
    {
        return $query->where('is_notified', false);
    }

    public function scopeRecentlyEarned(Builder $query, int $hours = 24): Builder
    {
        return $query->where('earned_at', '>=', Carbon::now()->subHours($hours));
    }

    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('earned_at');
    }

    public function markAsNotified(): void
    {
        $this->update(['is_notified' => true]);
    }

    public function getEarnedAtFormattedAttribute(): string
    {
        return $this->earned_at->format('M j, Y');
    }

    public function getEarnedAtHumanAttribute(): string
    {
        return $this->earned_at->diffForHumans();
    }

    public static function awardBadge(
        Badge $badge,
        string $userId,
        ?string $deviceId = null,
        array $criteriaMet = [],
        ?int $progressValue = null,
        ?Carbon $earnedAt = null
    ): self {
        if (!$badge->is_repeatable && $badge->hasBeenEarnedByUser($userId, $deviceId)) {
            throw new \InvalidArgumentException('Badge has already been earned and is not repeatable');
        }

        $tierLevel = 1;
        if ($badge->is_repeatable) {
            $tierLevel = $badge->getTimesEarnedByUser($userId, $deviceId) + 1;
        }

        return self::create([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'badge_id' => $badge->id,
            'earned_at' => $earnedAt ?? Carbon::now(),
            'criteria_met' => $criteriaMet,
            'progress_value' => $progressValue,
            'is_notified' => false,
            'tier_level' => $tierLevel,
        ]);
    }

    public static function getUserBadgesWithDetails(string $userId, ?string $deviceId = null): \Illuminate\Database\Eloquent\Collection
    {
        return self::with(['badge'])
            ->forUserOrDevice($userId, $deviceId)
            ->newest()
            ->get();
    }

    public static function getUserBadgeStats(string $userId, ?string $deviceId = null): array
    {
        $badges = self::forUserOrDevice($userId, $deviceId)->with('badge')->get();

        $totalBadges = $badges->count();
        $totalPoints = $badges->sum(function (self $userBadge) {
            return $userBadge->badge->points;
        });

        $categoryCounts = $badges->groupBy(function (self $userBadge) {
            return $userBadge->badge->category;
        })->map->count();

        $tierCounts = $badges->groupBy(function (self $userBadge) {
            return $userBadge->badge->tier;
        })->map->count();

        $recentBadges = $badges->filter(function (self $userBadge) {
            return $userBadge->earned_at->isAfter(Carbon::now()->subDays(7));
        })->count();

        return [
            'total_badges' => $totalBadges,
            'total_points' => $totalPoints,
            'categories' => $categoryCounts->toArray(),
            'tiers' => $tierCounts->toArray(),
            'recent_badges' => $recentBadges,
            'latest_badge' => $badges->first()?->badge?->name,
        ];
    }
}

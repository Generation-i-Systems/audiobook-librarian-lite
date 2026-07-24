<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'icon',
        'icon_path',
        'image_url',
        'category',
        'tier',
        'points',
        'criteria',
        'is_active',
        'is_repeatable',
        'sort_order',
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_active' => 'boolean',
        'is_repeatable' => 'boolean',
    ];

    public const CATEGORIES = [
        'listening' => 'Listening',
        'milestone' => 'Milestone',
        'streak' => 'Streak',
        'variety' => 'Variety',
        'social' => 'Social',
        'completion' => 'Completion',
        'speed' => 'Speed',
        'exploration' => 'Exploration',
        'dedication' => 'Dedication',
        'discovery' => 'Discovery',
        'seasonal' => 'Seasonal',
        'collection' => 'Collection',
        'challenge' => 'Challenge',
        'time_based' => 'Time-Based',
        'quality' => 'Quality',
        'community' => 'Community',
        'special' => 'Special Events',
        'habit' => 'Habit Building',
        'mastery' => 'Mastery',
    ];

    public const TIERS = [
        'bronze' => 'Bronze',
        'silver' => 'Silver',
        'gold' => 'Gold',
        'platinum' => 'Platinum',
        'diamond' => 'Diamond',
    ];

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function hasBeenEarnedByUser(string $userId, ?string $deviceId = null): bool
    {
        $query = $this->userBadges()->where('user_id', $userId);

        if ($deviceId) {
            $query->orWhere('device_id', $deviceId);
        }

        return $query->exists();
    }

    public function getTimesEarnedByUser(string $userId, ?string $deviceId = null): int
    {
        $query = $this->userBadges()->where('user_id', $userId);

        if ($deviceId) {
            $query->orWhere('device_id', $deviceId);
        }

        return $query->count();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeTier(Builder $query, string $tier): Builder
    {
        return $query->where('tier', $tier);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getTierWeightAttribute(): int
    {
        $weights = [
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4,
            'diamond' => 5,
        ];

        return $weights[$this->tier] ?? 1;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name . ' (' . ucfirst($this->tier) . ')';
    }

    public function evaluateCriteria(array $userStats): bool
    {
        foreach ($this->criteria as $type => $requirement) {
            if (!$this->checkSingleCriterion($type, $requirement, $userStats)) {
                return false;
            }
        }

        return true;
    }

    protected function checkSingleCriterion(string $type, $requirement, array $userStats): bool
    {
        $value = $userStats[$type] ?? 0;

        if (is_numeric($requirement)) {
            return $value >= $requirement;
        }

        if (is_array($requirement)) {
            if (isset($requirement['min']) && $value < $requirement['min']) {
                return false;
            }
            if (isset($requirement['max']) && $value > $requirement['max']) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function getProgressPercentage(array $userStats): int
    {
        $criteria = $this->criteria;
        $totalCriteria = count($criteria);

        if ($totalCriteria === 0) {
            return 100;
        }

        $progress = 0.0;

        foreach ($criteria as $type => $requirement) {
            $progress += $this->getSingleCriterionProgress($type, $requirement, $userStats);
        }

        return (int) floor(($progress / $totalCriteria) * 100);
    }

    protected function getSingleCriterionProgress(string $type, $requirement, array $userStats): float
    {
        $value = $userStats[$type] ?? 0;

        if (is_numeric($requirement)) {
            if ((float) $requirement <= 0.0) {
                return 1.0;
            }

            return min(1.0, max(0.0, ((float) $value) / ((float) $requirement)));
        }

        if (is_array($requirement)) {
            $min = isset($requirement['min']) && is_numeric($requirement['min'])
                ? (float) $requirement['min']
                : null;
            $max = isset($requirement['max']) && is_numeric($requirement['max'])
                ? (float) $requirement['max']
                : null;

            if ($min !== null && $max !== null) {
                if ($value < $min) {
                    return $min > 0.0 ? min(1.0, max(0.0, ((float) $value) / $min)) : 0.0;
                }

                if ($value > $max) {
                    return 0.0;
                }

                return 1.0;
            }

            if ($min !== null) {
                return $min > 0.0 ? min(1.0, max(0.0, ((float) $value) / $min)) : 0.0;
            }

            if ($max !== null) {
                return $value <= $max ? 1.0 : 0.0;
            }
        }

        return $this->checkSingleCriterion($type, $requirement, $userStats) ? 1.0 : 0.0;
    }
}

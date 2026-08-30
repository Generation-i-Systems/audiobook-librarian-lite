<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool|null $can_force_delete
 */
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
        'created_by',
        'updated_by',
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

    /**
     * Badge criteria types — mirrors the stat keys BadgeService::getUserListeningStatistics()
     * actually computes, so the admin rule builder only offers stats that can be evaluated.
     */
    public const CRITERIA_TYPES = [
        'total_listening_time' => 'Total listening time in seconds',
        'session_count' => 'Number of listening sessions',
        'books_started' => 'Number of books started',
        'books_completed' => 'Number of books completed',
        'total_listening_days' => 'Total number of days with listening activity',
        'longest_session' => 'Longest single session duration in seconds',
        'current_streak' => 'Current listening streak (days)',
        'longest_streak' => 'Longest listening streak ever (days)',
        'genres_explored' => 'Number of different genres listened to',
        'authors_explored' => 'Number of different authors listened to',
        'narrator_variety' => 'Different narrators listened to',
        'weekend_listening' => 'Weekend listening sessions',
        'weekend_listening_time' => 'Weekend listening time in seconds',
        'books_completed_this_month' => 'Books completed in the current month',
        'books_completed_this_week' => 'Books completed in the current week',
        'books_completed_on_weekend' => 'Books completed on a weekend',
        'quick_finishes' => 'Books finished quickly after starting',
        'series_explored' => 'Series where at least one book was completed',
        'series_completion' => 'Complete book series finished',
        'bookmarks_created' => 'Number of bookmarks created',
        'books_reviewed' => 'Number of books reviewed or rated',
        'library_size' => 'Number of books in personal library',
        'completion_rate' => 'Percentage of started books completed',
        'chapter_completion' => 'Number of chapters completed',
        'device_variety' => 'Number of different devices used',
        'repeat_listening' => 'Books listened to multiple times',
        'language_variety' => 'Books in different languages',
        'classic_books_explored' => 'Classic books completed',
        'indie_books_explored' => 'Independent/self-published books completed',
        'recommendations_sent' => 'Recommendations sent to other users',
        'discovery_rate' => 'Recommendations read/acted on',
        'playlist_count' => 'Number of playlists created',
        'morning_sessions' => 'Sessions during morning hours',
        'evening_sessions' => 'Sessions during evening hours',
        'commute_sessions' => 'Sessions during typical commute hours',
        'new_year_sessions' => 'Sessions on New Year\'s Day',
        'spring_sessions' => 'Sessions during spring',
        'summer_sessions' => 'Sessions during summer',
        'autumn_sessions' => 'Sessions during autumn',
        'winter_sessions' => 'Sessions during winter',
        'anniversary_sessions' => 'Sessions on the listening anniversary date',
        'speed_time_110' => 'Seconds listened at 1.10x speed or faster',
        'speed_time_125' => 'Seconds listened at 1.25x speed or faster',
        'speed_time_150' => 'Seconds listened at 1.50x speed or faster',
        'speed_time_175' => 'Seconds listened at 1.75x speed or faster',
        'speed_time_200' => 'Seconds listened at 2.00x speed or faster',
        'speed_variety' => 'Number of distinct playback speeds used substantially',
        'weekly_goal_streak' => 'Consecutive weeks a listening goal was met',
        'monthly_goal_streak' => 'Consecutive months a listening goal was met',
        'yearly_goal_achieved' => 'Yearly listening goal achieved',
        'action_app_installed' => 'App installed on a platform',
        'action_app_installed_android' => 'App installed on Android',
        'action_app_installed_ios' => 'App installed on iOS',
        'action_app_installed_desktop' => 'App installed on Desktop',
        'action_book_downloaded' => 'Books downloaded',
        'action_book_started' => 'Books started playing',
        'action_skin_changed' => 'Player skin changed',
        'action_gallery_skin_downloaded' => 'Skins downloaded from gallery',
        'action_theme_changed' => 'Color theme changed',
        'action_drive_mode_activated' => 'Drive mode activated',
        'action_bookmark_created' => 'Bookmarks created via player',
    ];

    /**
     * Operators supported by the version-2 (multi-condition) rule engine.
     */
    public const RULE_OPERATORS = [
        '>=' => 'At least',
        '<=' => 'At most',
        '>' => 'More than',
        '<' => 'Less than',
        '==' => 'Exactly',
        'between' => 'Between',
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
        $criteria = $this->criteria;

        if ($this->isConditionGroup($criteria)) {
            return $this->evaluateConditionGroup($criteria, $userStats);
        }

        foreach ($criteria as $type => $requirement) {
            if (!$this->checkSingleCriterion($type, $requirement, $userStats)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether a criteria array uses the version-2 condition-group format.
     */
    protected function isConditionGroup(array $criteria): bool
    {
        return isset($criteria['conditions']) && is_array($criteria['conditions']);
    }

    /**
     * Recursively evaluate a version-2 AND/OR condition group.
     *
     * @param array{logic?: string, conditions: array} $group
     */
    protected function evaluateConditionGroup(array $group, array $userStats): bool
    {
        $logic = strtoupper((string) ($group['logic'] ?? 'AND'));
        $conditions = $group['conditions'];

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $node) {
            if ($this->isConditionGroup($node)) {
                $result = $this->evaluateConditionGroup($node, $userStats);
            } else {
                $result = $this->evaluateCondition($node, $userStats);
            }

            if ($logic === 'OR') {
                if ($result) {
                    return true;
                }
            } elseif (!$result) {
                return false;
            }
        }

        return $logic !== 'OR';
    }

    /**
     * Evaluate a single leaf condition, e.g. {"stat": "books_completed", "operator": ">=", "value": 10}.
     */
    protected function evaluateCondition(array $condition, array $userStats): bool
    {
        $stat = $condition['stat'] ?? null;
        $operator = $condition['operator'] ?? '>=';
        $target = $condition['value'] ?? null;

        if ($stat === null || $target === null) {
            return false;
        }

        $value = $userStats[$stat] ?? 0;

        return match ($operator) {
            '>=' => $value >= $target,
            '<=' => $value <= $target,
            '>' => $value > $target,
            '<' => $value < $target,
            '==' => $value == $target,
            'between' => is_array($target) && count($target) === 2
                && $value >= $target[0] && $value <= $target[1],
            default => false,
        };
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

        if ($this->isConditionGroup($criteria)) {
            $leaves = [];
            $this->collectLeafConditions($criteria, $leaves);

            if (empty($leaves)) {
                return 100;
            }

            $progress = 0.0;
            foreach ($leaves as $leaf) {
                $progress += $this->getConditionProgress($leaf, $userStats);
            }

            return (int) floor(($progress / count($leaves)) * 100);
        }

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

    /**
     * Flatten a version-2 condition group into its leaf conditions (ignoring AND/OR structure),
     * matching the existing "average of criteria" progress semantic used for the legacy format.
     */
    protected function collectLeafConditions(array $node, array &$leaves): void
    {
        if ($this->isConditionGroup($node)) {
            foreach ($node['conditions'] as $child) {
                $this->collectLeafConditions($child, $leaves);
            }
            return;
        }

        $leaves[] = $node;
    }

    protected function getConditionProgress(array $condition, array $userStats): float
    {
        $stat = $condition['stat'] ?? null;
        $operator = $condition['operator'] ?? '>=';
        $target = $condition['value'] ?? null;

        if ($stat === null || $target === null) {
            return 0.0;
        }

        $value = (float) ($userStats[$stat] ?? 0);

        if ($operator === 'between' && is_array($target) && count($target) === 2) {
            [$min, $max] = [(float) $target[0], (float) $target[1]];
            if ($value > $max) {
                return 0.0;
            }
            return $min > 0.0 ? min(1.0, max(0.0, $value / $min)) : 1.0;
        }

        if (in_array($operator, ['>=', '>', '=='], true) && is_numeric($target)) {
            $target = (float) $target;
            return $target > 0.0 ? min(1.0, max(0.0, $value / $target)) : 1.0;
        }

        return $this->evaluateCondition($condition, $userStats) ? 1.0 : 0.0;
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
            $min = isset($requirement['min']) && is_numeric($requirement['min']) ? (float) $requirement['min'] : null;
            $max = isset($requirement['max']) && is_numeric($requirement['max']) ? (float) $requirement['max'] : null;

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

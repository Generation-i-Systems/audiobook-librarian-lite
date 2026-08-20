<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use Illuminate\Contracts\View\View;

class BadgeController extends Controller
{
    public function index(): View
    {
        $tierOrder = ['bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4, 'diamond' => 5];
        $badges = Badge::query()->orderBy('category')->orderBy('sort_order')->get()
            ->sortBy(fn (Badge $badge): array => [$badge->category, $badge->sort_order, $tierOrder[$badge->tier] ?? 99, $badge->name]);

        return view('admin.badges.index', ['badgesByCategory' => $badges->groupBy('category')]);
    }
}

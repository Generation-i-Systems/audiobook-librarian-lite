@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex align-items-center mb-4 justify-content-between">
        <div class="d-flex align-items-center">
            @php
                $initial = strtoupper(substr($user['name'] ?? '?', 0, 1));
                $fallbackSvg = 'data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%2264%22%20height%3D%2264%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%2264%22%20height%3D%2264%22%20rx%3D%2232%22%20ry%3D%2232%22%20fill%3D%22%236c757d%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2255%25%22%20font-family%3D%22Arial%2Csans-serif%22%20font-size%3D%2232%22%20fill%3D%22%23fff%22%20text-anchor%3D%22middle%22%20dominant-baseline%3D%22middle%22%3E' . $initial . '%3C%2Ftext%3E%3C%2Fsvg%3E';
            @endphp
            @if(!empty($user['photo_url']))
                <img src="{{ $user['photo_url'] }}"
                     alt="Profile Photo"
                     class="rounded-circle me-3"
                     style="width: 64px; height: 64px; object-fit: cover;"
                     onerror="this.onerror=null; this.src='{{ $fallbackSvg }}';">
            @else
                <img src="{{ $fallbackSvg }}"
                     alt="{{ $initial }}"
                     class="rounded-circle me-3">
            @endif
            <div>
                <h1 class="h3 mb-0">{{ $user['name'] ?? 'Unknown User' }}
                    @if(($user['role'] ?? '') === 'unverified')
                        <span class="badge bg-warning text-dark">Unverified</span>
                    @else
                        <span class="badge bg-success">Verified</span>
                    @endif

                    @if(!empty($user['google_id']))
                        <span class="badge bg-info">
                            <i class="fab fa-google"></i> Google Sign-In
                        </span>
                    @endif
                </h1>
                <div class="text-muted">
                    {{ $user['email'] ?? '' }} ({{ $user['username'] ?? '' }})
                </div>
            </div>
        </div>
        <div>
            @if(auth()->user()->role === 'admin' || auth()->user()->role === 'super-admin')
                <a href="{{ route('admin.users.edit', $user['id']) }}" class="btn btn-warning">
                    <i class="fas fa-edit me-1"></i> Edit User
                </a>
            @endif
        </div>
    </div>

    <div class="row">
        <!-- Profile Info Card -->
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Profile Info</h5>
                </div>
                <div class="card-body">
                    <p><strong>Role:</strong> {{ ucfirst($user['role'] ?? 'User') }}</p>
                    <p><strong>Joined:</strong> {{ !empty($user['created_at']) ? \Carbon\Carbon::parse($user['created_at'])->format('M j, Y') : 'N/A' }}</p>
                    <p><strong>Last Active:</strong> {{ !empty($user['last_active_at']) ? \Carbon\Carbon::parse($user['last_active_at'])->diffForHumans() : 'N/A' }}</p>
                </div>
            </div>
        </div>

        <div class="col-md-9 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Listening Statistics</h5>
                </div>
                <div class="card-body">
                    @php($statistics = $user['listening_statistics'] ?? [])
                    <div class="row">
                        <div class="col-md-4 mb-2"><strong>Synced events:</strong> {{ number_format($statistics['event_count'] ?? 0) }}</div>
                        <div class="col-md-4 mb-2"><strong>Listening sessions:</strong> {{ number_format($statistics['session_count'] ?? 0) }}</div>
                        <div class="col-md-4 mb-2"><strong>Listening time:</strong> {{ number_format(($statistics['total_seconds'] ?? 0) / 60, 1) }} min</div>
                        <div class="col-md-4 mb-2"><strong>Books started:</strong> {{ number_format($statistics['books_started'] ?? 0) }}</div>
                        <div class="col-md-4 mb-2"><strong>Active days:</strong> {{ number_format($statistics['active_days'] ?? 0) }}</div>
                        <div class="col-md-4 mb-2"><strong>Current / longest streak:</strong> {{ $statistics['current_streak'] ?? 0 }} / {{ $statistics['longest_streak'] ?? 0 }} days</div>
                    </div>

                    <p class="mb-0 text-muted">Last event: {{ $statistics['last_listened_at'] ?? 'No synced listening events' }}</p>
                </div>
            </div>
        </div>
    </div>

    @php($activityData = $activityData ?? [])
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Current Positions</h5></div>
                <div class="card-body p-0">
                    @if (empty($activityData['progress']))
                        <p class="p-3 mb-0 text-muted">No current listening positions have been synced.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Book</th><th>Position</th><th>Last listened</th></tr></thead>
                                <tbody>
                                    @foreach ($activityData['progress'] as $progress)
                                        <tr>
                                            <td>{{ $progress['book_title'] }}@if (!empty($progress['book_author'])) <small class="text-muted">by {{ $progress['book_author'] }}</small>@endif</td>
                                            <td>{{ number_format((float) $progress['percentage'], 1) }}%</td>
                                            <td>{{ !empty($progress['last_listened_at']) ? \Carbon\Carbon::parse($progress['last_listened_at'])->format('M j, Y g:i A') : 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Badge Progress</h5></div>
                <div class="card-body p-0">
                    @if (empty($activityData['badges_by_category']))
                        <p class="p-3 mb-0 text-muted">No badge progress is available.</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($activityData['badges_by_category'] as $category => $badges)
                                @foreach ($badges as $badge)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>{{ $badge['emoji'] ?? '🏅' }} {{ $badge['name'] }} <small class="text-muted">({{ ucfirst($category) }})</small></span>
                                        <span class="badge {{ $badge['is_earned'] ? 'bg-success' : 'bg-secondary' }}">{{ $badge['is_earned'] ? 'Earned' : 'Next' }}</span>
                                    </li>
                                @endforeach
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Achievements</h5></div>
                <div class="card-body p-0">
                    @if (empty($user['badges']))
                        <p class="p-3 mb-0 text-muted">No achievements have been earned.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Badge</th><th>Tier</th><th>Points</th><th>Earned</th></tr></thead>
                                <tbody>
                                    @foreach ($user['badges'] as $userBadge)
                                        <tr>
                                            <td>{{ $userBadge['badge']['name'] ?? 'Deleted badge' }}</td>
                                            <td>{{ ucfirst($userBadge['badge']['tier'] ?? '') }}</td>
                                            <td>{{ $userBadge['badge']['points'] ?? 0 }}</td>
                                            <td>{{ !empty($userBadge['earned_at']) ? \Carbon\Carbon::parse($userBadge['earned_at'])->format('M j, Y') : 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Recent Synced Events</h5></div>
                <div class="card-body p-0">
                    @if (empty($user['events']))
                        <p class="p-3 mb-0 text-muted">No listening events have been synced.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>When</th><th>Event</th><th>Book</th><th>Position</th></tr></thead>
                                <tbody>
                                    @foreach ($user['events'] as $event)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($event['occurred_at'])->format('M j, Y g:i A') }}</td>
                                            <td>{{ str_replace('_', ' ', $event['event_type']) }}</td>
                                            <td>{{ $event['title'] }}@if (!empty($event['author'])) <small class="text-muted">by {{ $event['author'] }}</small>@endif</td>
                                            <td>{{ number_format($event['position_ms'] / 1000, 1) }} s</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">User Data</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4"><strong>Groups:</strong> {{ count($user['groups'] ?? []) }}</div>
                        <div class="col-md-4"><strong>Friends:</strong> {{ count($user['friendships'] ?? []) }}</div>
                        <div class="col-md-4"><strong>Bookmarks:</strong> {{ count($user['bookmarks'] ?? []) }}</div>
                        <div class="col-md-4"><strong>Listening goals:</strong> {{ count($user['listening_goals'] ?? []) }}</div>
                        <div class="col-md-4"><strong>Achievements:</strong> {{ count($user['badges'] ?? []) }}</div>
                        <div class="col-md-4"><strong>Book statuses:</strong> {{ count($user['book_statuses'] ?? []) }}</div>
                    </div>

                    @foreach ([
                        'groups' => 'Groups',
                        'friendships' => 'Friends',
                        'sent_friend_invitations' => 'Sent invitations',
                        'received_friend_invitations' => 'Received invitations',
                        'bookmarks' => 'Bookmarks',
                        'listening_goals' => 'Listening goals',
                        'book_statuses' => 'Book statuses',
                    ] as $key => $label)
                        @if (!empty($user[$key]))
                            <hr>
                            <h6>{{ $label }}</h6>
                            <pre class="mb-0 small bg-light border rounded p-2">{{ json_encode($user[$key], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

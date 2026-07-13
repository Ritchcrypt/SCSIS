<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DaoSystem Admin</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-100 text-slate-900">
    <div class="flex min-h-screen">
        <aside class="fixed left-0 top-0 z-30 flex h-screen w-72 flex-col overflow-hidden bg-blue-950 text-white">
            <div class="shrink-0 flex items-center gap-3 border-b border-blue-900 px-6 py-6">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600">
                    <span class="text-lg font-bold">🛡</span>
                </div>

                <div>
                    <h1 class="text-lg font-bold leading-tight">SCSISystem</h1>
                    <p class="text-sm text-blue-200">Dao, Capiz</p>
                </div>
            </div>

            @php
                $authUser = auth()->user();
                $role = strtolower(trim($authUser?->role ?? ''));

                $authPhotoPath = $authUser && \Illuminate\Support\Facades\Schema::hasColumn('users', 'profile_photo_path')
                    ? ($authUser->profile_photo_path ?? null)
                    : null;

                $authPhotoUrl = $authPhotoPath && Route::has('users.profile-photo')
                    ? route('users.profile-photo', $authUser)
                    : null;

                $authInitial = strtoupper(mb_substr($authUser?->name ?? 'U', 0, 1));

                $authProfileUrl = $authUser && $authUser->role === 'admin' && Route::has('admin.users.edit')
                    ? route('admin.users.edit', $authUser)
                    : '#';

                $navItems = match ($role) {
                    'admin' => [
                        [
                            'label' => 'Dashboard',
                            'icon' => '▦',
                            'route' => 'admin.dashboard',
                            'active' => ['admin.dashboard'],
                        ],
                        [
                            'label' => 'Incidents',
                            'icon' => '📄',
                            'route' => 'admin.incidents.index',
                            'active' => ['admin.incidents.*'],
                        ],
                        [
                            'label' => 'Tanod Alerts',
                            'icon' => '🔔',
                            'route' => 'admin.tanod-alerts.index',
                            'active' => ['admin.tanod-alerts.*'],
                        ],
                        [
                            'label' => 'Tanod Roster',
                            'icon' => '👥',
                            'route' => 'admin.tanods.index',
                            'active' => ['admin.tanods.*'],
                        ],
                        [
                            'label' => 'Tanod Tasks',
                            'icon' => '📋',
                            'route' => 'admin.tanod-tasks.index',
                            'active' => ['admin.tanod-tasks.*'],
                        ],
                        [
                            'label' => 'Case Management',
                            'icon' => '📘',
                            'route' => 'admin.cases.index',
                            'active' => ['admin.cases.*'],
                        ],
                        [
                            'label' => 'Announcements',
                            'icon' => '📢',
                            'route' => 'admin.announcements.index',
                            'active' => ['admin.announcements.*'],
                        ],
                        [
                            'label' => 'Emergency Mode',
                            'icon' => '🚨',
                            'route' => 'admin.emergency-mode.index',
                            'active' => ['admin.emergency-mode.*'],
                        ],
                        [
                            'label' => 'Map',
                            'icon' => '🗺',
                            'route' => 'admin.map.index',
                            'active' => ['admin.map.*'],
                        ],
                        [
                            'label' => 'Reports',
                            'icon' => '📊',
                            'route' => 'admin.reports.index',
                            'active' => ['admin.reports.*'],
                        ],
                        [
                            'label' => 'User Management',
                            'icon' => '⚙',
                            'route' => 'admin.users.index',
                            'active' => ['admin.users.*'],
                        ],
                    ],

                    'official', 'dao' => [
                        [
                            'label' => 'Dashboard',
                            'icon' => '▦',
                            'route' => 'official.dashboard',
                            'active' => ['official.dashboard'],
                        ],
                        [
                            'label' => 'Incidents',
                            'icon' => '📄',
                            'route' => 'official.incidents.index',
                            'active' => ['official.incidents.*'],
                        ],
                    ],

                    'tanod' => [
                        [
                            'label' => 'Dashboard',
                            'icon' => '▦',
                            'route' => 'tanod.dashboard',
                            'active' => ['tanod.dashboard'],
                        ],
                        [
                            'label' => 'Tanod Tasks',
                            'icon' => '📋',
                            'route' => 'tanod.tanod-tasks.index',
                            'active' => ['tanod.tanod-tasks.*'],
                        ],
                        [
                            'label' => 'Tanod Alerts',
                            'icon' => '🔔',
                            'route' => 'tanod.tanod-alerts.index',
                            'active' => ['tanod.tanod-alerts.*'],
                        ],
                        [
                            'label' => 'Assigned Incidents',
                            'icon' => '📄',
                            'route' => 'tanod.incidents.index',
                            'active' => ['tanod.incidents.*'],
                        ],
                    ],

                    'resident' => [
                        [
                            'label' => 'Dashboard',
                            'icon' => '▦',
                            'route' => 'resident.dashboard',
                            'active' => ['resident.dashboard'],
                        ],
                        [
                            'label' => 'Report Incident',
                            'icon' => '➕',
                            'route' => 'resident.incidents.create',
                            'active' => ['resident.incidents.create'],
                        ],
                        [
                            'label' => 'My Reports',
                            'icon' => '📄',
                            'route' => 'resident.incidents.index',
                            'active' => ['resident.incidents.index', 'resident.incidents.show'],
                        ],
                    ],

                    default => [
                        [
                            'label' => 'Dashboard',
                            'icon' => '▦',
                            'route' => 'dashboard',
                            'active' => ['dashboard'],
                        ],
                    ],
                };
            @endphp

            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-4 py-5">
                @foreach ($navItems as $item)
                    @continue(! Route::has($item['route']))

                    @php
                        $isActive = collect($item['active'])
                            ->contains(fn ($pattern) => request()->routeIs($pattern));
                    @endphp

                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                       {{ $isActive
                            ? 'bg-blue-600 font-semibold text-white'
                            : 'font-medium text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        <span>{{ $item['icon'] }}</span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="shrink-0 border-t border-blue-900 px-4 py-4">
                <details class="relative group">
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-2xl px-2 py-2 hover:bg-blue-900">
                        <div class="relative h-10 w-10 shrink-0">
                            @if ($authPhotoUrl)
                                <img src="{{ $authPhotoUrl }}"
                                     alt="{{ $authUser->name }} profile photo"
                                     class="h-10 w-10 rounded-full border border-blue-800 object-cover shadow-sm"
                                     onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">

                                <div class="hidden h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">
                                    {{ $authInitial }}
                                </div>
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">
                                    {{ $authInitial }}
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-white">
                                {{ $authUser?->name ?? 'User' }}
                            </p>
                            <p class="truncate text-xs text-blue-200">
                                {{ ucfirst($authUser?->role ?? 'User') }}
                            </p>
                        </div>

                        <span class="text-sm text-blue-200 group-open:rotate-180">⌃</span>
                    </summary>

                    <div class="absolute bottom-full left-0 right-0 z-50 mb-3 overflow-hidden rounded-2xl border border-blue-900 bg-blue-950 shadow-2xl">
                        <div class="border-b border-blue-900 px-4 py-4">
                            <div class="flex items-center gap-3">
                                <div class="relative h-11 w-11 shrink-0">
                                    @if ($authPhotoUrl)
                                        <img src="{{ $authPhotoUrl }}"
                                             alt="{{ $authUser->name }} profile photo"
                                             class="h-11 w-11 rounded-full border border-blue-800 object-cover shadow-sm"
                                             onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">

                                        <div class="hidden h-11 w-11 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">
                                            {{ $authInitial }}
                                        </div>
                                    @else
                                        <div class="flex h-11 w-11 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">
                                            {{ $authInitial }}
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-white">
                                        {{ $authUser?->name ?? 'User' }}
                                    </p>
                                    <p class="truncate text-xs text-blue-200">
                                        {{ ucfirst($authUser?->role ?? 'User') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="py-2">
                            <a href="{{ $authProfileUrl }}"
                               class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-blue-100 hover:bg-blue-900 hover:text-white">
                                <span>👤</span>
                                <span>Profile</span>
                            </a>

                        </div>

                        <div class="border-t border-blue-900 py-2">
                            <form method="POST" action="{{ route('logout') }}" class="m-0">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">

                                <button type="submit"
                                        class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-medium text-blue-100 hover:bg-blue-900 hover:text-white">
                                    <span>↪</span>
                                    <span>Logout</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </details>
            </div>
        </aside>

        <div class="min-h-screen flex-1 pl-72">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-end border-b border-slate-200 bg-white px-8">
                <div class="flex items-center gap-4">
                    @php
                        $importantNotificationTypes = [
                            'incident',
                            'dispatch',
                            'escalation',
                            'emergency',
                            'calamity',
                            'resolved',
                            'announcement',
                        ];

                        $notificationTypeLabels = [
                            'incident' => 'Incident Update',
                            'dispatch' => 'Dispatch',
                            'escalation' => 'Escalation',
                            'emergency' => 'Emergency',
                            'calamity' => 'Calamity',
                            'resolved' => 'Resolved',
                            'announcement' => 'Announcement',
                        ];

                        $unreadNotificationCount = 0;
                        $notificationUrl = '#';
                        $latestUnreadNotifications = collect();

                        if ($authUser) {
                            $notificationQuery = \App\Models\UserNotification::query()
                                ->where('is_read', false)
                                ->whereIn('type', $importantNotificationTypes);

                            /*
                            |--------------------------------------------------------------------------
                            | Notification Visibility Rule
                            |--------------------------------------------------------------------------
                            | Admin sees all important unread system notifications.
                            | Other roles only see unread notifications assigned to their account.
                            */
                            if ($authUser->role !== 'admin') {
                                $notificationQuery->where('user_id', $authUser->id);
                            }

                            $unreadNotificationCount = (clone $notificationQuery)->count();

                            $latestUnreadNotifications = (clone $notificationQuery)
                                ->latest()
                                ->limit(6)
                                ->get();

                            $notificationUrl = match ($authUser->role) {
                                'admin' => Route::has('admin.tanod-alerts.index')
                                    ? route('admin.tanod-alerts.index')
                                    : '#',

                                'tanod' => Route::has('tanod.tanod-alerts.index')
                                    ? route('tanod.tanod-alerts.index')
                                    : '#',

                                'official' => Route::has('official.incidents.index')
                                    ? route('official.incidents.index')
                                    : '#',

                                'resident' => Route::has('resident.incidents.index')
                                    ? route('resident.incidents.index')
                                    : '#',

                                default => '#',
                            };
                        }
                    @endphp

                    <details class="relative">
                        <summary class="relative inline-flex cursor-pointer list-none items-center justify-center">
                            <span class="text-xl">🔔</span>

                            @if ($unreadNotificationCount > 0)
                                <span class="absolute -right-2 -top-2 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white">
                                    {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
                                </span>
                            @endif
                        </summary>

                        <div class="absolute right-0 top-9 z-50 w-96 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                                <div>
                                    <p class="text-sm font-bold text-slate-900">Unread Notifications</p>
                                    <p class="text-xs text-slate-500">Important new updates only</p>
                                </div>

                                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">
                                    {{ $unreadNotificationCount }}
                                </span>
                            </div>

                            <div class="max-h-96 overflow-y-auto">
                                @forelse ($latestUnreadNotifications as $notification)
                                    @php
                                        $type = strtolower($notification->type ?? 'incident');
                                        $typeLabel = $notificationTypeLabels[$type] ?? ucfirst($type);

                                        $incidentRoute = null;

                                        if ($notification->source_id) {
                                            $incidentRouteName = match ($authUser?->role) {
                                                'admin' => 'admin.incidents.show',
                                                'official' => 'official.incidents.show',
                                                'tanod' => 'tanod.incidents.show',
                                                'resident' => 'resident.incidents.show',
                                                default => null,
                                            };

                                            if ($incidentRouteName && Route::has($incidentRouteName)) {
                                                $incidentRoute = route($incidentRouteName, $notification->source_id);
                                            }
                                        }
                                    @endphp

                                    <div class="border-b border-slate-100 px-4 py-3 hover:bg-slate-50">
                                        <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-blue-700">
                                            {{ $typeLabel }}
                                        </span>

                                        <h3 class="mt-2 text-sm font-bold text-slate-900">
                                            {{ $notification->title ?? 'Untitled notification' }}
                                        </h3>

                                        <p class="mt-1 text-xs leading-5 text-slate-600">
                                            {{ $notification->message ?? 'No notification message provided.' }}
                                        </p>

                                        <p class="mt-2 text-[11px] text-slate-400">
                                            {{ $notification->created_at?->diffForHumans() ?? 'No date' }}
                                        </p>

                                        @if ($incidentRoute)
                                            <a href="{{ $incidentRoute }}"
                                               class="mt-3 inline-flex text-xs font-bold text-blue-700 hover:text-blue-900">
                                                Open Related Incident →
                                            </a>
                                        @endif
                                    </div>
                                @empty
                                    <div class="px-4 py-8 text-center">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-xl">
                                            🔔
                                        </div>

                                        <p class="mt-3 text-sm font-bold text-slate-900">
                                            No unread notifications
                                        </p>

                                        <p class="mt-1 text-xs text-slate-500">
                                            New important updates will appear here.
                                        </p>
                                    </div>
                                @endforelse
                            </div>

                            <div class="border-t border-slate-200 bg-slate-50 px-4 py-3">
                                <a href="{{ $notificationUrl }}"
                                   class="block rounded-xl bg-blue-600 px-4 py-2 text-center text-sm font-bold text-white hover:bg-blue-700">
                                    View All Alerts
                                </a>
                            </div>
                        </div>
                    </details>
                </div>
            </header>

            <main class="p-8">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>

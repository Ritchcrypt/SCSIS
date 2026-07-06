<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DaoSystem Admin</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-100 text-slate-900">
    <div class="flex min-h-screen">
        <aside class="fixed inset-y-0 left-0 z-30 flex w-72 flex-col bg-blue-950 text-white">
            <div class="flex items-center gap-3 border-b border-blue-900 px-6 py-6">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600">
                    <span class="text-lg font-bold">🛡</span>
                </div>

                <div>
                    <h1 class="text-lg font-bold leading-tight">BrgySafe</h1>
                    <p class="text-sm text-blue-200">Dao, Capiz</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-5">
                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                   {{ request()->routeIs('admin.dashboard')
                        ? 'bg-blue-600 font-semibold text-white'
                        : 'font-medium text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                    <span>▦</span>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('admin.incidents.index') }}"
                   class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                   {{ request()->routeIs('admin.incidents.*')
                        ? 'bg-blue-600 font-semibold text-white'
                        : 'font-medium text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                    <span>📄</span>
                    <span>Incidents</span>
                </a>

                <a href="{{ Route::has('admin.tanod-alerts.index') ? route('admin.tanod-alerts.index') : '#' }}"
                   class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                   {{ request()->routeIs('admin.tanod-alerts.*')
                        ? 'bg-blue-600 font-semibold text-white'
                        : 'font-medium text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                    <span>🔔</span>
                    <span>Tanod Alerts</span>
                </a>

                <a href="{{ Route::has('admin.cases.index') ? route('admin.cases.index') : '#' }}"
                   class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                   {{ request()->routeIs('admin.cases.*')
                        ? 'bg-blue-600 font-semibold text-white'
                        : 'font-medium text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                    <span>📘</span>
                    <span>Case Management</span>
                </a>

                <a href="{{ Route::has('admin.announcements.index') ? route('admin.announcements.index') : '#' }}"
                   class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                   {{ request()->routeIs('admin.announcements.*')
                        ? 'bg-blue-600 font-semibold text-white'
                        : 'font-medium text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                    <span>📢</span>
                    <span>Announcements</span>
                </a>

                <a href="{{ Route::has('admin.emergency.index') ? route('admin.emergency.index') : '#' }}"
                   class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                   {{ request()->routeIs('admin.emergency.*')
                        ? 'bg-blue-600 font-semibold text-white'
                        : 'font-medium text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                    <span>🚨</span>
                    <span>Emergency Mode</span>
                </a>

                <a href="{{ Route::has('admin.tanods.index') ? route('admin.tanods.index') : '#' }}"
                   class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                   {{ request()->routeIs('admin.tanods.*')
                        ? 'bg-blue-600 font-semibold text-white'
                        : 'font-medium text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                    <span>👥</span>
                    <span>Tanod Roster</span>
                </a>

                <a href="{{ Route::has('admin.map.index') ? route('admin.map.index') : '#' }}"
                   class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                   {{ request()->routeIs('admin.map.*')
                        ? 'bg-blue-600 font-semibold text-white'
                        : 'font-medium text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                    <span>🗺</span>
                    <span>Map</span>
                </a>

                <a href="{{ Route::has('admin.reports.index') ? route('admin.reports.index') : '#' }}"
                   class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                   {{ request()->routeIs('admin.reports.*')
                        ? 'bg-blue-600 font-semibold text-white'
                        : 'font-medium text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                    <span>📊</span>
                    <span>Reports</span>
                </a>

                <a href="{{ Route::has('admin.users.index') ? route('admin.users.index') : '#' }}"
                   class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                   {{ request()->routeIs('admin.users.*')
                        ? 'bg-blue-600 font-semibold text-white'
                        : 'font-medium text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                    <span>⚙</span>
                    <span>User Management</span>
                </a>
            </nav>

            <div class="border-t border-blue-900 px-6 py-5">
                <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                <p class="text-xs text-blue-200">{{ ucfirst(auth()->user()->role) }}</p>

                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf

                    <button type="submit" class="text-sm text-blue-200 hover:text-white">
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <div class="min-h-screen flex-1 pl-72">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-end border-b border-slate-200 bg-white px-8">
                <div class="flex items-center gap-4">
                    @php
                        $authUser = auth()->user();

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

                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-950 text-sm font-bold text-white">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500">{{ ucfirst(auth()->user()->role) }}</p>
                    </div>
                </div>
            </header>

            <main class="p-8">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
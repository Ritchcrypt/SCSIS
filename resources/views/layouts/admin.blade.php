<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TabangNow System</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-100 text-slate-900">
    <div class="flex min-h-screen">
        <aside id="adminSidebar"
               class="fixed left-0 top-0 z-30 flex h-screen w-72 translate-x-0 flex-col overflow-hidden bg-blue-950 text-white transition-transform duration-300 ease-in-out">
            @php
                $layoutAuthUser = auth()->user();

                $systemSetting = null;

                if (
                    class_exists(\App\Models\SystemSetting::class)
                    && \Illuminate\Support\Facades\Schema::hasTable('system_settings')
                ) {
                    $systemSetting = \App\Models\SystemSetting::query()->first();
                }

                $systemName = $systemSetting?->system_name ?: 'SCSISystem';
                $systemSubtitle = $systemSetting?->system_subtitle ?: 'Dao, Capiz';
                $systemLogoPath = $systemSetting?->system_logo_path;

                $systemLogoExists = $systemLogoPath
                    && \Illuminate\Support\Facades\Storage::disk('public')->exists($systemLogoPath);

                $systemLogoUrl = $systemLogoExists && \Illuminate\Support\Facades\Route::has('system-branding.logo')
                    ? route('system-branding.logo') . '?v=' . optional($systemSetting?->updated_at)->timestamp
                    : null;

                $canEditSystemBranding = $layoutAuthUser?->role === 'admin'
                    && \Illuminate\Support\Facades\Route::has('admin.system-branding.edit');
            @endphp

            @if ($canEditSystemBranding)
                <a href="{{ route('admin.system-branding.edit') }}"
                   title="Edit system branding"
                   class="shrink-0 flex items-center gap-3 border-b border-blue-900 px-6 py-6 transition hover:bg-blue-900">
                    <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-blue-600">
                        @if ($systemLogoUrl)
                            <img src="{{ $systemLogoUrl }}"
                                 alt="{{ $systemName }} Logo"
                                 class="h-full w-full object-cover"
                                 onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">

                            <span class="hidden text-lg font-bold">🛡</span>
                        @else
                            <span class="text-lg font-bold">🛡</span>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <h1 class="truncate text-lg font-bold leading-tight">{{ $systemName }}</h1>
                        <p class="truncate text-sm text-blue-200">{{ $systemSubtitle }}</p>
                    </div>
                </a>
            @else
                <div class="shrink-0 flex items-center gap-3 border-b border-blue-900 px-6 py-6">
                    <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-blue-600">
                        @if ($systemLogoUrl)
                            <img src="{{ $systemLogoUrl }}"
                                 alt="{{ $systemName }} Logo"
                                 class="h-full w-full object-cover"
                                 onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">

                            <span class="hidden text-lg font-bold">🛡</span>
                        @else
                            <span class="text-lg font-bold">🛡</span>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <h1 class="truncate text-lg font-bold leading-tight">{{ $systemName }}</h1>
                        <p class="truncate text-sm text-blue-200">{{ $systemSubtitle }}</p>
                    </div>
                </div>
            @endif

            @php
                $authUser = auth()->user();
                $role = strtolower(trim($authUser?->role ?? ''));

                $authPhotoPath = $authUser && \Illuminate\Support\Facades\Schema::hasColumn('users', 'profile_photo_path')
                    ? ($authUser->profile_photo_path ?? null)
                    : null;

                $authPhotoUrl = $authPhotoPath && Route::has('users.profile-photo')
                    ? route('users.profile-photo', $authUser) . '?v=' . optional($authUser?->updated_at)->timestamp
                    : null;

                $authInitial = strtoupper(mb_substr($authUser?->name ?? 'U', 0, 1));

                $authProfileUrl = '#';

                if ($authUser) {
                    $authProfileUrl = match ($authUser->role) {
                        'admin' => Route::has('admin.users.edit')
                            ? route('admin.users.edit', $authUser)
                            : (Route::has('profile.edit') ? route('profile.edit') : '#'),

                        'official', 'dao', 'tanod', 'resident' => Route::has('profile.edit')
                            ? route('profile.edit')
                            : '#',

                        default => Route::has('profile.edit')
                            ? route('profile.edit')
                            : '#',
                    };
                }

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
                            'label' => 'Emergency Hotlines',
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
    [
        'label' => 'Tanod Roster',
        'icon' => '👥',
        'route' => 'official.tanods.index',
        'active' => ['official.tanods.*'],
    ],
    [
        'label' => 'Announcements',
        'icon' => '📢',
        'route' => 'official.announcements.index',
        'active' => ['official.announcements.*'],
    ],
    [
        'label' => 'Emergency Hotlines',
        'icon' => '🚨',
        'route' => 'official.emergency-mode.index',
        'active' => ['official.emergency-mode.*'],
    ],
    [
        'label' => 'Map',
        'icon' => '🗺',
        'route' => 'official.map.index',
        'active' => ['official.map.*'],
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
                                @csrf

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

        <div id="adminMainContent"
             class="min-h-screen flex-1 pl-72 transition-[padding] duration-300 ease-in-out">
            <header class="sticky top-0 z-[100] isolate flex h-16 items-center justify-between border-b border-slate-200 bg-white px-8 shadow-sm">
                <button id="sidebarToggleButton"
                        type="button"
                        aria-label="Toggle sidebar"
                        aria-controls="adminSidebar"
                        aria-expanded="true"
                        title="Open or close sidebar"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-100 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2"
                         class="h-6 w-6"
                         aria-hidden="true">
                        <path stroke-linecap="round"
                              d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div class="flex items-center gap-4">
                    @php
                        $importantNotificationTypes = [
                            'announcement',
                            'incident_reported',
                            'calamity',
                            'community_problem',
                            'community',
                            'dispatch',
                            'escalation',
                            'emergency',
                            'resolved',
                        ];

                        $notificationTypeLabels = [
                            'announcement' => 'Announcement',
                            'incident_reported' => 'New Incident Report',
                            'calamity' => 'Calamity Alert',
                            'community_problem' => 'Community Problem',
                            'community' => 'Community',
                            'dispatch' => 'Dispatch',
                            'escalation' => 'Escalation',
                            'emergency' => 'Emergency',
                            'resolved' => 'Resolved',
                        ];

                        $unreadNotificationCount = 0;
                        $notificationUrl = '#';
                        $latestUnreadNotifications = collect();

                        if ($authUser) {
                            $notificationQuery = \App\Models\UserNotification::query()
                                ->where('user_id', $authUser->id)
                                ->where('is_read', false)
                                ->whereIn('type', $importantNotificationTypes);

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

                                'official', 'dao' => Route::has('official.incidents.index')
                                    ? route('official.incidents.index')
                                    : '#',

                                'resident' => Route::has('resident.incidents.index')
                                    ? route('resident.incidents.index')
                                    : '#',

                                default => '#',
                            };
                        }
                    @endphp

                    <details class="relative z-[110]">
                        <summary class="relative inline-flex cursor-pointer list-none items-center justify-center">
                            <span class="text-xl">🔔</span>

                            @if ($unreadNotificationCount > 0)
                                <span class="absolute -right-2 -top-2 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white">
                                    {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
                                </span>
                            @endif
                        </summary>

                        <div id="notificationBackdrop"
                             class="fixed bottom-0 left-72 right-0 top-16 z-[100] bg-slate-950/20 backdrop-blur-[1px] transition-[left] duration-300 ease-in-out"
                             onclick="this.closest('details').removeAttribute('open')"
                             aria-hidden="true">
                        </div>

                        <div class="fixed right-8 top-[4.5rem] z-[120] flex w-96 max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-slate-900/10">
                            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                                <div>
                                    <p class="text-sm font-bold text-slate-900">Unread Notifications</p>
                                    <p class="text-xs text-slate-500">Important new updates only</p>
                                </div>

                                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">
                                    {{ $unreadNotificationCount }}
                                </span>
                            </div>

                            <div class="max-h-[calc(100vh-13rem)] overflow-y-auto">
                                @forelse ($latestUnreadNotifications as $notification)
                                    @php
                                        $type = strtolower($notification->type ?? 'incident');
                                        $typeLabel = $notificationTypeLabels[$type] ?? ucfirst($type);

                                        $incidentRoute = null;

                                        $incidentLinkedTypes = [
                                            'incident_reported',
                                            'dispatch',
                                            'escalation',
                                        ];

                                        if ($notification->source_id && in_array($type, $incidentLinkedTypes, true)) {
                                            $incidentRouteName = match ($authUser?->role) {
                                                'admin' => 'admin.incidents.show',
                                                'official', 'dao' => 'official.incidents.show',
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('adminSidebar');
            const mainContent = document.getElementById('adminMainContent');
            const toggleButton = document.getElementById('sidebarToggleButton');
            const notificationBackdrop = document.getElementById('notificationBackdrop');
            const storageKey = 'tabangnow.admin.sidebar-collapsed';

            if (!sidebar || !mainContent || !toggleButton) {
                return;
            }

            function applySidebarState(isCollapsed) {
                sidebar.classList.toggle('-translate-x-full', isCollapsed);
                sidebar.classList.toggle('translate-x-0', !isCollapsed);

                mainContent.classList.toggle('pl-0', isCollapsed);
                mainContent.classList.toggle('pl-72', !isCollapsed);

                if (notificationBackdrop) {
                    notificationBackdrop.classList.toggle('left-0', isCollapsed);
                    notificationBackdrop.classList.toggle('left-72', !isCollapsed);
                }

                toggleButton.setAttribute(
                    'aria-expanded',
                    isCollapsed ? 'false' : 'true'
                );

                toggleButton.title = isCollapsed
                    ? 'Open sidebar'
                    : 'Close sidebar';
            }

            let isCollapsed = false;

            try {
                isCollapsed = localStorage.getItem(storageKey) === '1';
            } catch (error) {
                isCollapsed = false;
            }

            applySidebarState(isCollapsed);

            toggleButton.addEventListener('click', function () {
                const nextCollapsedState = !sidebar.classList.contains(
                    '-translate-x-full'
                );

                applySidebarState(nextCollapsedState);

                try {
                    localStorage.setItem(
                        storageKey,
                        nextCollapsedState ? '1' : '0'
                    );
                } catch (error) {
                    console.warn('Unable to save sidebar state.', error);
                }
            });
        });
    </script>
</body>
</html>

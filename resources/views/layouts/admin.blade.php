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
                    <div class="relative">
                        <span class="text-xl">🔔</span>
                        <span class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">
                            6
                        </span>
                    </div>

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
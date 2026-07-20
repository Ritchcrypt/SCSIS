@extends('layouts.admin')

@section('title', 'User Management | TabangNow')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-blue-700">
                User Management
            </p>

            <h1 class="mt-1 text-2xl font-bold text-slate-900">
                Users
            </h1>

            <p class="mt-2 max-w-3xl text-sm text-slate-600">
                Manage admin, official, tanod, and resident accounts.
            </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('admin.users.export', request()->query()) }}"
               class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Export
            </a>

            <a href="{{ route('admin.users.create') }}"
               class="inline-flex items-center justify-center rounded-xl bg-blue-950 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-900">
                + Add User
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('temporary_password'))
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm font-medium text-blue-800">
            Temporary password for <strong>{{ session('temporary_password_user') }}</strong>:
            <span class="font-mono font-bold">{{ session('temporary_password') }}</span>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Users</p>
            <p class="mt-3 text-3xl font-bold text-slate-900">{{ $summary['total'] }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Online</p>
            <p class="mt-3 text-3xl font-bold text-green-600">{{ $summary['online'] }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Offline</p>
            <p class="mt-3 text-3xl font-bold text-slate-500">{{ $summary['offline'] }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Staff</p>
            <p class="mt-3 text-3xl font-bold text-blue-700">{{ $summary['staff'] }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Residents</p>
            <p class="mt-3 text-3xl font-bold text-indigo-600">{{ $summary['residents'] }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-4 xl:grid-cols-12">
            <div class="xl:col-span-4">
                <label for="search" class="sr-only">Search</label>

                <input id="search"
                       type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Search name, email, contact, address..."
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
            </div>

            <div class="xl:col-span-2">
                <select name="role"
                        class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="all">All Roles</option>

                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(request('role') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="xl:col-span-2">
                <select name="status"
                        class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="all">All Presence</option>

                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="xl:col-span-2">
                <select name="date"
                        class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="all">All Dates</option>

                    @foreach ($dateOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('date') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2 xl:col-span-2">
                <button type="submit"
                        class="inline-flex flex-1 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                    Filter
                </button>

                <a href="{{ route('admin.users.index') }}"
                   class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div id="userManagementTableScroll" class="max-h-[620px] overflow-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="sticky top-0 z-20 bg-blue-950 shadow-sm">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-white">
                            Full Name
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-white">
                            Email
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-white">
                            Contact
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-white">
                            Address
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-white">
                            Role
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-white">
                            Status
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-white">
                            Joined Date
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-white">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y-2 divide-slate-200 bg-white">
                    @forelse ($users as $userRecord)
                        @php
                            $isOnline = false;

                            if (
                                Schema::hasColumn('users', 'last_seen_at')
                                && $userRecord->last_seen_at
                            ) {
                                try {
                                    $isOnline = \Carbon\Carbon::parse($userRecord->last_seen_at)
                                        ->greaterThanOrEqualTo(now()->subMinutes(2));
                                } catch (\Throwable $e) {
                                    $isOnline = false;
                                }
                            }
                            $profilePhotoPath = Schema::hasColumn('users', 'profile_photo_path') ? ($userRecord->profile_photo_path ?? null) : null;
                            $profilePhotoUrl = $profilePhotoPath && Route::has('users.profile-photo')
                            ? route('users.profile-photo', $userRecord) . '?v=' . optional($userRecord->updated_at)->timestamp
                            : null;
                            $userInitial = strtoupper(mb_substr($userRecord->name ?? 'U', 0, 1));
                        @endphp

                        <tr class="border-b-2 border-slate-200 transition hover:bg-slate-50 last:border-b-0">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="relative h-10 w-10 shrink-0">
                                        @if ($profilePhotoUrl)
                                            <img src="{{ $profilePhotoUrl }}"
                                                 alt="{{ $userRecord->name }} profile photo"
                                                 class="h-10 w-10 rounded-full border border-slate-200 object-cover shadow-sm"
                                                 onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">

                                            <div class="hidden h-10 w-10 items-center justify-center rounded-full bg-blue-950 text-sm font-bold text-white shadow-sm">
                                                {{ $userInitial }}
                                            </div>
                                        @else
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-950 text-sm font-bold text-white shadow-sm">
                                                {{ $userInitial }}
                                            </div>
                                        @endif
                                    </div>

                                    <div>
                                        <p class="font-semibold text-slate-900">
                                            {{ $userRecord->name }}
                                        </p>

                                        <p class="mt-0.5 text-xs text-slate-500">
                                            {{ ucfirst((string) $userRecord->role) }} account
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ filled($userRecord->email)
                                    ? $userRecord->email
                                    : '—' }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ filled($userRecord->contact_number)
                                    ? $userRecord->contact_number
                                    : '—' }}
                            </td>

                            <td class="max-w-[220px] px-5 py-4 text-sm text-slate-700">
                                <span class="block truncate"
                                      title="{{ filled($userRecord->address) ? $userRecord->address : '—' }}">
                                    {{ filled($userRecord->address)
                                        ? $userRecord->address
                                        : '—' }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                                    {{ ucfirst((string) $userRecord->role) }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                @if ($isOnline)
                                    <span class="inline-flex items-center gap-2 rounded-full border border-green-200 bg-green-50 px-3 py-1 text-xs font-bold text-green-700">
                                        <span class="h-2 w-2 rounded-full bg-green-500"></span>
                                        Online
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                                        <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                                        Offline
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $userRecord->created_at ? $userRecord->created_at->format('M d, Y') : '—' }}
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.users.show', $userRecord) }}"
                                       data-user-management-return
                                       title="View user"
                                       aria-label="View user"
                                       class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50 hover:text-slate-900">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                             viewBox="0 0 24 24"
                                             fill="none"
                                             stroke="currentColor"
                                             stroke-width="1.8"
                                             class="h-5 w-5"
                                             aria-hidden="true">
                                            <path stroke-linecap="round"
                                                  stroke-linejoin="round"
                                                  d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                            <circle cx="12" cy="12" r="2.75" />
                                        </svg>
                                    </a>

                                    <a href="{{ route('admin.users.edit', $userRecord) }}"
                                       data-user-management-return
                                       title="Edit user"
                                       aria-label="Edit user"
                                       class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-700 transition hover:bg-blue-100 hover:text-blue-800">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                             viewBox="0 0 24 24"
                                             fill="none"
                                             stroke="currentColor"
                                             stroke-width="1.8"
                                             class="h-5 w-5"
                                             aria-hidden="true">
                                            <path stroke-linecap="round"
                                                  stroke-linejoin="round"
                                                  d="M16.862 3.487a2.25 2.25 0 0 1 3.182 3.182L8.25 18.463 3.75 19.5l1.037-4.5L16.862 3.487Z" />
                                            <path stroke-linecap="round"
                                                  stroke-linejoin="round"
                                                  d="m15.75 4.5 3.75 3.75" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-14 text-center">
                                <h3 class="text-base font-bold text-slate-900">
                                    No users found
                                </h3>

                                <p class="mt-2 text-sm text-slate-500">
                                    Add users or adjust the current filters.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 bg-white px-6 py-4">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-700">
            <span class="font-semibold">
                Rows per page
            </span>

            <form method="GET" action="{{ route('admin.users.index') }}">
                @foreach (request()->except(['per_page', 'page']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach

                <select name="per_page"
                        onchange="this.form.submit()"
                        class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}" @selected((int) $perPage === (int) $option)>
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
            </form>

            <span>
                of <strong>{{ $users->total() }}</strong> rows
            </span>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            @if ($users->onFirstPage())
                <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-300">
                    «
                </span>

                <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-300">
                    ‹
                </span>
            @else
                <a href="{{ $users->url(1) }}"
                   class="inline-flex h-9 min-w-9 items-center justify-center rounded-full border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    «
                </a>

                <a href="{{ $users->previousPageUrl() }}"
                   class="inline-flex h-9 min-w-9 items-center justify-center rounded-full border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    ‹
                </a>
            @endif

            @php
                $currentPage = $users->currentPage();
                $lastPage = $users->lastPage();

                $startPage = max(1, $currentPage - 2);
                $endPage = min($lastPage, $currentPage + 2);
            @endphp

            @for ($page = $startPage; $page <= $endPage; $page++)
                @if ($page === $currentPage)
                    <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-full bg-blue-950 px-3 text-sm font-bold text-white">
                        {{ $page }}
                    </span>
                @else
                    <a href="{{ $users->url($page) }}"
                       class="inline-flex h-9 min-w-9 items-center justify-center rounded-full border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
                        {{ $page }}
                    </a>
                @endif
            @endfor

            @if ($users->hasMorePages())
                <a href="{{ $users->nextPageUrl() }}"
                   class="inline-flex h-9 min-w-9 items-center justify-center rounded-full border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    ›
                </a>

                <a href="{{ $users->url($users->lastPage()) }}"
                   class="inline-flex h-9 min-w-9 items-center justify-center rounded-full border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    »
                </a>
            @else
                <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-300">
                    ›
                </span>

                <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-300">
                    »
                </span>
            @endif
        </div>
    </div>
</div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const storagePrefix = 'tabangnow.user-management.';
    const pendingKey = storagePrefix + 'return-pending';
    const urlKey = storagePrefix + 'return-url';
    const windowScrollKey = storagePrefix + 'window-scroll-y';
    const tableScrollKey = storagePrefix + 'table-scroll-y';

    const tableScroll = document.getElementById('userManagementTableScroll');
    const navigationLinks = document.querySelectorAll('[data-user-management-return]');

    function rememberUserManagementPosition() {
        try {
            sessionStorage.setItem(pendingKey, '1');
            sessionStorage.setItem(urlKey, window.location.href);
            sessionStorage.setItem(windowScrollKey, String(window.scrollY || 0));
            sessionStorage.setItem(
                tableScrollKey,
                String(tableScroll ? tableScroll.scrollTop : 0)
            );
        } catch (error) {
            console.warn('Unable to save User Management position.', error);
        }
    }

    navigationLinks.forEach(function (link) {
        link.addEventListener('click', rememberUserManagementPosition);
    });

    try {
        if (sessionStorage.getItem(pendingKey) !== '1') {
            return;
        }

        const savedUrl = sessionStorage.getItem(urlKey);

        if (savedUrl && new URL(savedUrl).href !== window.location.href) {
            window.location.replace(savedUrl);
            return;
        }

        const savedWindowScroll = Number(
            sessionStorage.getItem(windowScrollKey) || 0
        );
        const savedTableScroll = Number(
            sessionStorage.getItem(tableScrollKey) || 0
        );

        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                window.scrollTo({
                    top: savedWindowScroll,
                    left: 0,
                    behavior: 'auto'
                });

                if (tableScroll) {
                    tableScroll.scrollTop = savedTableScroll;
                }

                sessionStorage.removeItem(pendingKey);
                sessionStorage.removeItem(urlKey);
                sessionStorage.removeItem(windowScrollKey);
                sessionStorage.removeItem(tableScrollKey);
            });
        });
    } catch (error) {
        console.warn('Unable to restore User Management position.', error);
    }
});
</script>

@endsection

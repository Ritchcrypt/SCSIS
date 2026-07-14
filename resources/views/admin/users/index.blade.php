@extends('layouts.admin')

@section('title', 'User Management | DaoSystem')

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

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @if (session('temporary_password'))
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm font-medium text-blue-800">
            Temporary password for <strong>{{ session('temporary_password_user') }}</strong>:
            <span class="font-mono font-bold">{{ session('temporary_password') }}</span>
        </div>
    @endif

    <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
        <p class="font-bold">Permanent Delete Safety Rule</p>
        <p class="mt-1 leading-6">
            Permanent delete is only for accounts with no connected system records. If the user is connected to incidents, task responses, notifications, employee/tanod records, or other audit records, deletion will be blocked. Use <strong>Deactivate</strong> instead for real accounts.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Users</p>
            <p class="mt-3 text-3xl font-bold text-slate-900">{{ $summary['total'] }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Active</p>
            <p class="mt-3 text-3xl font-bold text-green-600">{{ $summary['active'] }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Inactive</p>
            <p class="mt-3 text-3xl font-bold text-slate-500">{{ $summary['inactive'] }}</p>
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
                    <option value="all">All Status</option>

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
    <div class="max-h-[620px] overflow-auto">
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
                            Barangay
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

                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($users as $userRecord)
                        @php
                            $barangay = $barangays->firstWhere('id', $userRecord->barangay_id ?? null);
                            $isActive = ! Schema::hasColumn('users', 'is_active') || (bool) $userRecord->is_active;
                            $isCurrentUser = auth()->id() && (int) auth()->id() === (int) $userRecord->id;
                            $profilePhotoPath = Schema::hasColumn('users', 'profile_photo_path') ? ($userRecord->profile_photo_path ?? null) : null;
                            $profilePhotoUrl = $profilePhotoPath && Route::has('users.profile-photo')
                            ? route('users.profile-photo', $userRecord) . '?v=' . optional($userRecord->updated_at)->timestamp
                            : null;
                            $userInitial = strtoupper(mb_substr($userRecord->name ?? 'U', 0, 1));
                        @endphp

                        <tr class="hover:bg-slate-50">
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
                                {{ $userRecord->email }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $userRecord->contact_number ?? '—' }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $barangay->barangay_name ?? $barangay->name ?? '—' }}
                            </td>

                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                                    {{ ucfirst((string) $userRecord->role) }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                @if ($isActive)
                                    <span class="inline-flex rounded-full border border-green-200 bg-green-50 px-3 py-1 text-xs font-bold text-green-700">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                                        Inactive
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $userRecord->created_at ? $userRecord->created_at->format('M d, Y') : '—' }}
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.users.show', $userRecord) }}"
                                       class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                                        View
                                    </a>

                                    <a href="{{ route('admin.users.edit', $userRecord) }}"
                                       class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100">
                                        Edit
                                    </a>

                                    <form method="POST" action="{{ route('admin.users.reset-password', $userRecord) }}">
                                        @csrf
                                        @method('PATCH')

                                        <button type="submit"
                                                onclick="return confirm('Reset password for this user?')"
                                                class="rounded-lg border border-yellow-200 bg-yellow-50 px-3 py-2 text-xs font-bold text-yellow-700 hover:bg-yellow-100">
                                            Reset
                                        </button>
                                    </form>

                                    @if ($isActive)
                                        <form method="POST" action="{{ route('admin.users.deactivate', $userRecord) }}">
                                            @csrf
                                            @method('PATCH')

                                            <button type="submit"
                                                    onclick="return confirm('Deactivate this user?')"
                                                    class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200">
                                                Deactivate
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.activate', $userRecord) }}">
                                            @csrf
                                            @method('PATCH')

                                            <button type="submit"
                                                    class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs font-bold text-green-700 hover:bg-green-100">
                                                Activate
                                            </button>
                                        </form>
                                    @endif

                                    @if ($isCurrentUser)
                                        <button type="button"
                                                disabled
                                                title="You cannot permanently delete your own account."
                                                class="cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-bold text-slate-400">
                                            Delete Locked
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.destroy', $userRecord) }}">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                    onclick="return confirm('Permanent delete will run safety checks first. If this user has incidents, tasks, notifications, employee/tanod records, messages, or logs, deletion will be blocked and you should deactivate instead. Continue?')"
                                                    class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100">
                                                Permanent Delete
                                            </button>
                                        </form>
                                    @endif
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
@endsection
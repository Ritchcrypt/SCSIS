@extends('layouts.admin')

@section('title', 'Activity Logs | TabangNow')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">
                Security audit trail
            </p>

            <h2 class="mt-1 text-2xl font-bold text-slate-900">
                Activity Logs
            </h2>

            <p class="mt-2 max-w-3xl text-sm text-slate-600">
                Review authentication, account-management, incident, complaint,
                tanod, announcement, configuration, and security events.
                Individual activity records are read-only. Administrators may permanently clear the complete audit trail.
            </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 shadow-sm">
                <span class="font-semibold text-slate-900">
                    {{ number_format($activityLogs->total()) }}
                </span>
                matching record{{ $activityLogs->total() === 1 ? '' : 's' }}
            </div>

            <form method="POST"
                  action="{{ route('admin.activity-logs.destroy-all-action') }}"
                  onsubmit="return confirm('Delete ALL activity logs permanently? This removes the complete audit trail, not only the currently filtered records. This cannot be undone.');">
                @csrf

                <button type="submit"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-red-300 bg-red-50 px-4 py-2.5 text-sm font-bold text-red-700 shadow-sm transition hover:border-red-400 hover:bg-red-100">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2"
                         class="h-4 w-4"
                         aria-hidden="true">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M14.74 9 14.394 18m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M19.228 5.79 18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-10.978.397c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0V4.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.364 0c-1.18.037-2.09 1.022-2.09 2.201v.312m7.544 0a48.667 48.667 0 0 0-7.544 0" />
                    </svg>

                    Delete All Permanently
                </button>
            </form>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
            {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">The filters could not be applied.</p>

            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET"
              action="{{ route('admin.activity-logs.index') }}"
              class="grid gap-4 xl:grid-cols-12">

            <div class="xl:col-span-4">
                <label for="search"
                       class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Search
                </label>

                <input id="search"
                       name="search"
                       type="search"
                       value="{{ $filters['search'] }}"
                       maxlength="200"
                       placeholder="Actor, event, description, route, or IP"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </div>

            <div class="xl:col-span-2">
                <label for="category"
                       class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Category
                </label>

                <select id="category"
                        name="category"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <option value="">All categories</option>

                    @foreach ($categories as $category)
                        <option value="{{ $category }}"
                                @selected($filters['category'] === $category)>
                            {{ \Illuminate\Support\Str::headline($category) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="xl:col-span-2">
                <label for="event"
                       class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Event
                </label>

                <select id="event"
                        name="event"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <option value="">All events</option>

                    @foreach ($events as $event)
                        <option value="{{ $event }}"
                                @selected($filters['event'] === $event)>
                            {{ \Illuminate\Support\Str::headline(str_replace('.', ' ', $event)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="xl:col-span-2">
                <label for="actor_id"
                       class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Actor
                </label>

                <select id="actor_id"
                        name="actor_id"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <option value="">All actors</option>

                    @foreach ($actors as $actor)
                        <option value="{{ $actor->actor_id }}"
                                @selected((string) $filters['actor_id'] === (string) $actor->actor_id)>
                            {{ $actor->actor_name }}
                            @if ($actor->actor_role)
                                ({{ ucfirst($actor->actor_role) }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="xl:col-span-2">
                <label for="per_page"
                       class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Rows
                </label>

                <select id="per_page"
                        name="per_page"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    @foreach ([10, 25, 50, 100, 250] as $size)
                        <option value="{{ $size }}"
                                @selected((int) $filters['per_page'] === $size)>
                            {{ $size }} per page
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="xl:col-span-3">
                <label for="date_from"
                       class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Date from
                </label>

                <input id="date_from"
                       name="date_from"
                       type="date"
                       value="{{ $filters['date_from'] }}"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </div>

            <div class="xl:col-span-3">
                <label for="date_to"
                       class="mb-1.5 block text-sm font-semibold text-slate-700">
                    Date to
                </label>

                <input id="date_to"
                       name="date_to"
                       type="date"
                       value="{{ $filters['date_to'] }}"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </div>

            <div class="flex items-end gap-3 xl:col-span-6">
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                    Apply Filters
                </button>

                <a href="{{ route('admin.activity-logs.index') }}"
                   class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Clear Filters
                </a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                            Date and time
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                            Actor
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                            Event
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                            Description
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                            IP address
                        </th>

                        <th class="px-5 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-600">
                            Action
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($activityLogs as $activityLog)
                        <tr class="align-top transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                <p class="font-semibold text-slate-900">
                                    {{ $activityLog->created_at?->format('M d, Y') ?? '—' }}
                                </p>

                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $activityLog->created_at?->format('h:i:s A') ?? '—' }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-sm">
                                <p class="font-semibold text-slate-900">
                                    {{ $activityLog->actor_name ?: 'System / Unknown' }}
                                </p>

                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $activityLog->actor_role
                                        ? ucfirst($activityLog->actor_role)
                                        : 'No actor role' }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-sm">
                                <p class="font-mono text-xs font-semibold text-blue-700">
                                    {{ $activityLog->event }}
                                </p>

                                <span class="mt-2 inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    {{ \Illuminate\Support\Str::headline($activityLog->category) }}
                                </span>
                            </td>

                            <td class="max-w-md px-5 py-4 text-sm text-slate-600">
                                {{ $activityLog->description }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-600">
                                {{ $activityLog->ip_address ?: '—' }}
                            </td>

                            <td class="px-5 py-4 text-center">
                                <a href="{{ route('admin.activity-logs.show', $activityLog) }}"
                                   title="View activity log"
                                   aria-label="View activity log"
                                   class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="currentColor"
                                         stroke-width="2"
                                         class="h-4 w-4"
                                         aria-hidden="true">
                                        <path stroke-linecap="round"
                                              stroke-linejoin="round"
                                              d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.575 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.575-3.007-9.964-7.178Z" />
                                        <path stroke-linecap="round"
                                              stroke-linejoin="round"
                                              d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6"
                                class="px-6 py-14 text-center">
                                <p class="text-base font-semibold text-slate-800">
                                    No activity logs matched the selected filters.
                                </p>

                                <p class="mt-1 text-sm text-slate-500">
                                    Clear the filters or choose a wider date range.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($activityLogs->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $activityLogs->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
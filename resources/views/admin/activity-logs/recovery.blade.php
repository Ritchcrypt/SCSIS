@extends('layouts.admin')

@section('title', 'Deleted Data Recovery | TabangNow')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">
                Activity Logs · Recovery
            </p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">
                Deleted Data Recovery
            </h2>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">
                Restore supported application records deleted through TabangNow. Recovery snapshots are private and available only to active administrators.
            </p>
        </div>

        <a href="{{ route('admin.activity-logs.index') }}"
           class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">
            Back to Activity Logs
        </a>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">Recovery could not be completed.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-amber-700">Recoverable</p>
            <p class="mt-1 text-3xl font-bold text-amber-900">{{ number_format($summary['recoverable']) }}</p>
        </div>
        <div class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-green-700">Restored</p>
            <p class="mt-1 text-3xl font-bold text-green-900">{{ number_format($summary['restored']) }}</p>
        </div>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET"
              action="{{ route('admin.activity-logs.deleted-data.index') }}"
              class="grid gap-4 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <label for="search" class="mb-1.5 block text-sm font-semibold text-slate-700">Search</label>
                <input id="search"
                       name="search"
                       type="search"
                       value="{{ $filters['search'] }}"
                       maxlength="200"
                       placeholder="Record label, deleted by, or original ID"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </div>

            <div class="lg:col-span-3">
                <label for="type" class="mb-1.5 block text-sm font-semibold text-slate-700">Data type</label>
                <select id="type"
                        name="type"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <option value="">All supported types</option>
                    @foreach ($typeLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-2">
                <label for="status" class="mb-1.5 block text-sm font-semibold text-slate-700">Status</label>
                <select id="status"
                        name="status"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <option value="recoverable" @selected($filters['status'] === 'recoverable')>Recoverable</option>
                    <option value="restored" @selected($filters['status'] === 'restored')>Restored</option>
                    <option value="all" @selected($filters['status'] === 'all')>All</option>
                </select>
            </div>

            <div class="flex items-end gap-2 lg:col-span-2">
                <button type="submit"
                        class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700">
                    Apply
                </button>
                <a href="{{ route('admin.activity-logs.deleted-data.index') }}"
                   class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50">
                    Reset
                </a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                    <tr>
                        <th class="px-5 py-3">Data</th>
                        <th class="px-5 py-3">Original ID</th>
                        <th class="px-5 py-3">Deleted by</th>
                        <th class="px-5 py-3">Deleted</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse ($records as $record)
                        <tr class="align-top">
                            <td class="px-5 py-4">
                                <div class="font-bold text-slate-900">
                                    {{ $typeLabels[$record->resource_type] ?? \Illuminate\Support\Str::headline($record->resource_type) }}
                                </div>
                                <div class="mt-1 max-w-md text-slate-600">
                                    {{ $record->resource_label ?: 'Deleted record' }}
                                </div>
                            </td>
                            <td class="px-5 py-4 font-mono text-xs">#{{ $record->resource_id }}</td>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-900">{{ $record->deleted_by_name ?: 'Unknown user' }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $record->deleted_by_role ? \Illuminate\Support\Str::headline($record->deleted_by_role) : 'Unknown role' }}</div>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                {{ $record->deleted_at?->format('M d, Y g:i A') }}
                            </td>
                            <td class="px-5 py-4">
                                @if ($record->restored_at)
                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-bold text-green-700">Restored</span>
                                    <div class="mt-1 text-xs text-slate-500">{{ $record->restored_at->format('M d, Y g:i A') }}</div>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-700">Recoverable</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if (! $record->restored_at)
                                    <form method="POST"
                                          action="{{ route('admin.activity-logs.deleted-data.restore', $record) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                data-confirm="Restore this deleted {{ strtolower($typeLabels[$record->resource_type] ?? 'record') }} and its archived dependent data?"
                                                data-confirm-title="Restore deleted data"
                                                data-confirm-button="Restore"
                                                class="inline-flex min-h-10 items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-blue-700">
                                            Restore
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs font-semibold text-slate-400">Completed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-14 text-center text-slate-500">
                                No deleted-data recovery records match the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($records->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $records->links() }}
            </div>
        @endif
    </section>
</div>
@endsection

@extends('layouts.admin')

@section('content')
@php
    $routePrefix = request()->routeIs('tanod.*') ? 'tanod.' : 'admin.';

    $typeStyles = [
        'dispatch' => [
            'label' => 'Dispatch',
            'icon' => '🚓',
            'badge' => 'bg-blue-100 text-blue-700',
            'border' => 'border-blue-200',
        ],
        'escalation' => [
            'label' => 'Escalation',
            'icon' => '⚠️',
            'badge' => 'bg-orange-100 text-orange-700',
            'border' => 'border-orange-200',
        ],
        'emergency' => [
            'label' => 'Emergency',
            'icon' => '🚨',
            'badge' => 'bg-red-100 text-red-700',
            'border' => 'border-red-200',
        ],
        'calamity' => [
            'label' => 'Calamity',
            'icon' => '🌧️',
            'badge' => 'bg-purple-100 text-purple-700',
            'border' => 'border-purple-200',
        ],
        'resolved' => [
            'label' => 'Resolved',
            'icon' => '✅',
            'badge' => 'bg-emerald-100 text-emerald-700',
            'border' => 'border-emerald-200',
        ],
        'announcement' => [
            'label' => 'Announcement',
            'icon' => '📢',
            'badge' => 'bg-slate-100 text-slate-700',
            'border' => 'border-slate-200',
        ],
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Tanod Alert Notifications</h1>
            <p class="mt-1 text-sm text-slate-500">
                Monitor dispatch alerts, escalations, emergency notices, calamity updates, and acknowledgements.
            </p>
        </div>

        <form method="GET" action="{{ route($routePrefix . 'tanod-alerts.index') }}" class="flex items-center gap-3">
            <label for="type" class="text-sm font-semibold text-slate-600">Alert Type</label>

            <select id="type"
                    name="type"
                    onchange="this.form.submit()"
                    class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                @foreach ($alertTypes as $value => $label)
                    <option value="{{ $value }}" @selected($selectedType === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Total Alerts</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $totalAlerts }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Unread Alerts</p>
            <p class="mt-2 text-3xl font-bold text-red-600">{{ $unreadAlerts }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">Acknowledged</p>
            <p class="mt-2 text-3xl font-bold text-emerald-600">{{ $acknowledgedAlerts }}</p>
        </div>
    </div>

    <div class="space-y-4">
        @forelse ($alerts as $alert)
            @php
                $type = strtolower($alert->type ?? 'announcement');
                $style = $typeStyles[$type] ?? $typeStyles['announcement'];

                $isAcknowledged = (bool) $alert->acknowledged_at || (bool) $alert->is_read;

                $acknowledgedName = optional($alert->acknowledgedBy)->name;

                if (! $acknowledgedName && $isAcknowledged) {
                    $acknowledgedName = optional($alert->user)->name;
                }

                $createdAt = $alert->created_at
                    ? $alert->created_at->format('M d, Y h:i A')
                    : 'No date';

                $readAt = $alert->read_at
                    ? $alert->read_at->format('M d, Y h:i A')
                    : null;

                $acknowledgedAt = $alert->acknowledged_at
                    ? $alert->acknowledged_at->format('M d, Y h:i A')
                    : $readAt;
            @endphp

            <article class="rounded-2xl border {{ $style['border'] }} bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-xl">
                            {{ $style['icon'] }}
                        </div>

                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $style['badge'] }}">
                                    {{ $style['label'] }}
                                </span>

                                @if (! $alert->is_read)
                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">
                                        New
                                    </span>
                                @else
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">
                                        Read
                                    </span>
                                @endif
                            </div>

                            <h2 class="mt-3 text-lg font-bold text-slate-900">
                                {{ $alert->title ?? 'Untitled Alert' }}
                            </h2>

                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                {{ $alert->message ?? 'No alert message provided.' }}
                            </p>

                            <div class="mt-4 flex flex-wrap gap-4 text-xs text-slate-500">
                                <span>
                                    Created:
                                    <strong class="font-semibold text-slate-700">{{ $createdAt }}</strong>
                                </span>

                                @if ($alert->user)
                                    <span>
                                        Recipient:
                                        <strong class="font-semibold text-slate-700">{{ $alert->user->name }}</strong>
                                    </span>
                                @endif

                                @if ($alert->source_id && Route::has($routePrefix . 'incidents.show'))
                                    <a href="{{ route($routePrefix . 'incidents.show', $alert->source_id) }}"
                                       class="font-semibold text-blue-700 hover:text-blue-900">
                                        Open Related Incident
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col items-start gap-2 lg:items-end">
                        @if ($isAcknowledged)
                            <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">
                                ✓ {{ $acknowledgedName ?? 'Acknowledged' }}
                            </div>

                            @if ($acknowledgedAt)
                                <p class="text-xs text-slate-500">
                                    {{ $acknowledgedAt }}
                                </p>
                            @endif
                        @else
                            <form method="POST" action="{{ route($routePrefix . 'tanod-alerts.acknowledge', $alert) }}">
                                @csrf
                                @method('PATCH')

                                <button type="submit"
                                        class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
                                    Acknowledge
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl">
                    🔔
                </div>

                <h2 class="mt-4 text-lg font-bold text-slate-900">No alerts found</h2>

                <p class="mt-2 text-sm text-slate-500">
                    There are no tanod alerts for the selected filter yet.
                </p>
            </div>
        @endforelse
    </div>

    @if ($alerts->hasPages())
        <div class="pt-2">
            {{ $alerts->links() }}
        </div>
    @endif
</div>
@endsection
@extends('layouts.admin')

@section('content')
@php
    $routePrefix = match (true) {
        request()->routeIs('tanod.*') => 'tanod.',
        request()->routeIs('official.*') => 'official.',
        default => 'admin.',
    };

    $formatAppDateTime = function ($dateTime) {
        if (! $dateTime) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($dateTime)->format('M d, Y h:i A');
        } catch (\Throwable $e) {
            return null;
        }
    };

    /*
    |--------------------------------------------------------------------------
    | Tanod Alert Display Types
    |--------------------------------------------------------------------------
    | Real announcements and calamity notices are excluded by the controller.
    | This list only controls how operational alert types are labeled here.
    */

    $typeStyles = [
        'assigned_incident' => [
            'label' => 'Assigned Incident',
            'icon' => '📄',
            'badge' => 'bg-blue-100 text-blue-700',
            'border' => 'border-blue-200',
        ],
        'incident_assigned' => [
            'label' => 'Assigned Incident',
            'icon' => '📄',
            'badge' => 'bg-blue-100 text-blue-700',
            'border' => 'border-blue-200',
        ],
        'new_assigned_incident' => [
            'label' => 'Assigned Incident',
            'icon' => '📄',
            'badge' => 'bg-blue-100 text-blue-700',
            'border' => 'border-blue-200',
        ],
        'incident' => [
            'label' => 'Incident',
            'icon' => '📄',
            'badge' => 'bg-blue-100 text-blue-700',
            'border' => 'border-blue-200',
        ],
        'incident_reported' => [
            'label' => 'New Incident Report',
            'icon' => '📄',
            'badge' => 'bg-blue-100 text-blue-700',
            'border' => 'border-blue-200',
        ],
        'incident_update' => [
            'label' => 'Incident Update',
            'icon' => '🔄',
            'badge' => 'bg-indigo-100 text-indigo-700',
            'border' => 'border-indigo-200',
        ],
        'incident_updated' => [
            'label' => 'Incident Update',
            'icon' => '🔄',
            'badge' => 'bg-indigo-100 text-indigo-700',
            'border' => 'border-indigo-200',
        ],
        'incident_status_update' => [
            'label' => 'Incident Status Update',
            'icon' => '🔄',
            'badge' => 'bg-indigo-100 text-indigo-700',
            'border' => 'border-indigo-200',
        ],
        'status_update' => [
            'label' => 'Status Update',
            'icon' => '🔄',
            'badge' => 'bg-indigo-100 text-indigo-700',
            'border' => 'border-indigo-200',
        ],
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
        'resolved' => [
            'label' => 'Resolved',
            'icon' => '✅',
            'badge' => 'bg-emerald-100 text-emerald-700',
            'border' => 'border-emerald-200',
        ],
        'tanod_task' => [
            'label' => 'Tanod Task',
            'icon' => '📋',
            'badge' => 'bg-cyan-100 text-cyan-700',
            'border' => 'border-cyan-200',
        ],
        'tanod_task_assigned' => [
            'label' => 'Tanod Task',
            'icon' => '📋',
            'badge' => 'bg-cyan-100 text-cyan-700',
            'border' => 'border-cyan-200',
        ],
        'task_assigned' => [
            'label' => 'Tanod Task',
            'icon' => '📋',
            'badge' => 'bg-cyan-100 text-cyan-700',
            'border' => 'border-cyan-200',
        ],
        'tanod_task_update' => [
            'label' => 'Tanod Task Update',
            'icon' => '📋',
            'badge' => 'bg-cyan-100 text-cyan-700',
            'border' => 'border-cyan-200',
        ],
        'task_update' => [
            'label' => 'Tanod Task Update',
            'icon' => '📋',
            'badge' => 'bg-cyan-100 text-cyan-700',
            'border' => 'border-cyan-200',
        ],
        'tanod_alert' => [
            'label' => 'Tanod Alert',
            'icon' => '🔔',
            'badge' => 'bg-slate-100 text-slate-700',
            'border' => 'border-slate-200',
        ],
        'community_problem' => [
            'label' => 'Community',
            'icon' => '🏘️',
            'badge' => 'bg-amber-100 text-amber-700',
            'border' => 'border-amber-200',
        ],
        'community' => [
            'label' => 'Community',
            'icon' => '🏘️',
            'badge' => 'bg-amber-100 text-amber-700',
            'border' => 'border-amber-200',
        ],
    ];

    $fallbackStyle = [
        'label' => 'Tanod Alert',
        'icon' => '🔔',
        'badge' => 'bg-slate-100 text-slate-700',
        'border' => 'border-slate-200',
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                Tanod Alert Notifications
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Monitor dispatch alerts, escalations, emergency notices, calamity updates, and acknowledgements.
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-start gap-3 lg:justify-end">
            <form method="POST"
                  action="{{ route($routePrefix . 'tanod-alerts.read-all') }}"
                  class="m-0">
                @csrf
                @method('PATCH')

                <button type="submit"
                        title="Mark all as read"
                        aria-label="Mark all as read"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-5 w-5"
                         fill="none"
                         viewBox="0 0 24 24"
                         stroke="currentColor"
                         stroke-width="2">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M4.5 12.75 9 17.25 19.5 6.75" />
                    </svg>
                </button>
            </form>

            <form method="POST"
      action="{{ route($routePrefix . 'tanod-alerts.destroy-all') }}"
      class="m-0">
                @csrf
                @method('DELETE')

                <button type="submit"
                        title="Delete all alerts"
                        aria-label="Delete all alerts"
                        class="tn-action-icon tn-action-icon-delete inline-flex h-10 w-10 items-center justify-center rounded-xl border text-sm font-bold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-red-200">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-5 w-5"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2"
                         stroke-linecap="round"
                         stroke-linejoin="round"
                         aria-hidden="true">
                        <path d="M3 6h18" />
                        <path d="M8 6V4h8v2" />
                        <path d="M19 6l-1 14H6L5 6" />
                        <path d="M10 11v6" />
                        <path d="M14 11v6" />
                    </svg>
                </button>
            </form>

            <form method="GET"
                  action="{{ route($routePrefix . 'tanod-alerts.index') }}"
                  class="m-0">
                <select id="type"
                        name="type"
                        onchange="this.form.submit()"
                        aria-label="Filter alerts by type"
                        class="h-10 min-w-60 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    @foreach ($alertTypes as $value => $label)
                        <option value="{{ $value }}" @selected($selectedType === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
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
                $type = strtolower((string) ($alert->type ?? 'tanod_alert'));
$style = $typeStyles[$type] ?? $fallbackStyle;

                $isAcknowledged = (bool) $alert->acknowledged_at || (bool) $alert->is_read;

                $acknowledgedName = optional($alert->acknowledgedBy)->name;

                if (! $acknowledgedName && $isAcknowledged) {
                    $acknowledgedName = optional($alert->user)->name;
                }

                $createdAt = $formatAppDateTime($alert->created_at) ?? 'No date';
                $readAt = $formatAppDateTime($alert->read_at);
                $acknowledgedAt = $formatAppDateTime($alert->acknowledged_at) ?? $readAt;
            @endphp

            <article class="rounded-2xl border {{ $style['border'] }} bg-white p-5 shadow-sm">
                <div class="grid gap-5 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div class="flex min-w-0 gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-xl">
                            {{ $style['icon'] }}
                        </div>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $style['badge'] }}">
                                    {{ $style['label'] }}
                                </span>

                                @if ($alert->is_read)
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">
                                        Read
                                    </span>
                                @endif
                            </div>

                            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                {{ $type === 'incident_reported' ? 'A new report was submitted.' : ($alert->message ?? 'No alert message provided.') }}
                            </p>

                            <p class="mt-4 text-xs text-slate-500">
                                Created:
                                <strong class="font-semibold text-slate-700">
                                    {{ $createdAt }}
                                </strong>
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-3">
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
                            <form method="POST"
                                  action="{{ route($routePrefix . 'tanod-alerts.acknowledge', $alert) }}"
                                  class="m-0">
                                @csrf
                                @method('PATCH')

                                <button type="submit"
                                        class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    Acknowledge
                                </button>
                            </form>
                        @endif

                        <form method="POST"
      action="{{ route($routePrefix . 'tanod-alerts.destroy', $alert) }}"
      class="m-0">
                            @csrf
                            @method('DELETE')

                            <button type="submit"
                                    title="Delete alert"
                                    aria-label="Delete alert"
                                    class="tn-action-icon tn-action-icon-delete inline-flex h-10 w-10 items-center justify-center rounded-xl border text-sm font-bold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-red-200">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                     class="h-5 w-5"
                                     viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="2"
                                     stroke-linecap="round"
                                     stroke-linejoin="round"
                                     aria-hidden="true">
                                    <path d="M3 6h18" />
                                    <path d="M8 6V4h8v2" />
                                    <path d="M19 6l-1 14H6L5 6" />
                                    <path d="M10 11v6" />
                                    <path d="M14 11v6" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl">
                    🔔
                </div>

                <h2 class="mt-4 text-lg font-bold text-slate-900">
                    No alerts found
                </h2>

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
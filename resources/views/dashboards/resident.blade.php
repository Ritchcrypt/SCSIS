@extends('layouts.admin')

@section('title', 'Resident Dashboard | DaoSystem')

@section('content')
@php
    $summary = $summary ?? [];

    $myReportsCount = $summary['my_reports'] ?? 0;
    $pendingReportsCount = $summary['pending_reports'] ?? 0;
    $activeReportsCount = $summary['active_reports'] ?? 0;
    $resolvedReportsCount = $summary['resolved_reports'] ?? 0;
    $announcementsCount = $summary['announcements'] ?? 0;

    $myLatestReports = $myLatestReports ?? collect();
    $latestAnnouncements = $latestAnnouncements ?? collect();

    $incidentIndexUrl = Route::has('resident.incidents.index')
        ? route('resident.incidents.index')
        : '#';

    $incidentCreateUrl = Route::has('resident.incidents.create')
        ? route('resident.incidents.create')
        : '#';

    $statusBadgeClass = function ($status) {
        $status = strtolower((string) $status);

        return match ($status) {
            'pending', 'reported' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'active', 'responding', 'in progress', 'in_progress', 'dispatched' => 'bg-blue-100 text-blue-700 border-blue-200',
            'escalated' => 'bg-red-100 text-red-700 border-red-200',
            'resolved', 'closed', 'completed' => 'bg-green-100 text-green-700 border-green-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    };

    $priorityBadgeClass = function ($priority) {
        $priority = strtolower((string) $priority);

        return match ($priority) {
            'critical' => 'bg-red-100 text-red-700 border-red-200',
            'high' => 'bg-orange-100 text-orange-700 border-orange-200',
            'moderate', 'medium' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'low' => 'bg-green-100 text-green-700 border-green-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    };
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-wide text-blue-700">
                    Resident Dashboard
                </p>

                <h1 class="mt-1 text-2xl font-bold text-slate-900">
                    My Incident Reports
                </h1>

                <p class="mt-2 max-w-3xl text-sm text-slate-600">
                    Submit new community safety reports and monitor the status of incidents you reported.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ $incidentCreateUrl }}"
                   class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                    + Report Incident
                </a>

                <a href="{{ $incidentIndexUrl }}"
                   class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                    My Reports
                </a>
            </div>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">My Reports</p>
            <p class="mt-3 text-3xl font-bold text-slate-900">{{ $myReportsCount }}</p>
            <p class="mt-2 text-xs text-slate-500">Total reports you submitted</p>
        </div>

        <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-yellow-700">Pending</p>
            <p class="mt-3 text-3xl font-bold text-yellow-900">{{ $pendingReportsCount }}</p>
            <p class="mt-2 text-xs text-yellow-700">Waiting for review</p>
        </div>

        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-blue-700">Active</p>
            <p class="mt-3 text-3xl font-bold text-blue-900">{{ $activeReportsCount }}</p>
            <p class="mt-2 text-xs text-blue-700">Currently being handled</p>
        </div>

        <div class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-green-700">Resolved</p>
            <p class="mt-3 text-3xl font-bold text-green-900">{{ $resolvedReportsCount }}</p>
            <p class="mt-2 text-xs text-green-700">Closed or completed reports</p>
        </div>

        <div class="rounded-2xl border border-purple-200 bg-purple-50 p-5 shadow-sm">
            <p class="text-sm font-semibold text-purple-700">Announcements</p>
            <p class="mt-3 text-3xl font-bold text-purple-900">{{ $announcementsCount }}</p>
            <p class="mt-2 text-xs text-purple-700">Active barangay announcements</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        My Latest Reports
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Recent incident reports submitted using your account.
                    </p>
                </div>

                <a href="{{ $incidentIndexUrl }}"
                   class="text-sm font-bold text-blue-700 hover:text-blue-900">
                    Open My Reports →
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                Incident
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                Type
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                Priority
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                Status
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($myLatestReports as $incident)
                            @php
                                $incidentCode = $incident->incident_code ?? ('INC-' . str_pad((string) $incident->id, 5, '0', STR_PAD_LEFT));
                                $incidentTitle = $incident->incident_title ?? 'Untitled Incident';
                                $categoryName = $incident->category_name ?? 'Uncategorized';
                                $priority = $incident->priority ?? 'low';
                                $statusName = $incident->status_name ?? 'Pending';

                                $showUrl = Route::has('resident.incidents.show')
                                    ? route('resident.incidents.show', $incident->id)
                                    : '#';
                            @endphp

                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4">
                                    <p class="text-xs font-bold uppercase tracking-wide text-blue-700">
                                        {{ $incidentCode }}
                                    </p>

                                    <p class="mt-1 max-w-xs truncate text-sm font-semibold text-slate-900">
                                        {{ $incidentTitle }}
                                    </p>
                                </td>

                                <td class="px-5 py-4 text-sm font-medium text-slate-700">
                                    {{ $categoryName }}
                                </td>

                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $priorityBadgeClass($priority) }}">
                                        {{ ucfirst((string) $priority) }}
                                    </span>
                                </td>

                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $statusBadgeClass($statusName) }}">
                                        {{ ucfirst(str_replace('_', ' ', (string) $statusName)) }}
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <a href="{{ $showUrl }}"
                                       class="inline-flex rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-14 text-center">
                                    <div class="mx-auto max-w-md">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl">
                                            📝
                                        </div>

                                        <h3 class="mt-4 text-base font-bold text-slate-900">
                                            No reports yet
                                        </h3>

                                        <p class="mt-2 text-sm text-slate-500">
                                            Submit your first incident report to start tracking it here.
                                        </p>

                                        <a href="{{ $incidentCreateUrl }}"
                                           class="mt-5 inline-flex items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                                            Report Incident
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-bold text-slate-900">
                        Active Announcements
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Latest barangay safety notices and public updates.
                    </p>
                </div>

                <div class="space-y-4 p-6">
                    @forelse ($latestAnnouncements as $announcement)
                        @php
                            $title = $announcement->title ?? $announcement->announcement_title ?? 'Untitled announcement';
                            $body = $announcement->body ?? $announcement->message ?? $announcement->content ?? 'No announcement details.';

                            try {
                                $announcementDate = $announcement->created_at
                                    ? \Carbon\Carbon::parse($announcement->created_at)->format('M d, Y h:i A')
                                    : '—';
                            } catch (\Throwable $e) {
                                $announcementDate = '—';
                            }
                        @endphp

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <h3 class="text-sm font-bold text-slate-900">
                                {{ $title }}
                            </h3>

                            <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">
                                {{ $body }}
                            </p>

                            <p class="mt-3 text-xs text-slate-400">
                                {{ $announcementDate }}
                            </p>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                            <div class="text-3xl">📢</div>

                            <h3 class="mt-3 text-sm font-bold text-slate-900">
                                No announcements
                            </h3>

                            <p class="mt-1 text-sm text-slate-500">
                                Public announcements will appear here once posted.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
                <h3 class="text-sm font-bold text-blue-900">
                    Reporting Reminder
                </h3>

                <p class="mt-2 text-sm leading-6 text-blue-800">
                    Submit only accurate incident details. Include the location, description, and evidence when available to help barangay responders act faster.
                </p>

                <a href="{{ $incidentCreateUrl }}"
                   class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800">
                    Report New Incident
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
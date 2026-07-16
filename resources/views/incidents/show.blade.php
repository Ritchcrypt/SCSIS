@extends('layouts.admin')

@section('title', 'Incident Details | DaoSystem')

@section('content')
<link rel="stylesheet"
      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
    #incidentDetailMap {
        height: 360px;
        width: 100%;
        border-radius: 1rem;
        z-index: 1;
    }

    .incident-detail-pin {
        width: 26px;
        height: 26px;
        border-radius: 9999px;
        background: #172554;
        border: 4px solid white;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.45);
    }
</style>

@php
    /*
    |--------------------------------------------------------------------------
    | Route Prefix Resolver
    |--------------------------------------------------------------------------
    | Keeps the page compatible with admin, official, tanod, and resident routes.
    */

    $rawRole = data_get(auth()->user(), 'role', 'admin');

    if (is_object($rawRole)) {
        $role = strtolower((string) data_get($rawRole, 'value', 'admin'));
    } else {
        $role = strtolower((string) $rawRole);
    }

    $allowedRoles = ['admin', 'official', 'tanod', 'resident'];
    $routePrefix = in_array($role, $allowedRoles, true) ? $role . '.' : 'admin.';

    $indexRouteName = Route::has($routePrefix . 'incidents.index')
        ? $routePrefix . 'incidents.index'
        : (Route::has('incidents.index') ? 'incidents.index' : null);

    $indexUrl = $indexRouteName ? route($indexRouteName) : url()->previous();

    $updateStatusRouteName = Route::has($routePrefix . 'incidents.update-status')
    ? $routePrefix . 'incidents.update-status'
    : (Route::has('incidents.update-status') ? 'incidents.update-status' : null);

$updateStatusUrl = $updateStatusRouteName
    ? route($updateStatusRouteName, $incident)
    : null;

$escalateRouteName = Route::has($routePrefix . 'incidents.escalate')
    ? $routePrefix . 'incidents.escalate'
    : (Route::has('incidents.escalate') ? 'incidents.escalate' : null);

$escalateUrl = $escalateRouteName
    ? route($escalateRouteName, $incident)
    : null;

$messageRouteName = Route::has($routePrefix . 'incidents.messages.store')
    ? $routePrefix . 'incidents.messages.store'
    : (Route::has($routePrefix . 'incidents.store-message')
        ? $routePrefix . 'incidents.store-message'
        : (Route::has('incidents.messages.store')
            ? 'incidents.messages.store'
            : (Route::has('incidents.store-message') ? 'incidents.store-message' : null)));

$messageUrl = $messageRouteName
    ? route($messageRouteName, $incident)
    : null;

$createCaseUrl = null;

if ($role === 'admin' && Route::has('admin.cases.index')) {
    $createCaseUrl = route('admin.cases.index', [
        'incident_id' => data_get($incident, 'id'),
        'open_create' => 1,
    ]);
}

$statuses = $statuses ?? collect();
$responders = $responders ?? ($tanods ?? collect());

$currentStatusId = data_get($incident, 'status_id')
    ?? data_get($incident, 'status.id')
    ?? data_get($incident, 'currentStatus.id');

$currentAssigneeId = data_get($incident, 'assigned_to')
    ?? data_get($incident, 'assigned_tanod_id')
    ?? data_get($incident, 'assignedResponder.id')
    ?? data_get($incident, 'assignedTanod.id')
    ?? data_get($incident, 'assignedUser.id')
    ?? data_get($incident, 'assignee.id');

    /*
    |--------------------------------------------------------------------------
    | Local Badge Helpers
    |--------------------------------------------------------------------------
    */

    $severityBadgeClass = function ($severity) {
        $severity = strtolower((string) $severity);

        return match ($severity) {
            'critical', 'emergency' => 'bg-red-100 text-red-700 border-red-200',
            'high' => 'bg-orange-100 text-orange-700 border-orange-200',
            'medium', 'moderate' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'low' => 'bg-green-100 text-green-700 border-green-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    };

    $statusBadgeClass = function ($status) {
        $status = strtolower((string) $status);

        return match ($status) {
            'pending', 'reported' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'verified', 'validated' => 'bg-blue-100 text-blue-700 border-blue-200',
            'responding', 'in progress', 'in_progress' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
            'resolved', 'completed', 'closed' => 'bg-green-100 text-green-700 border-green-200',
            'rejected', 'invalid', 'cancelled', 'canceled' => 'bg-red-100 text-red-700 border-red-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    };

    /*
    |--------------------------------------------------------------------------
    | Safe Incident Values
    |--------------------------------------------------------------------------
    */

    $incidentTitle = data_get($incident, 'display_title')
        ?? data_get($incident, 'incident_title')
        ?? data_get($incident, 'title')
        ?? 'Untitled Incident';

    $incidentDescription = data_get($incident, 'incident_description')
        ?? data_get($incident, 'description')
        ?? data_get($incident, 'details')
        ?? 'No description provided.';

    $categoryId = data_get($incident, 'category_id');

$categoryRecord = null;

if ($categoryId) {
    $categoryRecord = \App\Models\IncidentCategory::find($categoryId);
}

$categoryName = data_get($incident, 'category.category_name')
    ?? data_get($incident, 'category.name')
    ?? data_get($categoryRecord, 'category_name')
    ?? data_get($categoryRecord, 'name')
    ?? data_get($incident, 'type')
    ?? data_get($incident, 'incident_type')
    ?? 'Uncategorized';
    
    $severityLabel = data_get($incident, 'severity_label')
        ?? data_get($incident, 'severity')
        ?? data_get($incident, 'priority')
        ?? 'Low';

    $statusName = data_get($incident, 'currentStatus.status_name')
        ?? data_get($incident, 'status.status_name')
        ?? data_get($incident, 'status.name')
        ?? data_get($incident, 'status')
        ?? 'Pending';

    $barangayName = data_get($incident, 'barangay.barangay_name')
        ?? data_get($incident, 'barangay.name')
        ?? (is_string(data_get($incident, 'barangay')) ? data_get($incident, 'barangay') : null)
        ?? '—';

    $locationAddress = data_get($incident, 'location.location_address')
        ?? data_get($incident, 'location.address')
        ?? data_get($incident, 'address')
        ?? (is_string(data_get($incident, 'location')) ? data_get($incident, 'location') : null)
        ?? 'No exact location provided';

    $latitude = data_get($incident, 'location.latitude')
    ?? data_get($incident, 'latitude');

$longitude = data_get($incident, 'location.longitude')
    ?? data_get($incident, 'longitude');

$hasMapCoordinates = is_numeric($latitude) && is_numeric($longitude);

$incidentMapLatitude = $hasMapCoordinates
    ? (float) $latitude
    : 11.3945;

$incidentMapLongitude = $hasMapCoordinates
    ? (float) $longitude
    : 122.6858;

$barangayMapSearch = $locationAddress !== 'No exact location provided'
    ? $locationAddress
    : ($barangayName !== '—' ? $barangayName : $incidentTitle);

$barangayMapUrl = Route::has('admin.map.index')
    ? route('admin.map.index', ['search' => $barangayMapSearch])
    : null;

    $reporterName = data_get($incident, 'reporter.name')
        ?? data_get($incident, 'user.name')
        ?? data_get($incident, 'resident.name')
        ?? data_get($incident, 'reported_by')
        ?? 'Unknown Reporter';

    $reporterEmail = data_get($incident, 'reporter.email')
        ?? data_get($incident, 'user.email')
        ?? data_get($incident, 'resident.email')
        ?? null;

    $reporterContact = data_get($incident, 'reporter.contact_number')
        ?? data_get($incident, 'reporter.phone')
        ?? data_get($incident, 'user.contact_number')
        ?? data_get($incident, 'resident.contact_number')
        ?? data_get($incident, 'contact_number')
        ?? '—';

    $assignedName = data_get($incident, 'assignedResponder.name')
        ?? data_get($incident, 'assignedTanod.name')
        ?? data_get($incident, 'assignedUser.name')
        ?? data_get($incident, 'assignee.name')
        ?? data_get($incident, 'assigned_to')
        ?? 'Not assigned';

    $reportedRaw = data_get($incident, 'incident_datetime')
        ?? data_get($incident, 'reported_at')
        ?? data_get($incident, 'created_at');

    try {
        $reportedDate = $reportedRaw
            ? \Carbon\Carbon::parse($reportedRaw)->format('F d, Y')
            : '—';

        $reportedTime = $reportedRaw
            ? \Carbon\Carbon::parse($reportedRaw)->format('h:i A')
            : '—';
    } catch (\Throwable $e) {
        $reportedDate = '—';
        $reportedTime = '—';
    }

    $updatedRaw = data_get($incident, 'updated_at');

    try {
        $lastUpdated = $updatedRaw
            ? \Carbon\Carbon::parse($updatedRaw)->format('F d, Y h:i A')
            : '—';
    } catch (\Throwable $e) {
        $lastUpdated = '—';
    }

    /*
    |--------------------------------------------------------------------------
    | Optional Collections
    |--------------------------------------------------------------------------
    */

    $evidences = data_get($incident, 'evidences')
        ?? data_get($incident, 'evidence')
        ?? data_get($incident, 'attachments')
        ?? collect();

    $histories = data_get($incident, 'statusHistories')
        ?? data_get($incident, 'statusHistory')
        ?? data_get($incident, 'status_histories')
        ?? data_get($incident, 'histories')
        ?? collect();

    $messages = data_get($incident, 'messages')
        ?? data_get($incident, 'incidentMessages')
        ?? collect();
        
        $relatedCases = data_get($incident, 'caseRecords') ?? collect();

$createCaseUrl = null;

if ($role === 'admin' && Route::has('admin.cases.index')) {
    $createCaseUrl = route('admin.cases.index', [
        'incident_id' => data_get($incident, 'id'),
        'open_create' => 1,
    ]);
}

    $canUpdateIncident = $updateStatusUrl
    && auth()->check()
    && in_array($role, ['admin', 'official', 'tanod'], true);

$canAssignResponder = $role === 'admin';

$canEscalateIncident = $escalateUrl
    && auth()->check()
    && in_array($role, ['admin', 'official'], true);

$canOpenBarangayMap = $role === 'admin' && $barangayMapUrl;

$agencyOptions = $agencyOptions ?? [
        'PNP' => 'PNP',
        'BFP' => 'BFP',
        'MDRRMO' => 'MDRRMO',
        'DSWD' => 'DSWD',
        'DOH' => 'DOH',
        'Red Cross' => 'Red Cross',
        'Municipal Government' => 'Municipal Government',
    ];
@endphp

<div class="space-y-6">
    {{-- Header --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <a href="{{ $indexUrl }}"
                   class="inline-flex items-center text-sm font-semibold text-blue-700 transition hover:text-blue-900">
                    ← Back to Incidents
                </a>

                <div class="mt-4">
                    

                    <h1 class="mt-1 text-2xl font-bold text-slate-900">
                        {{ $incidentTitle }}
                    </h1>

                    <p class="mt-2 max-w-3xl text-sm text-slate-600">
                        Detailed incident report information, reporter details, location, evidence, and status history.
                    </p>
                </div>
            </div>

        </div>
    </div>

    {{-- Flash Messages --}}
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

    {{-- Main Grid --}}
    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Left Content --}}
        <div class="space-y-6 xl:col-span-2">
            {{-- Incident Summary --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-bold text-slate-900">
                        Incident Summary
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Core details submitted for this incident report.
                    </p>
                </div>

                <div class="grid gap-5 p-6 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Incident Type
                        </p>

                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            {{ $categoryName }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Current Status
                        </p>

                        <p class="mt-1">
                            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $statusBadgeClass($statusName) }}">
                                {{ ucfirst(str_replace('_', ' ', (string) $statusName)) }}
                            </span>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Severity
                        </p>

                        <p class="mt-1">
                            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $severityBadgeClass($severityLabel) }}">
                                {{ ucfirst((string) $severityLabel) }}
                            </span>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Date Reported
                        </p>

                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            {{ $reportedDate }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Time Reported
                        </p>

                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            {{ $reportedTime }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Description --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-bold text-slate-900">
                        Description
                    </h2>
                </div>

                <div class="p-6">
                    <p class="whitespace-pre-line text-sm leading-6 text-slate-700">
                        {{ $incidentDescription }}
                    </p>
                </div>
            </div>

            {{-- Location --}}
<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-6 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-base font-bold text-slate-900">
                    Location Details
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Barangay, exact location, and map preview.
                </p>
            </div>

            @if ($canOpenBarangayMap)
                <a href="{{ $barangayMapUrl }}"
                   class="inline-flex items-center justify-center rounded-xl border border-blue-700 px-4 py-2 text-sm font-bold text-blue-700 hover:bg-blue-50">
                    Open in Barangay Map
                </a>
            @endif
        </div>
    </div>

    <div class="space-y-5 p-6">
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    Barangay
                </p>

                <p class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $barangayName }}
                </p>
            </div>

            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    Coordinates
                </p>

                <p class="mt-1 text-sm font-semibold text-slate-900">
                    @if ($hasMapCoordinates)
                        {{ $latitude }}, {{ $longitude }}
                    @else
                        Not provided
                    @endif
                </p>
            </div>

            <div class="sm:col-span-2">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    Exact Address / Landmark
                </p>

                <p class="mt-1 text-sm leading-6 text-slate-700">
                    {{ $locationAddress }}
                </p>
            </div>
        </div>

        <div>
            <p class="mb-3 text-sm font-semibold text-slate-700">
                Incident Location Preview
            </p>

            <div id="incidentDetailMap"></div>

            @unless ($hasMapCoordinates)
                <p class="mt-3 rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm font-medium text-yellow-700">
                    No coordinates saved for this incident yet. Set its location from the Barangay Map module.
                </p>
            @endunless
        </div>
    </div>
</div>

            {{-- Evidence --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-bold text-slate-900">
                        Evidence / Attachments
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Uploaded photos, documents, or supporting files.
                    </p>
                </div>

                <div class="p-6">
                    @if (count($evidences))
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($evidences as $evidence)
@php
    $evidenceId = data_get($evidence, 'id');

    $filePath = data_get($evidence, 'file_path')
        ?? data_get($evidence, 'path')
        ?? data_get($evidence, 'url')
        ?? data_get($evidence, 'file_url');

    $fileName = data_get($evidence, 'file_name')
        ?? data_get($evidence, 'name')
        ?? basename((string) $filePath);

    $fileType = strtolower((string) (
        data_get($evidence, 'file_type')
        ?? data_get($evidence, 'mime_type')
        ?? pathinfo((string) $filePath, PATHINFO_EXTENSION)
    ));

    $isImage = str_contains($fileType, 'image')
        || in_array($fileType, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);

    $fileUrl = $evidenceId && Route::has('incident-evidence.file')
        ? route('incident-evidence.file', $evidenceId)
        : null;
@endphp

                                <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                    @if ($fileUrl && $isImage)
                                        <a href="{{ $fileUrl }}" target="_blank" class="block">
                                            <img src="{{ $fileUrl }}"
                                                 alt="{{ $fileName }}"
                                                 class="h-40 w-full object-cover">
                                        </a>
                                    @else
                                        <div class="flex h-40 items-center justify-center bg-slate-100 text-4xl">
                                            📎
                                        </div>
                                    @endif

                                    <div class="p-4">
                                        <p class="truncate text-sm font-semibold text-slate-800">
                                            {{ $fileName ?: 'Attachment' }}
                                        </p>

                                        @if ($fileUrl)
                                            <a href="{{ $fileUrl }}"
                                               target="_blank"
                                               class="mt-2 inline-flex text-sm font-semibold text-blue-700 hover:text-blue-900">
                                                Open File
                                            </a>
                                        @else
                                            <p class="mt-2 text-sm text-slate-500">
                                                File path unavailable
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                            <div class="text-3xl">
                                📎
                            </div>

                            <h3 class="mt-3 text-sm font-bold text-slate-900">
                                No evidence uploaded
                            </h3>

                            <p class="mt-1 text-sm text-slate-500">
                                This incident report has no attached files.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right Sidebar Content --}}
        <div class="space-y-6">
            {{-- Reporter --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-bold text-slate-900">
                        Reporter Information
                    </h2>
                </div>

                <div class="space-y-5 p-6">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Name
                        </p>

                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            {{ $reporterName }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Contact Number
                        </p>

                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            {{ $reporterContact }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Email
                        </p>

                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            {{ $reporterEmail ?: '—' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Record Info --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-bold text-slate-900">
                        Record Info
                    </h2>
                </div>

                <div class="space-y-5 p-6">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Incident ID
                        </p>

                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            #{{ data_get($incident, 'id') }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Last Updated
                        </p>

                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            {{ $lastUpdated }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Status Update --}}
            {{-- Related Cases --}}
@if ($role === 'admin')
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Related Cases
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Barangay case records connected to this incident.
                    </p>
                </div>

            </div>
        </div>

        <div class="space-y-4 p-6">
            @if (count($relatedCases))
                @foreach ($relatedCases as $caseRecord)
                    @php
                        $caseNumber = data_get($caseRecord, 'case_number') ?? ('Case #' . data_get($caseRecord, 'id'));

                        $caseType = data_get($caseRecord, 'display_type')
                            ?? ucfirst(str_replace('_', ' ', (string) data_get($caseRecord, 'case_type')));

                        $caseStatus = data_get($caseRecord, 'display_status')
                            ?? ucfirst(str_replace('_', ' ', (string) data_get($caseRecord, 'status')));

                        $hearingDateRaw = data_get($caseRecord, 'hearing_date');

                        try {
                            $hearingDate = $hearingDateRaw
                                ? \Carbon\Carbon::parse($hearingDateRaw)->format('M d, Y')
                                : 'No hearing date';
                        } catch (\Throwable $e) {
                            $hearingDate = 'No hearing date';
                        }

                        $handledBy = data_get($caseRecord, 'handled_by') ?: 'Not assigned';
                    @endphp

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-bold text-blue-700">
                            Case No. {{ $caseNumber }}
                        </p>

                        <div class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Type
                                </p>
                                <p class="mt-1 font-semibold text-slate-800">
                                    {{ $caseType }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Status
                                </p>
                                <p class="mt-1 font-semibold text-slate-800">
                                    {{ $caseStatus }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Hearing Date
                                </p>
                                <p class="mt-1 font-semibold text-slate-800">
                                    {{ $hearingDate }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Handled By
                                </p>
                                <p class="mt-1 font-semibold text-slate-800">
                                    {{ $handledBy }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                    <h3 class="text-sm font-bold text-slate-900">
                        No related case yet
                    </h3>

                    <p class="mt-1 text-sm text-slate-500">
                        Create a case record if this incident needs formal barangay handling.
                    </p>

                    @if ($createCaseUrl)
                        <a href="{{ $createCaseUrl }}"
                           class="mt-4 inline-flex rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white hover:bg-blue-800">
                            + Create Case
                        </a>
                    @endif

                </div>
            @endif
        </div>
    </div>
@endif

            @if ($canUpdateIncident)
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-base font-bold text-slate-900">
                            Update Incident
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            {{ $canAssignResponder
    ? 'Change the current status, assign a responder, and add remarks.'
    : 'Change the current status and add remarks.' }}
                        </p>
                    </div>

                    <form method="POST" action="{{ $updateStatusUrl }}" class="space-y-5 p-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="status_id" class="mb-2 block text-sm font-semibold text-slate-700">
                                Status
                            </label>

                            <select
                                id="status_id"
                                name="status_id"
                                required
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="">Select Status</option>

                                @foreach ($statuses as $status)
                                    @php
                                        $statusValue = data_get($status, 'id');
                                        $statusLabel = data_get($status, 'status_name')
                                            ?? data_get($status, 'name')
                                            ?? data_get($status, 'label')
                                            ?? 'Status';
                                    @endphp

                                    <option value="{{ $statusValue }}" @selected((string) $currentStatusId === (string) $statusValue)>
                                        {{ $statusLabel }}
                                    </option>
                                @endforeach
                            </select>

                            @error('status_id')
                                <p class="mt-2 text-sm font-medium text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Save Update
                        </button>
                    </form>
                </div>
            @endif

            {{-- Escalate Incident --}}
            @if ($canEscalateIncident)
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-base font-bold text-slate-900">
                            Escalate Incident
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Forward this incident to a higher-response agency when barangay response is not enough.
                        </p>
                    </div>

                    <form method="POST" action="{{ $escalateUrl }}" class="space-y-5 p-6">
                        @csrf

                        <div>
                            <label for="agency" class="mb-2 block text-sm font-semibold text-slate-700">
                                Agency
                            </label>

                            <select
                                id="agency"
                                name="agency"
                                required
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="">Select Agency</option>

                                @foreach ($agencyOptions as $agencyValue => $agencyLabel)
                                    <option value="{{ $agencyValue }}" @selected(old('agency') === (string) $agencyValue)>
                                        {{ $agencyLabel }}
                                    </option>
                                @endforeach
                            </select>

                            @error('agency')
                                <p class="mt-2 text-sm font-medium text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label for="reason" class="mb-2 block text-sm font-semibold text-slate-700">
                                Escalation Reason
                            </label>

                            <textarea
                                id="reason"
                                name="reason"
                                rows="4"
                                placeholder="Explain why this incident needs escalation..."
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >{{ old('reason') }}</textarea>

                            @error('reason')
                                <p class="mt-2 text-sm font-medium text-red-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        >
                            Escalate Incident
                        </button>
                    </form>
                </div>
            @endif

            {{-- Status Timeline --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-bold text-slate-900">
                        Status History
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Timeline of updates for this incident.
                    </p>
                </div>

                <div class="p-6">
                    @if (count($histories))
                        <div class="space-y-5">
                            @foreach ($histories as $history)
                                @php
                                    $historyStatus = data_get($history, 'status.status_name')
                                        ?? data_get($history, 'status.name')
                                        ?? data_get($history, 'status')
                                        ?? data_get($history, 'new_status')
                                        ?? 'Updated';

                                    $historyRemarks = data_get($history, 'remarks')
                                        ?? data_get($history, 'note')
                                        ?? data_get($history, 'description')
                                        ?? null;

                                    $historyUser = data_get($history, 'user.name')
                                        ?? data_get($history, 'updatedBy.name')
                                        ?? data_get($history, 'createdBy.name')
                                        ?? 'System';

                                    $historyRawDate = data_get($history, 'created_at')
                                        ?? data_get($history, 'updated_at');

                                    try {
                                        $historyDate = $historyRawDate
                                            ? \Carbon\Carbon::parse($historyRawDate)->format('M d, Y h:i A')
                                            : '—';
                                    } catch (\Throwable $e) {
                                        $historyDate = '—';
                                    }
                                @endphp

                                <div class="relative border-l-2 border-slate-200 pl-4">
                                    <div class="absolute -left-[7px] top-1 h-3 w-3 rounded-full bg-blue-700"></div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $statusBadgeClass($historyStatus) }}">
                                            {{ ucfirst(str_replace('_', ' ', (string) $historyStatus)) }}
                                        </span>

                                        <span class="text-xs font-medium text-slate-500">
                                            {{ $historyDate }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm font-semibold text-slate-900">
                                        Updated by {{ $historyUser }}
                                    </p>

                                    @if ($historyRemarks)
                                        <p class="mt-1 text-sm leading-6 text-slate-600">
                                            {{ $historyRemarks }}
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                            <div class="text-3xl">
                                🕒
                            </div>

                            <h3 class="mt-3 text-sm font-bold text-slate-900">
                                No status history yet
                            </h3>

                            <p class="mt-1 text-sm text-slate-500">
                                Updates will appear here once this incident is processed.
                            </p>
                        </div>
                    @endif
                </div>
            </div>


    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div id="incidentDetailMapData"
     class="hidden"
     data-latitude="{{ $incidentMapLatitude }}"
     data-longitude="{{ $incidentMapLongitude }}"
     data-has-coordinates="{{ $hasMapCoordinates ? '1' : '0' }}"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    const incidentDetailMapData = document.getElementById('incidentDetailMapData');

    const incidentDetailLatitude = parseFloat(
        incidentDetailMapData.getAttribute('data-latitude') || '11.3945'
    );

    const incidentDetailLongitude = parseFloat(
        incidentDetailMapData.getAttribute('data-longitude') || '122.6858'
    );

    const incidentHasCoordinates =
        incidentDetailMapData.getAttribute('data-has-coordinates') === '1';

    const incidentDetailMap = L.map('incidentDetailMap', {
        zoomControl: true,
        scrollWheelZoom: true,
    }).setView(
        [incidentDetailLatitude, incidentDetailLongitude],
        incidentHasCoordinates ? 16 : 13
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(incidentDetailMap);

    function createIncidentDetailIcon() {
        return L.divIcon({
            className: '',
            html: '<div class="incident-detail-pin"></div>',
            iconSize: [26, 26],
            iconAnchor: [13, 13],
            popupAnchor: [0, -12],
        });
    }

    if (incidentHasCoordinates) {
        L.marker([incidentDetailLatitude, incidentDetailLongitude], {
            icon: createIncidentDetailIcon(),
        })
            .addTo(incidentDetailMap)
            .bindPopup('Incident location')
            .openPopup();
    } else {
        L.popup()
            .setLatLng([incidentDetailLatitude, incidentDetailLongitude])
            .setContent('No saved coordinates for this incident.')
            .openOn(incidentDetailMap);
    }
</script>
@endsection
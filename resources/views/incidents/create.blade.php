@extends('layouts.admin')

@section('title', 'Report Incident | DaoSystem')

@section('content')
@php
    $rawRole = data_get(auth()->user(), 'role', 'resident');

    if (is_object($rawRole)) {
        $role = strtolower((string) data_get($rawRole, 'value', 'resident'));
    } else {
        $role = strtolower((string) $rawRole);
    }

    $allowedRoles = ['admin', 'official', 'tanod', 'resident'];
    $routePrefix = in_array($role, $allowedRoles, true) ? $role . '.' : 'resident.';

    $storeRouteName = Route::has($routePrefix . 'incidents.store')
        ? $routePrefix . 'incidents.store'
        : (Route::has('incidents.store') ? 'incidents.store' : null);

    $indexRouteName = Route::has($routePrefix . 'incidents.index')
        ? $routePrefix . 'incidents.index'
        : (Route::has('incidents.index') ? 'incidents.index' : null);

    $storeUrl = $storeRouteName ? route($storeRouteName) : '#';
    $indexUrl = $indexRouteName ? route($indexRouteName) : url()->previous();

    $categories = $categories ?? collect();
    $barangays = $barangays ?? collect();
    $severityOptions = $severityOptions ?? [
        'low' => 'Low',
        'moderate' => 'Moderate',
        'high' => 'High',
        'critical' => 'Critical',
    ];
@endphp

<div class="space-y-6">
    {{-- Page Header --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <a href="{{ $indexUrl }}"
                   class="inline-flex items-center text-sm font-semibold text-blue-700 transition hover:text-blue-900">
                    ← Back to Incidents
                </a>

                <div class="mt-4">
                    <p class="text-sm font-bold uppercase tracking-wide text-blue-700">
                        Incident Reporting
                    </p>

                    <h1 class="mt-1 text-2xl font-bold text-slate-900">
                        Report New Incident
                    </h1>

                    <p class="mt-2 max-w-3xl text-sm text-slate-600">
                        Submit a real community safety incident report for review and response by authorized personnel.
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

    {{-- Validation Summary --}}
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
            <p class="font-bold">Please fix the following:</p>

            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form --}}
    <form method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div class="grid gap-6 xl:grid-cols-3">
            {{-- Main Form --}}
            <div class="space-y-6 xl:col-span-2">
                {{-- Incident Details --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-base font-bold text-slate-900">
                            Incident Details
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Describe what happened clearly and accurately.
                        </p>
                    </div>

                    <div class="space-y-5 p-6">
                        <div>
                            <label for="incident_title" class="mb-2 block text-sm font-semibold text-slate-700">
                                Incident Title
                            </label>

                            <input
                                id="incident_title"
                                type="text"
                                name="incident_title"
                                value="{{ old('incident_title') }}"
                                required
                                placeholder="Example: Road accident near public market"
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >

                            @error('incident_title')
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="category_id" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Incident Category
                                </label>

                                <select
                                    id="category_id"
                                    name="category_id"
                                    required
                                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                >
                                    <option value="">Select category</option>

                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>
                                            {{ $category->category_name ?? $category->name ?? 'Category' }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('category_id')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="priority" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Severity
                                </label>

                                <select
                                    id="priority"
                                    name="priority"
                                    required
                                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                >
                                    <option value="">Select severity</option>

                                    @foreach ($severityOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('priority') === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('priority')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="incident_description" class="mb-2 block text-sm font-semibold text-slate-700">
                                Description
                            </label>

                            <textarea
                                id="incident_description"
                                name="incident_description"
                                rows="6"
                                required
                                placeholder="Describe what happened, who was involved, visible danger, injuries, damage, or other important details..."
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >{{ old('incident_description') }}</textarea>

                            @error('incident_description')
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Location --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-base font-bold text-slate-900">
                            Location
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Provide the barangay and exact landmark or address.
                        </p>
                    </div>

                    <div class="space-y-5 p-6">
                        <div>
                            <label for="barangay_id" class="mb-2 block text-sm font-semibold text-slate-700">
                                Barangay
                            </label>

                            <select
                                id="barangay_id"
                                name="barangay_id"
                                required
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="">Select barangay</option>

                                @foreach ($barangays as $barangay)
                                    <option value="{{ $barangay->id }}" @selected((string) old('barangay_id') === (string) $barangay->id)>
                                        {{ $barangay->barangay_name ?? $barangay->name ?? 'Barangay' }}
                                    </option>
                                @endforeach
                            </select>

                            @error('barangay_id')
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="location_address" class="mb-2 block text-sm font-semibold text-slate-700">
                                Exact Location / Landmark
                            </label>

                            <textarea
                                id="location_address"
                                name="location_address"
                                rows="4"
                                required
                                placeholder="Example: Near Dao Public Market, beside the tricycle terminal..."
                                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >{{ old('location_address') }}</textarea>

                            @error('location_address')
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="latitude" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Latitude <span class="font-normal text-slate-400">(optional)</span>
                                </label>

                                <input
                                    id="latitude"
                                    type="text"
                                    name="latitude"
                                    value="{{ old('latitude') }}"
                                    placeholder="Example: 11.3945"
                                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                >

                                @error('latitude')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="longitude" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Longitude <span class="font-normal text-slate-400">(optional)</span>
                                </label>

                                <input
                                    id="longitude"
                                    type="text"
                                    name="longitude"
                                    value="{{ old('longitude') }}"
                                    placeholder="Example: 122.6842"
                                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                >

                                @error('longitude')
                                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Evidence --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-base font-bold text-slate-900">
                            Evidence
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Upload photos or PDF evidence if available.
                        </p>
                    </div>

                    <div class="space-y-5 p-6">
                        <div>
                            <label for="evidence" class="mb-2 block text-sm font-semibold text-slate-700">
                                Upload Evidence <span class="font-normal text-slate-400">(optional)</span>
                            </label>

                            <input
                                id="evidence"
                                type="file"
                                name="evidence[]"
                                multiple
                                accept=".jpg,.jpeg,.png,.webp,.pdf"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >

                            <p class="mt-2 text-xs text-slate-500">
                                Maximum 5 files. JPG, PNG, WEBP, or PDF only. Max 5MB each.
                            </p>

                            @error('evidence')
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror

                            @error('evidence.*')
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Panel --}}
            <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-base font-bold text-slate-900">
                            Submission Info
                        </h2>
                    </div>

                    <div class="space-y-5 p-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                Reporter
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ auth()->user()->name }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                Initial Status
                            </p>

                            <p class="mt-1">
                                <span class="inline-flex rounded-full border border-yellow-200 bg-yellow-100 px-3 py-1 text-xs font-bold text-yellow-700">
                                    Pending
                                </span>
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                Date
                            </p>

                            <p class="mt-1 text-sm font-semibold text-slate-900">
                                {{ now()->format('F d, Y') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
                    <h3 class="text-sm font-bold text-blue-900">
                        Reminder
                    </h3>

                    <p class="mt-2 text-sm leading-6 text-blue-800">
                        Submit only real and accurate incident information. False or misleading reports may delay emergency response.
                    </p>
                </div>

                <div class="flex flex-col gap-3">
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        Submit Incident Report
                    </button>

                    <a href="{{ $indexUrl }}"
                       class="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

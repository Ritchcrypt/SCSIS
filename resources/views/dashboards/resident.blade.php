@extends('layouts.admin')

@section('title', 'Resident Dashboard | DaoSystem')

@section('content')
@php
    $summary = $summary ?? [];

    $myReportsCount = $summary['my_reports'] ?? 0;
    $pendingReportsCount = $summary['pending_reports'] ?? 0;
    $activeReportsCount = $summary['active_reports'] ?? 0;
    $resolvedReportsCount = $summary['resolved_reports'] ?? 0;
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-blue-700">
                Resident Dashboard
            </p>

            <h1 class="mt-1 text-2xl font-bold text-slate-900">
                My Incident Report Overview
            </h1>

            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Track the status of your submitted incident reports and monitor local weather or disaster advisories.
            </p>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg">
            <p class="text-sm font-semibold text-blue-700">
                My Reports
            </p>

            <p class="mt-3 text-3xl font-bold text-blue-900">
                {{ $myReportsCount }}
            </p>

            <p class="mt-2 text-xs text-blue-700">
                Incident reports you submitted
            </p>
        </div>

        <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg">
            <p class="text-sm font-semibold text-yellow-700">
                Pending Reports
            </p>

            <p class="mt-3 text-3xl font-bold text-yellow-900">
                {{ $pendingReportsCount }}
            </p>

            <p class="mt-2 text-xs text-yellow-700">
                Reports waiting for review
            </p>
        </div>

        <div class="rounded-2xl border border-orange-200 bg-orange-50 p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg">
            <p class="text-sm font-semibold text-orange-700">
                Active Reports
            </p>

            <p class="mt-3 text-3xl font-bold text-orange-900">
                {{ $activeReportsCount }}
            </p>

            <p class="mt-2 text-xs text-orange-700">
                Reports currently being handled
            </p>
        </div>

        <div class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg">
            <p class="text-sm font-semibold text-green-700">
                Resolved Reports
            </p>

            <p class="mt-3 text-3xl font-bold text-green-900">
                {{ $resolvedReportsCount }}
            </p>

            <p class="mt-2 text-xs text-green-700">
                Reports already resolved or closed
            </p>
        </div>
    </div>

    <section class="mt-8">
        @include('components.dashboard.weather-disaster-feed')
    </section>
</div>
@endsection
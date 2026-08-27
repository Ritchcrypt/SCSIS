@extends('layouts.admin')

@section('title', 'Distress Signal Alert | TabangNow')

@section('content')
@php
    $mobileSosIndexRoute = auth()->user()?->isAdmin()
        ? 'admin.mobile-sos.index'
        : 'official.mobile-sos.index';
@endphp

<div class="space-y-6">
    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <div>
        <a href="{{ route($mobileSosIndexRoute) }}"
           class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-slate-900">
            ← Back to Distress Signal
        </a>
    </div>

    <div class="rounded-2xl border border-red-200 bg-red-50 p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-red-700">
                    Distress Signal Alert
                </p>
                <h1 class="mt-2 text-2xl font-black text-slate-950">
                    {{ $alert->alert_code }}
                </h1>
                <p class="mt-2 text-sm text-slate-700">
                    Triggered {{ optional($alert->triggered_at)->format('M d, Y h:i:s A') ?? 'Unknown time' }}
                </p>
            </div>

            <span class="inline-flex rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide
                {{ $alert->status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : ($alert->status === 'acknowledged' ? 'bg-amber-100 text-amber-800' : 'bg-red-600 text-white') }}">
                {{ $alert->status }}
            </span>
        </div>
    </div>

    <section class="rounded-2xl border border-red-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-black text-slate-900">Emergency</h2>
        <p class="mt-4 whitespace-pre-wrap text-base leading-7 text-slate-900">
            {{ $alert->emergency_details ?: 'No emergency description was stored for this earlier alert.' }}
        </p>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">Caller</h2>

            <dl class="mt-5 space-y-4 text-sm">
                <div>
                    <dt class="font-semibold text-slate-500">Submitted mobile number</dt>
                    <dd class="mt-1 text-lg font-black text-slate-900">
                        {{ $alert->contact_number ?: 'Not provided for this earlier alert' }}
                    </dd>
                </div>

                <div>
                    <dt class="font-semibold text-slate-500">Linked TabangNow user</dt>
                    <dd class="mt-1 font-bold text-slate-900">{{ $alert->display_name }}</dd>
                </div>

                @if ($alert->user)
                    <div>
                        <dt class="font-semibold text-slate-500">Role</dt>
                        <dd class="mt-1 capitalize text-slate-900">{{ $alert->user->role }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Registered address</dt>
                        <dd class="mt-1 text-slate-900">{{ $alert->user->address ?: 'Not available' }}</dd>
                    </div>
                @else
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-slate-700">
                        No TabangNow account was linked to this device. Use the submitted mobile number and location when responding.
                    </div>
                @endif
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">Location</h2>

            @if ($alert->latitude !== null && $alert->longitude !== null)
                <dl class="mt-5 space-y-4 text-sm">
                    <div>
                        <dt class="font-semibold text-slate-500">Location source</dt>
                        <dd class="mt-1 font-bold text-slate-900">
                            {{ $alert->location_source === 'last_known' ? 'Last known device location' : 'Current device GPS' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Latitude</dt>
                        <dd class="mt-1 font-mono text-slate-900">{{ $alert->latitude }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Longitude</dt>
                        <dd class="mt-1 font-mono text-slate-900">{{ $alert->longitude }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Accuracy</dt>
                        <dd class="mt-1 text-slate-900">
                            {{ $alert->accuracy_meters !== null ? number_format((float) $alert->accuracy_meters, 1).' meters' : 'Not reported' }}
                        </dd>
                    </div>
                </dl>

                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($alert->latitude.','.$alert->longitude) }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="mt-5 inline-flex rounded-xl bg-blue-700 px-4 py-3 text-sm font-black text-white shadow-sm hover:bg-blue-800">
                    Open Location in Maps
                </a>
            @else
                <p class="mt-4 text-sm text-slate-600">
                    Location was not stored for this earlier alert. New distress signal submissions require current GPS or a last-known device location.
                </p>
            @endif
        </section>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-black text-slate-900">Response</h2>

        <div class="mt-5 flex flex-wrap gap-3">
            @if ($alert->status !== 'resolved' && $alert->status !== 'acknowledged')
                <form method="POST" action="{{ route('emergency-alerts.acknowledge', $alert) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-xl bg-amber-500 px-5 py-3 text-sm font-black text-white shadow-sm hover:bg-amber-600">
                        Acknowledge Distress Signal
                    </button>
                </form>
            @endif

            @if ($alert->status !== 'resolved')
                <form method="POST" action="{{ route('emergency-alerts.resolve', $alert) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-black text-white shadow-sm hover:bg-emerald-700">
                        Mark Resolved
                    </button>
                </form>
            @endif
            <form method="POST"
                  action="{{ route('emergency-alerts.destroy', $alert) }}"
                  onsubmit="return confirm('Delete {{ $alert->alert_code }}? This distress signal will be removed from the responder module.');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="rounded-xl border border-red-300 bg-white px-5 py-3 text-sm font-black text-red-700 shadow-sm hover:bg-red-50">
                    Delete Distress Signal
                </button>
            </form>
        </div>

        <div class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <p class="font-semibold text-slate-500">Acknowledged</p>
                <p class="mt-1 text-slate-900">
                    {{ $alert->acknowledged_at ? $alert->acknowledged_at->format('M d, Y h:i:s A') : 'Not yet' }}
                    @if ($alert->acknowledgedBy)
                        by {{ $alert->acknowledgedBy->name }}
                    @endif
                </p>
            </div>

            <div>
                <p class="font-semibold text-slate-500">Resolved</p>
                <p class="mt-1 text-slate-900">
                    {{ $alert->resolved_at ? $alert->resolved_at->format('M d, Y h:i:s A') : 'Not yet' }}
                    @if ($alert->resolvedBy)
                        by {{ $alert->resolvedBy->name }}
                    @endif
                </p>
            </div>
        </div>
    </section>
</div>
@endsection

@extends('layouts.admin')

@section('title', 'Mobile Emergency Alert | TabangNow')

@section('content')
<div class="space-y-6">
    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-2xl border border-red-200 bg-red-50 p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-red-700">
                    Mobile Emergency Alert
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

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">Source</h2>

            <dl class="mt-5 space-y-4 text-sm">
                <div>
                    <dt class="font-semibold text-slate-500">Person</dt>
                    <dd class="mt-1 font-bold text-slate-900">{{ $alert->display_name }}</dd>
                </div>

                @if ($alert->user)
                    <div>
                        <dt class="font-semibold text-slate-500">Role</dt>
                        <dd class="mt-1 capitalize text-slate-900">{{ $alert->user->role }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Contact number</dt>
                        <dd class="mt-1 text-slate-900">{{ $alert->user->contact_number ?: 'Not available' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Address</dt>
                        <dd class="mt-1 text-slate-900">{{ $alert->user->address ?: 'Not available' }}</dd>
                    </div>
                @else
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
                        This SOS came from a mobile installation that was not linked to a TabangNow account. Treat it as a valid emergency alert; identity is simply unavailable.
                    </div>
                @endif
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">Location</h2>

            @if ($alert->latitude !== null && $alert->longitude !== null)
                <dl class="mt-5 space-y-4 text-sm">
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
            @else
                <p class="mt-4 text-sm text-slate-600">
                    Location was not available when the emergency alert was triggered. The SOS remains valid and should still be handled immediately.
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
                        Acknowledge Alert
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

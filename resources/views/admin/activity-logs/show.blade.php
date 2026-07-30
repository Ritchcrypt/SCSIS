@extends('layouts.admin')

@section('title', 'Activity Log Details | TabangNow')

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('admin.activity-logs.index', request()->query()) }}"
           class="inline-flex items-center text-sm font-semibold text-blue-600 transition hover:text-blue-700">
            ← Back to Activity Logs
        </a>

        <div class="mt-4">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">
                Read-only audit record
            </p>

            <h2 class="mt-1 text-2xl font-bold text-slate-900">
                {{ \Illuminate\Support\Str::headline(str_replace('.', ' ', $activityLog->event)) }}
            </h2>

            <p class="mt-2 max-w-3xl text-sm text-slate-600">
                {{ $activityLog->description }}
            </p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-bold text-slate-900">
                Event information
            </h3>

            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Log ID
                    </dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        #{{ $activityLog->id }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Recorded
                    </dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $activityLog->created_at?->format('M d, Y h:i:s A') ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Event
                    </dt>
                    <dd class="mt-1 break-all font-mono text-sm font-semibold text-blue-700">
                        {{ $activityLog->event }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Category
                    </dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        {{ \Illuminate\Support\Str::headline($activityLog->category) }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-bold text-slate-900">
                Actor and target
            </h3>

            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Actor
                    </dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $activityLog->actor_name ?: 'System / Unknown' }}
                    </dd>
                    <dd class="mt-0.5 text-xs text-slate-500">
                        ID: {{ $activityLog->actor_id ?: '—' }}
                        · Role: {{ $activityLog->actor_role
                            ? ucfirst($activityLog->actor_role)
                            : '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Target user
                    </dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $activityLog->target_name ?: 'No user target' }}
                    </dd>
                    <dd class="mt-0.5 text-xs text-slate-500">
                        ID: {{ $activityLog->target_user_id ?: '—' }}
                        · Role: {{ $activityLog->target_role
                            ? ucfirst($activityLog->target_role)
                            : '—' }}
                    </dd>
                </div>
            </dl>

            <p class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
                Actor and target names are stored as snapshots, so this record
                remains readable after an account is deleted.
            </p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-2">
            <h3 class="text-base font-bold text-slate-900">
                Request information
            </h3>

            <dl class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Route
                    </dt>
                    <dd class="mt-1 break-all font-mono text-sm text-slate-900">
                        {{ $activityLog->route_name ?: '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Method
                    </dt>
                    <dd class="mt-1 font-mono text-sm font-semibold text-slate-900">
                        {{ $activityLog->request_method ?: '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        IP address
                    </dt>
                    <dd class="mt-1 font-mono text-sm text-slate-900">
                        {{ $activityLog->ip_address ?: '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        User agent
                    </dt>
                    <dd class="mt-1 break-words text-sm text-slate-900">
                        {{ $activityLog->user_agent ?: '—' }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-2">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-base font-bold text-slate-900">
                    Metadata
                </h3>

                <p class="text-xs font-semibold text-slate-500">
                    Sensitive keys are redacted before display.
                </p>
            </div>

            @php
                $metadataJson = json_encode(
                    $displayMetadata,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                ) ?: '{}';
            @endphp

            <pre class="mt-5 max-h-[32rem] overflow-auto rounded-xl bg-slate-950 p-5 text-xs leading-6 text-slate-100">{{ $metadataJson }}</pre>
        </section>
    </div>
</div>
@endsection

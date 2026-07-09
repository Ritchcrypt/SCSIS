@extends('layouts.admin')

@section('title', 'Tanod Task Details | DaoSystem')

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <a href="{{ route('admin.tanod-tasks.index') }}"
           class="inline-flex text-sm font-semibold text-blue-700 hover:text-blue-900">
            ← Back to Tanod Tasks
        </a>

        <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-blue-700">
                    Tanod Task
                </p>

                <h1 class="mt-1 text-2xl font-bold text-slate-900">
                    {{ $task->title }}
                </h1>

                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    {{ $task->description ?: 'No description provided.' }}
                </p>
            </div>

            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold
                @class([
                    'border-green-200 bg-green-50 text-green-700' => $task->status === 'open',
                    'border-slate-200 bg-slate-100 text-slate-700' => $task->status === 'closed',
                    'border-red-200 bg-red-50 text-red-700' => $task->status === 'cancelled',
                ])
            ">
                {{ ucfirst($task->status) }}
            </span>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                Total Tanods
            </p>

            <p class="mt-3 text-3xl font-bold text-slate-900">
                {{ $task->responses->count() }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                Accepted
            </p>

            <p class="mt-3 text-3xl font-bold text-green-600">
                {{ $task->responses->where('response_status', 'accepted')->count() }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                Declined
            </p>

            <p class="mt-3 text-3xl font-bold text-red-600">
                {{ $task->responses->where('response_status', 'declined')->count() }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                Pending
            </p>

            <p class="mt-3 text-3xl font-bold text-slate-600">
                {{ $task->responses->where('response_status', 'pending')->count() }}
            </p>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-base font-bold text-slate-900">
                Task Information
            </h2>
        </div>

        <div class="grid gap-5 p-6 md:grid-cols-2">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    Location
                </p>

                <p class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $task->location ?: 'No location provided' }}
                </p>
            </div>

            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    Priority
                </p>

                <p class="mt-1 text-sm font-semibold text-slate-900">
                    {{ ucfirst($task->priority) }}
                </p>
            </div>

            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    Task Schedule
                </p>

                <p class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $task->task_datetime ? $task->task_datetime->format('F d, Y h:i A') : 'No schedule' }}
                </p>
            </div>

            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    Response Due
                </p>

                <p class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $task->due_at ? $task->due_at->format('F d, Y h:i A') : 'No due date' }}
                </p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-base font-bold text-slate-900">
                    Tanod Responses
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Shows who accepted, declined, or has not responded.
                </p>
            </div>

            @if ($task->status === 'open')
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('admin.tanod-tasks.close', $task) }}">
                        @csrf
                        @method('PATCH')

                        <button type="submit"
                                class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Close Task
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.tanod-tasks.cancel', $task) }}">
                        @csrf
                        @method('PATCH')

                        <button type="submit"
                                class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            Cancel Task
                        </button>
                    </form>
                </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Tanod
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Response
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Note
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Responded At
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($task->responses as $response)
                        @php
                            $tanodName = $response->employee?->user?->name
                                ?? $response->user?->name
                                ?? 'Tanod #' . $response->employee_id;
                        @endphp

                        <tr>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900">
                                    {{ $tanodName }}
                                </p>

                                <p class="mt-1 text-xs text-slate-500">
                                    Employee ID: {{ $response->employee_id }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold
                                    @class([
                                        'border-slate-200 bg-slate-100 text-slate-700' => $response->response_status === 'pending',
                                        'border-green-200 bg-green-50 text-green-700' => $response->response_status === 'accepted',
                                        'border-red-200 bg-red-50 text-red-700' => $response->response_status === 'declined',
                                    ])
                                ">
                                    {{ ucfirst($response->response_status) }}
                                </span>
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $response->response_note ?: '—' }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $response->responded_at ? $response->responded_at->format('M d, Y h:i A') : 'Not yet responded' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
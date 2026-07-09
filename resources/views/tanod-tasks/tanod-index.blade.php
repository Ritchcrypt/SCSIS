@extends('layouts.admin')

@section('title', 'My Tanod Tasks | DaoSystem')

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-sm font-medium uppercase tracking-wide text-blue-700">
            Tanod Tasks
        </p>

        <h1 class="mt-1 text-2xl font-bold text-slate-900">
            My Assigned Tasks
        </h1>

        <p class="mt-2 max-w-3xl text-sm text-slate-600">
            Review tasks assigned by the admin and submit whether you accept or decline.
        </p>
    </div>

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

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-base font-bold text-slate-900">
                Task List
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Tasks assigned to your tanod account.
            </p>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($responses as $response)
                @php
                    $task = $response->task;
                @endphp

                <div class="p-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="max-w-3xl">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-bold text-slate-900">
                                    {{ $task?->title ?? 'Untitled Task' }}
                                </h3>

                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold
                                    @class([
                                        'border-slate-200 bg-slate-100 text-slate-700' => $response->response_status === 'pending',
                                        'border-green-200 bg-green-50 text-green-700' => $response->response_status === 'accepted',
                                        'border-red-200 bg-red-50 text-red-700' => $response->response_status === 'declined',
                                    ])
                                ">
                                    {{ ucfirst($response->response_status) }}
                                </span>

                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold
                                    @class([
                                        'border-green-200 bg-green-50 text-green-700' => $task?->status === 'open',
                                        'border-slate-200 bg-slate-100 text-slate-700' => $task?->status === 'closed',
                                        'border-red-200 bg-red-50 text-red-700' => $task?->status === 'cancelled',
                                    ])
                                ">
                                    Task {{ ucfirst($task?->status ?? 'unknown') }}
                                </span>
                            </div>

                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                {{ $task?->description ?: 'No description provided.' }}
                            </p>

                            <div class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Location
                                    </p>

                                    <p class="mt-1 font-semibold text-slate-800">
                                        {{ $task?->location ?: 'No location' }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Schedule
                                    </p>

                                    <p class="mt-1 font-semibold text-slate-800">
                                        {{ $task?->task_datetime ? $task->task_datetime->format('M d, Y h:i A') : 'No schedule' }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Response Due
                                    </p>

                                    <p class="mt-1 font-semibold text-slate-800">
                                        {{ $task?->due_at ? $task->due_at->format('M d, Y h:i A') : 'No due date' }}
                                    </p>
                                </div>
                            </div>

                            @if ($response->responded_at)
                                <p class="mt-4 text-sm text-slate-500">
                                    Responded at: {{ $response->responded_at->format('M d, Y h:i A') }}
                                </p>
                            @endif

                            @if ($response->response_note)
                                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Your Note
                                    </p>

                                    <p class="mt-1 text-sm text-slate-700">
                                        {{ $response->response_note }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        @if ($task && $task->status === 'open')
                            <div class="w-full rounded-xl border border-slate-200 bg-slate-50 p-4 lg:w-80">
                                <form method="POST" action="{{ route('tanod.tanod-tasks.respond', $response) }}" class="space-y-4">
                                    @csrf
                                    @method('PATCH')

                                    <div>
                                        <label for="response_note_{{ $response->id }}" class="mb-2 block text-sm font-semibold text-slate-700">
                                            Note / Reason
                                        </label>

                                        <textarea id="response_note_{{ $response->id }}"
                                                  name="response_note"
                                                  rows="3"
                                                  placeholder="Optional note..."
                                                  class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">{{ old('response_note', $response->response_note) }}</textarea>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="submit"
                                                name="response_status"
                                                value="accepted"
                                                class="rounded-xl bg-green-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-800">
                                            Accept
                                        </button>

                                        <button type="submit"
                                                name="response_status"
                                                value="declined"
                                                class="rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800">
                                            Decline
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @else
                            <div class="w-full rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm font-medium text-slate-500 lg:w-80">
                                This task is no longer open for response.
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-6 py-14 text-center">
                    <h3 class="text-base font-bold text-slate-900">
                        No tasks assigned
                    </h3>

                    <p class="mt-2 text-sm text-slate-500">
                        New tanod tasks will appear here once admin creates one.
                    </p>
                </div>
            @endforelse
        </div>

        @if ($responses->hasPages())
            <div class="border-t border-slate-200 px-6 py-4">
                {{ $responses->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
@extends('layouts.admin')

@section('title', 'Tanod Tasks | DaoSystem')

@section('content')
<div class="space-y-6">
    @php
    $tanodRosterUrl = Route::has('admin.tanods.index')
        ? route('admin.tanods.index')
        : (Route::has('admin.tanod-roster.index')
            ? route('admin.tanod-roster.index')
            : url('/admin/tanods'));
@endphp

<div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:flex-row lg:items-center lg:justify-between">
    <div>
        <a href="{{ $tanodRosterUrl }}"
           class="inline-flex items-center text-sm font-semibold text-blue-700 hover:text-blue-900">
            ← Back to Tanod Roster
        </a>

        <p class="mt-4 text-sm font-medium uppercase tracking-wide text-blue-700">
            Tanod Roster
        </p>

        <h1 class="mt-1 text-2xl font-bold text-slate-900">
            Tanod Tasks
        </h1>

        <p class="mt-2 max-w-3xl text-sm text-slate-600">
            Create tasks for tanods and monitor who accepted, declined, or has not responded yet.
        </p>
    </div>

    <a href="{{ route('admin.tanod-tasks.create') }}"
       class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
        + Create Task
    </a>
</div>

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-base font-bold text-slate-900">
                Task List
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Latest tanod tasks assigned to active tanods.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Task
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Priority
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Schedule
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            Responses
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
                    @forelse ($tasks as $task)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-4 align-top">
                                <p class="font-semibold text-slate-900">
                                    {{ $task->title }}
                                </p>

                                <p class="mt-1 line-clamp-2 text-sm text-slate-500">
                                    {{ $task->description ?: 'No description provided.' }}
                                </p>

                                @if ($task->location)
                                    <p class="mt-2 text-xs font-semibold text-slate-500">
                                        Location: {{ $task->location }}
                                    </p>
                                @endif
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold
                                    @class([
                                        'border-slate-200 bg-slate-100 text-slate-700' => $task->priority === 'low',
                                        'border-blue-200 bg-blue-50 text-blue-700' => $task->priority === 'normal',
                                        'border-orange-200 bg-orange-50 text-orange-700' => $task->priority === 'high',
                                        'border-red-200 bg-red-50 text-red-700' => $task->priority === 'urgent',
                                    ])
                                ">
                                    {{ ucfirst($task->priority) }}
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top text-sm text-slate-600">
                                <p>
                                    {{ $task->task_datetime ? $task->task_datetime->format('M d, Y h:i A') : 'No schedule' }}
                                </p>

                                @if ($task->due_at)
                                    <p class="mt-1 text-xs text-slate-500">
                                        Due: {{ $task->due_at->format('M d, Y h:i A') }}
                                    </p>
                                @endif
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="grid gap-1 text-sm">
                                    <span class="font-semibold text-green-700">
                                        Accepted: {{ $task->accepted_responses_count }}
                                    </span>

                                    <span class="font-semibold text-red-700">
                                        Declined: {{ $task->declined_responses_count }}
                                    </span>

                                    <span class="font-semibold text-slate-600">
                                        Pending: {{ $task->pending_responses_count }}
                                    </span>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold
                                    @class([
                                        'border-green-200 bg-green-50 text-green-700' => $task->status === 'open',
                                        'border-slate-200 bg-slate-100 text-slate-700' => $task->status === 'closed',
                                        'border-red-200 bg-red-50 text-red-700' => $task->status === 'cancelled',
                                    ])
                                ">
                                    {{ ucfirst($task->status) }}
                                </span>
                            </td>

                            <td class="px-5 py-4 text-right align-top">
                                <div class="flex items-center justify-end gap-3">
    <a href="{{ route('admin.tanod-tasks.show', $task) }}"
       class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100"
       title="View task">
        👁
    </a>

    <form method="POST"
      action="{{ route('admin.tanod-tasks.destroy', $task) }}">
        @csrf
        @method('DELETE')

        <button type="submit"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 hover:bg-red-100"
                title="Delete task">
            🗑
        </button>
    </form>
</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-14 text-center">
                                <h3 class="text-base font-bold text-slate-900">
                                    No tanod tasks yet
                                </h3>

                                <p class="mt-2 text-sm text-slate-500">
                                    Create a task so tanods can accept or decline it.
                                </p>

                                <a href="{{ route('admin.tanod-tasks.create') }}"
                                   class="mt-5 inline-flex rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-800">
                                    + Create First Task
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tasks->hasPages())
            <div class="border-t border-slate-200 px-6 py-4">
                {{ $tasks->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
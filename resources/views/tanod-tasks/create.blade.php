@extends('layouts.admin')

@section('title', 'Create Tanod Task | DaoSystem')

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <a href="{{ route('admin.tanod-tasks.index') }}"
           class="inline-flex text-sm font-semibold text-blue-700 hover:text-blue-900">
            ← Back to Tanod Tasks
        </a>

        <h1 class="mt-4 text-2xl font-bold text-slate-900">
            Create Tanod Task
        </h1>

        <p class="mt-2 text-sm text-slate-600">
            This task will be assigned to all active tanods. Each tanod can accept or decline it.
        </p>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">
            Please check the form and try again.
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('admin.tanod-tasks.store') }}" class="space-y-6 p-6">
            @csrf

            <div>
                <label for="title" class="mb-2 block text-sm font-semibold text-slate-700">
                    Task Title
                </label>

                <input id="title"
                       type="text"
                       name="title"
                       value="{{ old('title') }}"
                       required
                       placeholder="Example: Night patrol at Barangay Poblacion"
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                @error('title')
                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="mb-2 block text-sm font-semibold text-slate-700">
                    Description
                </label>

                <textarea id="description"
                          name="description"
                          rows="4"
                          placeholder="Explain the task details..."
                          class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">{{ old('description') }}</textarea>

                @error('description')
                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="location" class="mb-2 block text-sm font-semibold text-slate-700">
                        Location
                    </label>

                    <input id="location"
                           type="text"
                           name="location"
                           value="{{ old('location') }}"
                           placeholder="Barangay, street, or landmark"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                    @error('location')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="priority" class="mb-2 block text-sm font-semibold text-slate-700">
                        Priority
                    </label>

                    <select id="priority"
                            name="priority"
                            required
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="low" @selected(old('priority') === 'low')>Low</option>
                        <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option>
                        <option value="high" @selected(old('priority') === 'high')>High</option>
                        <option value="urgent" @selected(old('priority') === 'urgent')>Urgent</option>
                    </select>

                    @error('priority')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="task_datetime" class="mb-2 block text-sm font-semibold text-slate-700">
                        Task Date / Time
                    </label>

                    <input id="task_datetime"
                           type="datetime-local"
                           name="task_datetime"
                           value="{{ old('task_datetime') }}"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                    @error('task_datetime')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="due_at" class="mb-2 block text-sm font-semibold text-slate-700">
                        Response Due Date / Time
                    </label>

                    <input id="due_at"
                           type="datetime-local"
                           name="due_at"
                           value="{{ old('due_at') }}"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                    @error('due_at')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700">
                Active tanods who will receive this task: {{ $tanods->count() }}
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.tanod-tasks.index') }}"
                   class="inline-flex rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </a>

                <button type="submit"
                        class="inline-flex rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                    Create Task
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
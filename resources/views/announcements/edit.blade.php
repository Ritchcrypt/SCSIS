@extends('layouts.admin')

@section('title', 'Edit Published Announcement | TabangNow')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-blue-700">Announcements · Admin</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Edit Published Announcement</h1>
            <p class="mt-2 text-sm text-slate-600">Correct a published announcement without sending a second notification.</p>
        </div>
        <a href="{{ route('admin.announcements.index') }}"
           class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
            Back to Announcements
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
            <p class="font-bold">Please correct the fields below.</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-900">
        <p class="font-bold">Existing recipients stay the same.</p>
        <p>The original audience, target barangays, calamity mode, and publication date will not change. Updating this announcement does not send another alert or reset notification read states. To notify a different audience or issue a new emergency alert, publish a new announcement.</p>
    </div>

    <form method="POST"
          action="{{ route('admin.announcements.update', $announcement) }}"
          class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PATCH')

        <div>
            <label for="edit_announcement_title" class="mb-2 block text-sm font-bold text-slate-700">Title *</label>
            <input id="edit_announcement_title" name="title" type="text" maxlength="255" required
                   value="{{ old('title', $announcement->title) }}"
                   class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            @error('title')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="edit_announcement_content" class="mb-2 block text-sm font-bold text-slate-700">Message *</label>
            <textarea id="edit_announcement_content" name="content" rows="8" maxlength="5000" required
                      class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-100">{{ old('content', $announcement->content) }}</textarea>
            @error('content')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="edit_announcement_category" class="mb-2 block text-sm font-bold text-slate-700">Category *</label>
                <select id="edit_announcement_category" name="category"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    @foreach ($categories as $value => $label)
                        @continue($value === 'calamity' && $announcement->category !== 'calamity')
                        <option value="{{ $value }}" @selected(old('category', $announcement->category) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('category')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="edit_announcement_priority" class="mb-2 block text-sm font-bold text-slate-700">Priority *</label>
                <select id="edit_announcement_priority" name="priority"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    @foreach ($priorities as $value => $label)
                        <option value="{{ $value }}" @selected(old('priority', $announcement->priority) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('priority')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
            <p><span class="font-bold">Audience:</span> {{ $announcement->display_audience }}</p>
            <p class="mt-2"><span class="font-bold">Target barangays:</span>
                {{ $announcement->targetBarangays->pluck('barangay_name')->join(', ') ?: 'Legacy / Unscoped' }}
            </p>
            <p class="mt-2"><span class="font-bold">Calamity mode:</span> {{ $announcement->activate_calamity_mode ? 'On' : 'Off' }}</p>
            <p class="mt-2"><span class="font-bold">Originally published:</span> {{ $announcement->published_at?->format('M d, Y g:i A') ?? 'Not recorded' }}</p>
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">
            <a href="{{ route('admin.announcements.index') }}"
               class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-5 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
            <button type="submit"
                    class="inline-flex min-h-11 items-center rounded-xl bg-blue-900 px-5 py-2 text-sm font-bold text-white hover:bg-blue-800">
                Save Changes
            </button>
        </div>
    </form>
</div>
@endsection

@extends('layouts.admin')

@section('title', 'Announcements | DaoSystem')

@section('content')
@php
    $authUser = auth()->user();
    $role = strtolower(trim($authUser?->role ?? ''));

    /*
    |--------------------------------------------------------------------------
    | Announcement Permission Rule
    |--------------------------------------------------------------------------
    | Admin, Official, and Dao can post/delete announcements.
    | Tanod/Resident can only view announcements.
    */
    $canManageAnnouncements = in_array($role, ['admin', 'official', 'dao'], true);

    $categoryStyles = [
        'advisory' => 'bg-blue-100 text-blue-700 border-blue-200',
        'emergency' => 'bg-red-100 text-red-700 border-red-200',
        'calamity' => 'bg-red-100 text-red-700 border-red-200',
        'community' => 'bg-green-100 text-green-700 border-green-200',
        'health' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'general' => 'bg-slate-100 text-slate-700 border-slate-200',
    ];

    $priorityStyles = [
        'normal' => 'bg-slate-100 text-slate-700 border-slate-200',
        'important' => 'bg-blue-100 text-blue-700 border-blue-200',
        'urgent' => 'bg-orange-100 text-orange-700 border-orange-200',
        'emergency' => 'bg-red-100 text-red-700 border-red-200',
    ];

    $cardStyles = [
        'normal' => 'border-slate-200 bg-white',
        'important' => 'border-blue-200 bg-blue-50/30',
        'urgent' => 'border-orange-200 bg-orange-50/30',
        'emergency' => 'border-red-200 bg-red-50/40',
    ];

    $storeRouteName = match ($role) {
        'admin' => \Illuminate\Support\Facades\Route::has('admin.announcements.store')
            ? 'admin.announcements.store'
            : null,
        'official', 'dao' => \Illuminate\Support\Facades\Route::has('official.announcements.store')
            ? 'official.announcements.store'
            : null,
        default => null,
    };

    $storeAction = $storeRouteName ? route($storeRouteName) : '#';
@endphp

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                Community Announcements
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Advisories and emergency notifications
            </p>
        </div>

        @if ($canManageAnnouncements && $storeRouteName)
            <button type="button"
                    onclick="openAnnouncementModal()"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-950 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-900">
                + Post Announcement
            </button>
        @endif
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($canManageAnnouncements && $errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-bold">Please fix the following errors:</p>

            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Announcements List --}}
    <div class="space-y-4">
        @forelse ($announcements as $announcement)
            @php
                $categoryClass = $categoryStyles[$announcement->category] ?? $categoryStyles['general'];
                $priorityClass = $priorityStyles[$announcement->priority] ?? $priorityStyles['normal'];

                $cardClass = $announcement->activate_calamity_mode
                    ? 'border-red-300 bg-red-50/60'
                    : ($cardStyles[$announcement->priority] ?? $cardStyles['normal']);

                $iconClass = match ($announcement->category) {
                    'emergency', 'calamity' => 'bg-red-100 text-red-700',
                    'advisory' => 'bg-blue-100 text-blue-700',
                    'community' => 'bg-green-100 text-green-700',
                    'health' => 'bg-emerald-100 text-emerald-700',
                    default => 'bg-slate-100 text-slate-700',
                };

                $publishedDate = $announcement->published_at
                    ? $announcement->published_at->format('M d, Y h:i A')
                    : $announcement->created_at?->format('M d, Y h:i A');

                $posterName = $announcement->poster?->name ?: 'System';

                $showInWeatherFeed = (bool) ($announcement->show_in_weather_feed ?? false);

                $destroyRouteName = match ($role) {
                    'admin' => \Illuminate\Support\Facades\Route::has('admin.announcements.destroy')
                        ? 'admin.announcements.destroy'
                        : null,
                    'official', 'dao' => \Illuminate\Support\Facades\Route::has('official.announcements.destroy')
                        ? 'official.announcements.destroy'
                        : null,
                    default => null,
                };
            @endphp

            <div class="rounded-2xl border p-6 shadow-sm {{ $cardClass }}">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $iconClass }}">
                            @if (in_array($announcement->category, ['emergency', 'calamity'], true))
                                🚨
                            @elseif ($announcement->category === 'advisory')
                                📢
                            @elseif ($announcement->category === 'health')
                                🩺
                            @else
                                📣
                            @endif
                        </div>

                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-bold text-slate-900">
                                    {{ $announcement->title }}
                                </h2>

                                <span class="rounded-full border px-3 py-1 text-xs font-bold {{ $categoryClass }}">
                                    {{ $announcement->display_category }}
                                </span>

                                <span class="rounded-full border px-3 py-1 text-xs font-bold {{ $priorityClass }}">
                                    {{ $announcement->display_priority }}
                                </span>

                                @if ($showInWeatherFeed)
                                    <span class="rounded-full border border-blue-200 bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">
                                        Weather Feed
                                    </span>
                                @endif

                                @if (! $announcement->is_active)
                                    <span class="rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                                        Inactive
                                    </span>
                                @endif

                                @if ($announcement->activate_calamity_mode)
                                    <span class="rounded-full border border-red-200 bg-red-100 px-3 py-1 text-xs font-bold text-red-700">
                                        Calamity Mode
                                    </span>
                                @endif
                            </div>

                            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">
                                {{ $announcement->content }}
                            </p>

                            <div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                <span>By {{ $posterName }}</span>
                                <span>•</span>
                                <span>{{ $publishedDate }}</span>
                                <span>•</span>
                                <span>{{ $announcement->display_audience }}</span>
                            </div>
                        </div>
                    </div>

                    @if ($canManageAnnouncements && $destroyRouteName)
                        <div class="flex shrink-0 items-center gap-3">
                            <form method="POST"
                                  action="{{ route($destroyRouteName, $announcement) }}">
                                @csrf
                                @method('DELETE')

                                <button type="submit"
                                        title="Delete announcement"
                                        aria-label="Delete announcement"
                                        style="
                                            width: 40px;
                                            height: 40px;
                                            display: inline-flex;
                                            align-items: center;
                                            justify-content: center;
                                            vertical-align: middle;
                                            border: 1px solid #fca5a5;
                                            border-radius: 10px;
                                            background-color: #fff7f7;
                                            font-size: 17px;
                                            line-height: 1;
                                            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
                                            cursor: pointer;
                                        "
                                        onmouseover="
                                            this.style.backgroundColor='#fff1f1';
                                            this.style.borderColor='#fb923c';
                                        "
                                        onmouseout="
                                            this.style.backgroundColor='#fff7f7';
                                            this.style.borderColor='#fca5a5';
                                        ">
                                    <span style="display:block; line-height:1;">🗑️</span>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-14 text-center shadow-sm">
                <h3 class="text-base font-bold text-slate-900">
                    No announcements yet
                </h3>

                <p class="mt-2 text-sm text-slate-500">
                    {{ $canManageAnnouncements
                        ? 'Post the first community announcement, advisory, or emergency notice.'
                        : 'No community announcements, advisories, or emergency notices are available yet.' }}
                </p>

                @if ($canManageAnnouncements && $storeRouteName)
                    <button type="button"
                            onclick="openAnnouncementModal()"
                            class="mt-5 inline-flex items-center justify-center rounded-xl bg-blue-950 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-900">
                        + Post Announcement
                    </button>
                @endif
            </div>
        @endforelse
    </div>

    @if ($announcements->hasPages())
        <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
            {{ $announcements->links() }}
        </div>
    @endif
</div>

@if ($canManageAnnouncements && $storeRouteName)
    {{-- Post Announcement Modal --}}
    <div id="announcementModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
        <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-900">
                    Post Announcement
                </h2>

                <button type="button"
                        onclick="closeAnnouncementModal()"
                        class="text-2xl leading-none text-slate-500 hover:text-slate-900">
                    &times;
                </button>
            </div>

            <form method="POST" action="{{ $storeAction }}" class="space-y-5">
                @csrf

                <div>
                    <label for="title" class="mb-2 block text-sm font-semibold text-slate-700">
                        Title *
                    </label>

                    <input id="title"
                           type="text"
                           name="title"
                           value="{{ old('title') }}"
                           required
                           placeholder="Announcement title"
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">

                    @error('title')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="content" class="mb-2 block text-sm font-semibold text-slate-700">
                        Content *
                    </label>

                    <textarea id="content"
                              name="content"
                              rows="5"
                              required
                              placeholder="Full announcement text..."
                              class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">{{ old('content') }}</textarea>

                    @error('content')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="category" class="mb-2 block text-sm font-semibold text-slate-700">
                            Category
                        </label>

                        <select id="category"
                                name="category"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}" @selected(old('category', 'general') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="priority" class="mb-2 block text-sm font-semibold text-slate-700">
                            Priority
                        </label>

                        <select id="priority"
                                name="priority"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                            @foreach ($priorities as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="audience" class="mb-2 block text-sm font-semibold text-slate-700">
                            Audience
                        </label>

                        <select id="audience"
                                name="audience"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                            @foreach ($audiences as $value => $label)
                                <option value="{{ $value }}" @selected(old('audience', 'everyone') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-red-200 bg-red-50 p-4">
                    <div>
                        <p class="text-sm font-bold text-red-700">
                            🚨 Activate Calamity Mode
                        </p>

                        <p class="mt-1 text-sm text-red-600">
                            Triggers system-wide emergency alert and automatically shows this announcement in the Weather & Disaster Feed.
                        </p>
                    </div>

                    <input type="checkbox"
                           name="activate_calamity_mode"
                           value="1"
                           class="h-5 w-5 rounded border-red-300 text-red-600 focus:ring-red-500">
                </label>

                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <div>
                        <p class="text-sm font-bold text-blue-900">
                            Show in Weather & Disaster Feed
                        </p>

                        <p class="mt-1 text-sm leading-5 text-blue-700">
                            Use this for PAGASA, MDRRMO, flood, typhoon, evacuation, weather, emergency, or disaster advisories.
                            Normal announcements should stay unchecked.
                        </p>
                    </div>

                    <input type="checkbox"
                           name="show_in_weather_feed"
                           value="1"
                           {{ old('show_in_weather_feed') ? 'checked' : '' }}
                           class="h-5 w-5 rounded border-blue-300 text-blue-600 focus:ring-blue-500">
                </label>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                    <button type="button"
                            onclick="closeAnnouncementModal()"
                            class="rounded-xl border border-slate-300 px-5 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>

                    <button type="submit"
                            class="rounded-xl bg-blue-950 px-5 py-2 text-sm font-bold text-white hover:bg-blue-900">
                        Post Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAnnouncementModal() {
            const modal = document.getElementById('announcementModal');

            if (!modal) {
                return;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeAnnouncementModal() {
            const modal = document.getElementById('announcementModal');

            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>
@endif
@endsection
@extends('layouts.admin')

@section('title', 'Emergency Hotlines | TabangNow')

@section('content')
@php
    $authUser = auth()->user();
    $role = strtolower((string) ($authUser?->role ?? ''));

    $storeRoute = match ($role) {
        'admin' => Route::has('admin.emergency-mode.store') ? 'admin.emergency-mode.store' : null,
        'official', 'dao' => Route::has('official.emergency-mode.store') ? 'official.emergency-mode.store' : null,
        default => null,
    };

    $destroyRoute = match ($role) {
        'admin' => Route::has('admin.emergency-mode.destroy') ? 'admin.emergency-mode.destroy' : null,
        'official', 'dao' => Route::has('official.emergency-mode.destroy') ? 'official.emergency-mode.destroy' : null,
        default => null,
    };

    $colorClasses = [
        'blue' => [
            'dot' => 'bg-blue-600',
            'border' => 'border-blue-200',
            'hover' => 'hover:border-blue-400 hover:shadow-blue-100',
        ],
        'red' => [
            'dot' => 'bg-red-600',
            'border' => 'border-red-200',
            'hover' => 'hover:border-red-400 hover:shadow-red-100',
        ],
        'orange' => [
            'dot' => 'bg-orange-500',
            'border' => 'border-orange-300',
            'hover' => 'hover:border-orange-400 hover:shadow-orange-100',
        ],
        'green' => [
            'dot' => 'bg-green-600',
            'border' => 'border-green-200',
            'hover' => 'hover:border-green-400 hover:shadow-green-100',
        ],
        'purple' => [
            'dot' => 'bg-purple-600',
            'border' => 'border-purple-200',
            'hover' => 'hover:border-purple-400 hover:shadow-purple-100',
        ],
        'slate' => [
            'dot' => 'bg-slate-600',
            'border' => 'border-slate-200',
            'hover' => 'hover:border-slate-400 hover:shadow-slate-100',
        ],
    ];
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-4">
                <div class="text-3xl">
                    📞
                </div>

                <div>
                    <h1 class="text-2xl font-black text-slate-900">
                        Emergency Hotlines
                    </h1>

                    <p class="mt-1 text-sm text-slate-600">
                        Emergency contact numbers for immediate reference.
                    </p>
                </div>
            </div>

            @if ($canManageHotlines && $storeRoute)
                <button type="button"
                        onclick="document.getElementById('addHotlinePanel').classList.toggle('hidden')"
                        class="inline-flex w-fit rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
                    + Add Hotline
                </button>
            @endif
        </div>

        @if (session('success'))
            <div class="mt-5 rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-semibold text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                <p class="font-bold">Please fix the following:</p>

                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($canManageHotlines && $storeRoute)
            <div id="addHotlinePanel"
                 class="mt-6 hidden rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <form method="POST"
                      action="{{ route($storeRoute) }}"
                      class="grid gap-4 lg:grid-cols-[1fr_220px_180px_auto] lg:items-end">
                    @csrf

                    <div>
                        <label for="agency_name" class="mb-2 block text-sm font-bold text-slate-700">
                            Agency / Office Name
                        </label>

                        <input id="agency_name"
                               name="agency_name"
                               type="text"
                               required
                               value="{{ old('agency_name') }}"
                               placeholder="Example: Rural Health Unit"
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>

                    <div>
                        <label for="hotline_number" class="mb-2 block text-sm font-bold text-slate-700">
                            Hotline Number
                        </label>

                        <input id="hotline_number"
                               name="hotline_number"
                               type="text"
                               required
                               value="{{ old('hotline_number') }}"
                               placeholder="Example: 166"
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>

                    <div>
                        <label for="color" class="mb-2 block text-sm font-bold text-slate-700">
                            Card Color
                        </label>

                        <select id="color"
                                name="color"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                            @foreach ($colors as $colorValue => $colorLabel)
                                <option value="{{ $colorValue }}" @selected(old('color') === $colorValue)>
                                    {{ $colorLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit"
                            class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">
                        Save
                    </button>
                </form>
            </div>
        @endif

        <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($hotlines as $hotline)
                @php
                    $style = $colorClasses[$hotline->color] ?? $colorClasses['blue'];
                @endphp

                <div class="relative rounded-2xl border {{ $style['border'] }} bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg {{ $style['hover'] }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-4">
                                <span class="h-4 w-4 rounded-full {{ $style['dot'] }}"></span>

                                <h2 class="text-xl font-black text-slate-900">
                                    {{ $hotline->agency_name }}
                                </h2>
                            </div>

                            <p class="mt-7 text-3xl font-black tracking-wide text-blue-950">
                                {{ $hotline->hotline_number }}
                            </p>
                        </div>

                        @if ($canManageHotlines && $destroyRoute)
                            <form method="POST"
      action="{{ route($destroyRoute, $hotline) }}">
                                @csrf
                                @method('DELETE')

                                <button type="submit"
                                        title="Remove hotline"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-sm text-red-600 hover:bg-red-100">
                                    🗑
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-14 text-center md:col-span-2 xl:col-span-3">
                    <div class="text-4xl">📞</div>

                    <h2 class="mt-4 text-lg font-black text-slate-900">
                        No emergency hotlines available
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Emergency hotlines added by admin or official will appear here.
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
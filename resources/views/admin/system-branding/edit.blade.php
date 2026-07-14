@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">System Branding</h1>
        <p class="mt-1 text-sm text-slate-500">
            Update the logo, system name, and subtitle shown in the upper-left sidebar brand area.
        </p>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-bold">Please fix the following:</p>

            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6 rounded-2xl border border-blue-900 bg-blue-950 p-5 text-white">
            <p class="mb-4 text-sm font-semibold text-blue-200">Current sidebar preview</p>

            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-xl bg-blue-600">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}"
                             alt="{{ $setting->system_name }} Logo"
                             class="h-full w-full object-cover">
                    @else
                        <span class="text-lg font-bold">🛡</span>
                    @endif
                </div>

                <div class="min-w-0">
                    <h2 class="truncate text-lg font-bold leading-tight">
                        {{ $setting->system_name }}
                    </h2>
                    <p class="truncate text-sm text-blue-200">
                        {{ $setting->system_subtitle }}
                    </p>
                </div>
            </div>
        </div>

        <form method="POST"
              action="{{ route('admin.system-branding.update') }}"
              enctype="multipart/form-data"
              class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="system_name" class="block text-sm font-bold text-slate-700">
                    System Name
                </label>

                <input id="system_name"
                       type="text"
                       name="system_name"
                       value="{{ old('system_name', $setting->system_name) }}"
                       class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                       required>
            </div>

            <div>
                <label for="system_subtitle" class="block text-sm font-bold text-slate-700">
                    System Subtitle
                </label>

                <input id="system_subtitle"
                       type="text"
                       name="system_subtitle"
                       value="{{ old('system_subtitle', $setting->system_subtitle) }}"
                       class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                       required>
            </div>

            <div>
                <label for="system_logo" class="block text-sm font-bold text-slate-700">
                    System Logo
                </label>

                <input id="system_logo"
                       type="file"
                       name="system_logo"
                       accept="image/jpeg,image/png,image/webp"
                       class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-bold file:text-blue-700 hover:file:bg-blue-100">

                <p class="mt-2 text-xs text-slate-500">
                    Accepted formats: JPG, JPEG, PNG, WEBP. Maximum upload size: 50MB. Display copy is optimized automatically.
                </p>
            </div>

            @if ($logoUrl)
                <label class="flex items-center gap-3 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    <input type="checkbox"
                           name="remove_logo"
                           value="1"
                           class="rounded border-red-300 text-red-600 focus:ring-red-200">
                    Remove current logo and use the default shield icon.
                </label>
            @endif

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-5">
                <a href="{{ route('admin.dashboard') }}"
                   class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </a>

                <button type="submit"
                        class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
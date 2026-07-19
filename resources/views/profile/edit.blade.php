@extends('layouts.admin')

@section('title', 'My Profile | TabangNow')

@section('content')
@php
    $profilePhotoPath = $userRecord->profile_photo_path ?? null;

    $profilePhotoUrl = $profilePhotoPath && Route::has('users.profile-photo')
        ? route('users.profile-photo', $userRecord) . '?v=' . optional($userRecord->updated_at)->timestamp
        : null;

    $profileInitial = strtoupper(mb_substr($userRecord->name ?? 'U', 0, 1));
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <a href="{{ route('dashboard') }}"
           class="inline-flex text-sm font-semibold text-blue-700 hover:text-blue-900">
            ← Back to Dashboard
        </a>

        <h1 class="mt-4 text-2xl font-bold text-slate-900">
            My Profile
        </h1>

        <p class="mt-2 text-sm text-slate-600">
            Update your account information.
        </p>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">
            Please check the form and try again.
        </div>
    @endif

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="POST"
              action="{{ route('profile.update') }}"
              enctype="multipart/form-data"
              class="space-y-6 p-6">
            @csrf
            @method('PATCH')

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                    <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        @if ($profilePhotoUrl)
                            <img src="{{ $profilePhotoUrl }}"
                                 alt="{{ $userRecord->name ?? 'User' }} profile photo"
                                 class="h-full w-full object-cover"
                                 onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">

                            <span class="hidden text-3xl font-bold text-blue-700">
                                {{ $profileInitial }}
                            </span>
                        @else
                            <span class="text-3xl font-bold text-blue-700">
                                {{ $profileInitial }}
                            </span>
                        @endif
                    </div>

                    <div class="flex-1">
                        <label for="profile_photo" class="mb-2 block text-sm font-semibold text-slate-700">
                            Profile Picture
                        </label>

                        <input id="profile_photo"
                               type="file"
                               name="profile_photo"
                               accept="image/jpeg,image/png,image/webp"
                               class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-bold file:text-blue-700 hover:file:bg-blue-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                        <p class="mt-2 text-xs text-slate-500">
                            Accepted formats: JPG, PNG, or WEBP. Maximum size: 50 MB.
                            @if ($profilePhotoUrl)
                                Uploading a new image will replace the current profile picture.
                            @endif
                        </p>

                        @error('profile_photo')
                            <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">
                        Full Name
                    </label>

                    <input id="name"
                           type="text"
                           name="name"
                           value="{{ old('name', $userRecord->name ?? '') }}"
                           required
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                    @error('name')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">
                        Email
                    </label>

                    <input id="email"
                           type="email"
                           name="email"
                           value="{{ old('email', $userRecord->email ?? '') }}"
                           required
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                    @error('email')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="contact_number" class="mb-2 block text-sm font-semibold text-slate-700">
                        Contact Number
                    </label>

                    <input id="contact_number"
                           type="text"
                           name="contact_number"
                           value="{{ old('contact_number', $userRecord->contact_number ?? '') }}"
                           placeholder="Example: 09123456789"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                    @error('contact_number')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="address" class="mb-2 block text-sm font-semibold text-slate-700">
                        Address
                    </label>

                    <input id="address"
                           type="text"
                           name="address"
                           value="{{ old('address', $userRecord->address ?? '') }}"
                           placeholder="Enter complete address"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                    @error('address')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">
                        Role
                    </label>

                    <input type="text"
                           value="{{ ucfirst((string) $userRecord->role) }}"
                           disabled
                           class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm text-slate-600 shadow-sm">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">
                        Status
                    </label>

                    <input type="text"
                           value="{{ (bool) ($userRecord->is_active ?? true) ? 'Active' : 'Inactive' }}"
                           disabled
                           class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm text-slate-600 shadow-sm">
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('dashboard') }}"
                   class="inline-flex rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </a>

                <button type="submit"
                        class="inline-flex rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
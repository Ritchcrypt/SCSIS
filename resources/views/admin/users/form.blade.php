@extends('layouts.admin')

@section('title', ($userRecord ? 'Edit User' : 'Add User') . ' | TabangNow')

@section('content')
@php
    $isEdit = (bool) $userRecord;
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <a href="{{ route('admin.users.index') }}"
           class="inline-flex text-sm font-semibold text-blue-700 hover:text-blue-900">
            ← Back to User Management
        </a>

        <h1 class="mt-4 text-2xl font-bold text-slate-900">
            {{ $isEdit ? 'Edit User' : 'Add User' }}
        </h1>

        <p class="mt-2 text-sm text-slate-600">
            {{ $isEdit ? 'Update account information and access level.' : 'Create an account for admin, official, tanod, or resident users.' }}
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

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @if (session('temporary_password'))
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm font-medium text-blue-800">
            Temporary password for <strong>{{ session('temporary_password_user') }}</strong>:
            <span class="font-mono font-bold">{{ session('temporary_password') }}</span>
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="POST"
              action="{{ $isEdit ? route('admin.users.update', $userRecord) : route('admin.users.store') }}"
              enctype="multipart/form-data"
              class="space-y-6 p-6">
            @csrf

            @if ($isEdit)
                @method('PATCH')
            @endif

            @php
                $profilePhotoPath = $userRecord->profile_photo_path ?? null;
                $profilePhotoUrl = $profilePhotoPath && $userRecord && Route::has('users.profile-photo')
                    ? route('users.profile-photo', $userRecord) . '?v=' . optional($userRecord->updated_at)->timestamp
                    : null;
                $profileInitial = strtoupper(mb_substr($userRecord->name ?? 'U', 0, 1));
            @endphp

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
                            @if ($isEdit && $profilePhotoUrl)
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
                    <label for="role" class="mb-2 block text-sm font-semibold text-slate-700">
                        Role
                    </label>

                    <select id="role"
                            name="role"
                            required
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}"
                                @selected(old('role', $userRecord->role ?? 'resident') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>

                    @error('role')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="is_active" class="mb-2 block text-sm font-semibold text-slate-700">
                        Status
                    </label>

                    <select id="is_active"
                            name="is_active"
                            required
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="1" @selected((string) old('is_active', $userRecord->is_active ?? '1') === '1')>
                            Active
                        </option>

                        <option value="0" @selected((string) old('is_active', $userRecord->is_active ?? '1') === '0')>
                            Inactive
                        </option>
                    </select>

                    @error('is_active')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            @if (! $isEdit)
                <div>
                    <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">
                        Temporary Password
                    </label>

                    <input id="password"
                           type="text"
                           name="password"
                           required
                           placeholder="Minimum 8 characters"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                    <p class="mt-2 text-xs text-slate-500">
                        Give this temporary password to the user after account creation.
                    </p>

                    @error('password')
                        <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.users.index') }}"
                   class="inline-flex rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </a>

                <button type="submit"
                        class="inline-flex rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                    {{ $isEdit ? 'Save Changes' : 'Create User' }}
                </button>
            </div>
        </form>

        @if ($isEdit)
            <div class="border-t border-slate-200 px-6 py-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <form method="POST"
                          action="{{ route('admin.users.reset-password', $userRecord) }}">
                        @csrf
                        @method('PATCH')

                        <button type="submit"
                                class="inline-flex w-full items-center justify-center rounded-xl border border-amber-300 bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-800 hover:bg-amber-100 sm:w-auto">
                            Reset Password
                        </button>
                    </form>

                    <form method="POST"
                          action="{{ route('admin.users.destroy', $userRecord) }}">
                        @csrf
                        @method('DELETE')

                        <button type="submit"
                                class="inline-flex w-full items-center justify-center rounded-xl border border-red-300 bg-red-50 px-5 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-100 sm:w-auto">
                            Permanent Delete
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
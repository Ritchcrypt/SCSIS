@extends('layouts.admin')

@section('title', ($userRecord ? 'Edit User' : 'Add User') . ' | TabangNow')

@section('content')
@php
    $isEdit = (bool) $userRecord;

    /*
    |--------------------------------------------------------------------------
    | Account activation state
    |--------------------------------------------------------------------------
    |
    | The status display is read-only. Account activation and deactivation
    | remain controlled exclusively by the administrator approval buttons.
    |
    */

    if ($isEdit) {
        $rawActiveState = $userRecord->is_active ?? null;

        if ($rawActiveState !== null) {
            $accountIsActive = filter_var(
                $rawActiveState,
                FILTER_VALIDATE_BOOLEAN
            );
        } else {
            $accountIsActive = strtolower(
                trim((string) ($userRecord->status ?? 'inactive'))
            ) === 'active';
        }
    } else {
        $accountIsActive = true;
    }
@endphp

<style>
    /*
    |--------------------------------------------------------------------------
    | Account status confirmation popover
    |--------------------------------------------------------------------------
    |
    | Uses the native HTML Popover API so the controls remain functional under
    | the production Content Security Policy without inline JavaScript.
    |
    */

    .tn-account-popover {
        position: fixed;
        inset: 50% auto auto 50%;
        width: min(calc(100% - 2rem), 460px);
        max-width: calc(100vw - 2rem);
        max-height: calc(100vh - 2rem);
        margin: 0;
        padding: 0;
        overflow: auto;
        border: 1px solid #cbd5e1;
        border-radius: 1.5rem;
        background: #ffffff;
        color: #0f172a;
        box-shadow: 0 28px 80px rgba(2, 6, 23, 0.38);
        transform: translate(-50%, -50%);
    }

    .tn-account-popover::backdrop {
        background: rgba(2, 6, 23, 0.72);
        backdrop-filter: blur(5px);
    }

    .tn-account-popover-content {
        display: flex;
        gap: 1rem;
        padding: 1.5rem;
    }

    .tn-account-popover-icon {
        display: flex;
        width: 3rem;
        height: 3rem;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        border-radius: 1rem;
        font-size: 1.3rem;
        font-weight: 800;
    }

    .tn-account-popover-icon--activate {
        border: 1px solid #a7f3d0;
        background: #ecfdf5;
        color: #047857;
    }

    .tn-account-popover-icon--deactivate {
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #dc2626;
    }

    .tn-account-popover-title {
        margin: 0;
        color: #0f172a;
        font-size: 1.15rem;
        font-weight: 800;
        line-height: 1.35;
    }

    .tn-account-popover-message {
        margin: 0.55rem 0 0;
        color: #475569;
        font-size: 0.9rem;
        line-height: 1.7;
    }

    .tn-account-popover-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        padding: 0 1.5rem 1.5rem;
    }

    .tn-account-popover-button {
        display: inline-flex;
        min-width: 6.5rem;
        align-items: center;
        justify-content: center;
        border-radius: 0.85rem;
        padding: 0.72rem 1.15rem;
        font-size: 0.85rem;
        font-weight: 800;
        transition:
            background-color 150ms ease,
            border-color 150ms ease,
            transform 150ms ease;
    }

    .tn-account-popover-button:active {
        transform: translateY(1px);
    }

    .tn-account-popover-button--cancel {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
    }

    .tn-account-popover-button--cancel:hover {
        background: #f8fafc;
    }

    .tn-account-popover-button--activate {
        border: 1px solid #10b981;
        background: #059669;
        color: #ffffff;
    }

    .tn-account-popover-button--activate:hover {
        background: #047857;
    }

    .tn-account-popover-button--deactivate {
        border: 1px solid #ef4444;
        background: #dc2626;
        color: #ffffff;
    }

    .tn-account-popover-button--deactivate:hover {
        background: #b91c1c;
    }

    html[data-theme='dark'] .tn-account-popover {
        border-color: #334155;
        background: #0f172a;
        color: #f8fafc;
    }

    html[data-theme='dark'] .tn-account-popover-title {
        color: #f8fafc;
    }

    html[data-theme='dark'] .tn-account-popover-message {
        color: #cbd5e1;
    }

    html[data-theme='dark'] .tn-account-popover-button--cancel {
        border-color: #475569;
        background: #0f172a;
        color: #f8fafc;
    }

    html[data-theme='dark'] .tn-account-popover-button--cancel:hover {
        background: #1e293b;
    }

    @media (prefers-color-scheme: dark) {
        html[data-theme='system'] .tn-account-popover,
        html:not([data-theme]) .tn-account-popover {
            border-color: #334155;
            background: #0f172a;
            color: #f8fafc;
        }

        html[data-theme='system'] .tn-account-popover-title,
        html:not([data-theme]) .tn-account-popover-title {
            color: #f8fafc;
        }

        html[data-theme='system'] .tn-account-popover-message,
        html:not([data-theme]) .tn-account-popover-message {
            color: #cbd5e1;
        }

        html[data-theme='system'] .tn-account-popover-button--cancel,
        html:not([data-theme]) .tn-account-popover-button--cancel {
            border-color: #475569;
            background: #0f172a;
            color: #f8fafc;
        }

        html[data-theme='system'] .tn-account-popover-button--cancel:hover,
        html:not([data-theme]) .tn-account-popover-button--cancel:hover {
            background: #1e293b;
        }
    }
</style>

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
            {{ $isEdit
                ? 'Update account information and access level.'
                : 'Create an account for admin, official, tanod, or resident users.'
            }}
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

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="POST"
              action="{{ $isEdit
                  ? route('admin.users.update', $userRecord)
                  : route('admin.users.store')
              }}"
              enctype="multipart/form-data"
              class="space-y-6 p-6">
            @csrf

            @if ($isEdit)
                @method('PATCH')
            @endif

            @php
                $profilePhotoPath = $userRecord->profile_photo_path ?? null;

                $profilePhotoUrl =
                    $profilePhotoPath &&
                    $userRecord &&
                    Route::has('users.profile-photo')
                        ? route('users.profile-photo', $userRecord) .
                            '?v=' .
                            optional($userRecord->updated_at)->timestamp
                        : null;

                $profileInitial = strtoupper(
                    mb_substr($userRecord->name ?? 'U', 0, 1)
                );
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
                        <label for="profile_photo"
                               class="mb-2 block text-sm font-semibold text-slate-700">
                            Profile Picture
                        </label>

                        <input id="profile_photo"
                               type="file"
                               name="profile_photo"
                               accept="image/jpeg,image/png,image/webp"
                               class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-bold file:text-blue-700 hover:file:bg-blue-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                        <p class="mt-2 text-xs text-slate-500">
                            Accepted formats: JPG, PNG, or WEBP. Maximum size: 5 MB.

                            @if ($isEdit && $profilePhotoUrl)
                                Uploading a new image will replace the current profile picture.
                            @endif
                        </p>

                        @error('profile_photo')
                            <p class="mt-2 text-sm font-medium text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="name"
                           class="mb-2 block text-sm font-semibold text-slate-700">
                        Full Name
                    </label>

                    <input id="name"
                           type="text"
                           name="name"
                           value="{{ old('name', $userRecord->name ?? '') }}"
                           required
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                    @error('name')
                        <p class="mt-2 text-sm font-medium text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label for="email"
                           class="mb-2 block text-sm font-semibold text-slate-700">
                        Email
                    </label>

                    <input id="email"
                           type="email"
                           name="email"
                           value="{{ old('email', $userRecord->email ?? '') }}"
                           required
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                    @error('email')
                        <p class="mt-2 text-sm font-medium text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="contact_number"
                           class="mb-2 block text-sm font-semibold text-slate-700">
                        Contact Number
                    </label>

                    <input id="contact_number"
                           type="text"
                           name="contact_number"
                           value="{{ old('contact_number', $userRecord->contact_number ?? '') }}"
                           placeholder="Example: 09123456789"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                    @error('contact_number')
                        <p class="mt-2 text-sm font-medium text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label for="address"
                           class="mb-2 block text-sm font-semibold text-slate-700">
                        Address
                    </label>

                    <input id="address"
                           type="text"
                           name="address"
                           value="{{ old('address', $userRecord->address ?? '') }}"
                           placeholder="Enter complete address"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                    @error('address')
                        <p class="mt-2 text-sm font-medium text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="role"
                           class="mb-2 block text-sm font-semibold text-slate-700">
                        Role
                    </label>

                    <select id="role"
                            name="role"
                            required
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}"
                                @selected(
                                    old(
                                        'role',
                                        $userRecord->role ?? 'resident'
                                    ) === $value
                                )>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>

                    @error('role')
                        <p class="mt-2 text-sm font-medium text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <span class="mb-2 block text-sm font-semibold text-slate-700">
                        Status
                    </span>

                    <input type="hidden"
                           name="is_active"
                           value="{{ $accountIsActive ? '1' : '0' }}">

                    <div class="flex min-h-[42px] w-full items-center gap-3 rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm shadow-sm"
                         role="status"
                         aria-label="Account status: {{ $accountIsActive ? 'Active' : 'Inactive' }}">
                        @if ($accountIsActive)
                            <span class="relative flex h-3 w-3 shrink-0">
                                <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-40"></span>
                                <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span>
                            </span>

                            <span class="font-semibold text-emerald-700">
                                Active
                            </span>

                            <span class="text-xs text-slate-500">
                                Approved and allowed to access the system.
                            </span>
                        @else
                            <span class="relative flex h-3 w-3 shrink-0">
                                <span class="absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-40"></span>
                                <span class="relative inline-flex h-3 w-3 rounded-full bg-red-500"></span>
                            </span>

                            <span class="font-semibold text-red-700">
                                Inactive
                            </span>

                            <span class="text-xs text-slate-500">
                                Not approved or currently blocked from system access.
                            </span>
                        @endif
                    </div>

                    @error('is_active')
                        <p class="mt-2 text-sm font-medium text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>

            @if (! $isEdit)
                <div>
                    <label for="password"
                           class="mb-2 block text-sm font-semibold text-slate-700">
                        Initial Password
                    </label>

                    <input id="password"
                           type="password"
                           name="password"
                           autocomplete="new-password"
                           required
                           placeholder="At least 12 characters with uppercase, lowercase, number, and symbol"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                    <p class="mt-2 text-xs text-slate-500">
                        Use a unique initial password that satisfies the TabangNow password policy. The user can later change it from their profile.
                    </p>

                    @error('password')
                        <p class="mt-2 text-sm font-medium text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            @endif

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.users.index') }}"
                   class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </a>

                <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                    {{ $isEdit ? 'Save Changes' : 'Create User' }}
                </button>
            </div>
        </form>

        @if ($isEdit)
            <div class="border-t border-slate-200 px-6 py-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                    @if ($accountIsActive)
                        @can('deactivate', $userRecord)
                            <button type="button"
                                    popovertarget="deactivate-user-account-popover-{{ $userRecord->id }}"
                                    popovertargetaction="show"
                                    class="inline-flex w-full items-center justify-center rounded-xl border border-orange-300 bg-orange-50 px-5 py-2.5 text-sm font-semibold text-orange-800 hover:bg-orange-100 sm:w-auto">
                                Deactivate Account
                            </button>

                            <div id="deactivate-user-account-popover-{{ $userRecord->id }}"
                                 class="tn-account-popover"
                                 popover>
                                <div class="tn-account-popover-content">
                                    <div class="tn-account-popover-icon tn-account-popover-icon--deactivate"
                                         aria-hidden="true">
                                        !
                                    </div>

                                    <div>
                                        <h2 class="tn-account-popover-title">
                                            Deactivate account
                                        </h2>

                                        <p class="tn-account-popover-message">
                                            Deactivate this user account? The status will change to Inactive and the user will immediately lose access to the system.
                                        </p>
                                    </div>
                                </div>

                                <div class="tn-account-popover-actions">
                                    <button type="button"
                                            popovertarget="deactivate-user-account-popover-{{ $userRecord->id }}"
                                            popovertargetaction="hide"
                                            class="tn-account-popover-button tn-account-popover-button--cancel">
                                        Cancel
                                    </button>

                                    <form method="POST"
                                          action="{{ route('admin.users.deactivate', $userRecord) }}">
                                        @csrf
                                        @method('PATCH')

                                        <button type="submit"
                                                class="tn-account-popover-button tn-account-popover-button--deactivate">
                                            Deactivate
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endcan
                    @else
                        @can('activate', $userRecord)
                            <button type="button"
                                    popovertarget="activate-user-account-popover-{{ $userRecord->id }}"
                                    popovertargetaction="show"
                                    class="inline-flex w-full items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100 sm:w-auto">
                                Activate Account
                            </button>

                            <div id="activate-user-account-popover-{{ $userRecord->id }}"
                                 class="tn-account-popover"
                                 popover>
                                <div class="tn-account-popover-content">
                                    <div class="tn-account-popover-icon tn-account-popover-icon--activate"
                                         aria-hidden="true">
                                        ✓
                                    </div>

                                    <div>
                                        <h2 class="tn-account-popover-title">
                                            Activate account
                                        </h2>

                                        <p class="tn-account-popover-message">
                                            Activate this user account? The status will change to Active and the user will be allowed to access the system.
                                        </p>
                                    </div>
                                </div>

                                <div class="tn-account-popover-actions">
                                    <button type="button"
                                            popovertarget="activate-user-account-popover-{{ $userRecord->id }}"
                                            popovertargetaction="hide"
                                            class="tn-account-popover-button tn-account-popover-button--cancel">
                                        Cancel
                                    </button>

                                    <form method="POST"
                                          action="{{ route('admin.users.activate', $userRecord) }}">
                                        @csrf
                                        @method('PATCH')

                                        <button type="submit"
                                                class="tn-account-popover-button tn-account-popover-button--activate">
                                            Activate
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endcan
                    @endif

                    <form method="POST"
                          action="{{ route('admin.users.reset-password', $userRecord) }}">
                        @csrf
                        @method('PATCH')

                        <button type="submit"
                                class="inline-flex w-full items-center justify-center rounded-xl border border-amber-300 bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-800 hover:bg-amber-100 sm:w-auto">
                            Send Password Reset Link
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

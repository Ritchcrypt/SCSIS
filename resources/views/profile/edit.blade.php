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

<style>
    /*
    |--------------------------------------------------------------------------
    | Session Security theme compatibility
    |--------------------------------------------------------------------------
    |
    | TabangNow uses html[data-theme='dark'] and html[data-theme='system']
    | rather than Tailwind's .dark class. These selectors affect only the
    | Session Security panel on this page.
    |
    */

    .tn-session-security-panel {
        border-color: #bfdbfe;
        background-color: #eff6ff;
    }

    .tn-session-security-title {
        color: #1e3a8a;
    }

    .tn-session-security-description {
        color: #1e40af;
    }

    .tn-session-security-label {
        color: #334155;
    }

    .tn-session-security-input {
        border-color: #cbd5e1;
        background-color: #ffffff;
        color: #0f172a;
        caret-color: #0f172a;
    }

    .tn-session-security-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(191, 219, 254, 0.75);
    }

    .tn-password-wrapper {
        position: relative;
    }

    .tn-password-input {
        padding-right: 3rem !important;
        background-color: #0f172a !important;
        border-color: #334155 !important;
        color: #f8fafc !important;
        -webkit-text-fill-color: #f8fafc !important;
        caret-color: #ffffff !important;
    }

    .tn-password-input::placeholder {
        color: #94a3b8 !important;
        -webkit-text-fill-color: #94a3b8 !important;
        opacity: 1;
    }

    #selfResetPasswordPanel label {
        color: #78350f !important;
    }

    #selfDeleteAccountPanel label {
        color: #991b1b !important;
    }

    .tn-password-toggle {
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        display: inline-flex;
        width: 3rem;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 0 0.75rem 0.75rem 0;
        background: transparent;
        color: #64748b;
        cursor: pointer;
    }

    .tn-password-toggle:hover {
        color: #1d4ed8;
    }

    .tn-password-toggle:focus-visible {
        outline: 2px solid #3b82f6;
        outline-offset: -2px;
    }

    .tn-password-toggle svg {
        width: 1.25rem;
        height: 1.25rem;
        pointer-events: none;
    }

    html[data-theme='dark'] .tn-session-security-panel {
        border-color: #334155;
        background-color: #0f172a;
    }

    html[data-theme='dark'] .tn-session-security-title {
        color: #93c5fd;
    }

    html[data-theme='dark'] .tn-session-security-description {
        color: #cbd5e1;
    }

    html[data-theme='dark'] .tn-session-security-label {
        color: #e2e8f0;
    }

    html[data-theme='dark'] .tn-session-security-input {
        border-color: #475569;
        background-color: #020617;
        color: #f8fafc;
        caret-color: #ffffff;
    }

    html[data-theme='dark'] .tn-session-security-input:focus {
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.45);
    }

    html[data-theme='dark'] .tn-password-toggle {
        color: #cbd5e1;
    }

    html[data-theme='dark'] .tn-password-toggle:hover {
        color: #93c5fd;
    }

    @media (prefers-color-scheme: dark) {
        html[data-theme='system'] .tn-session-security-panel,
        html:not([data-theme]) .tn-session-security-panel {
            border-color: #334155;
            background-color: #0f172a;
        }

        html[data-theme='system'] .tn-session-security-title,
        html:not([data-theme]) .tn-session-security-title {
            color: #93c5fd;
        }

        html[data-theme='system'] .tn-session-security-description,
        html:not([data-theme]) .tn-session-security-description {
            color: #cbd5e1;
        }

        html[data-theme='system'] .tn-session-security-label,
        html:not([data-theme]) .tn-session-security-label {
            color: #e2e8f0;
        }

        html[data-theme='system'] .tn-session-security-input,
        html:not([data-theme]) .tn-session-security-input {
            border-color: #475569;
            background-color: #020617;
            color: #f8fafc;
            caret-color: #ffffff;
        }

        html[data-theme='system'] .tn-session-security-input:focus,
        html:not([data-theme]) .tn-session-security-input:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.45);
        }

        html[data-theme='system'] .tn-password-toggle,
        html:not([data-theme]) .tn-password-toggle {
            color: #cbd5e1;
        }

        html[data-theme='system'] .tn-password-toggle:hover,
        html:not([data-theme]) .tn-password-toggle:hover {
            color: #93c5fd;
        }
    }

    /* --------------------------------------------------------------------------
   Final password-field color correction
   -------------------------------------------------------------------------- */

input.tn-password-input {
    padding-right: 3rem !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

/* Light, white, and custom themes */
html[data-theme='light'] input.tn-password-input,
html[data-theme='white'] input.tn-password-input,
html[data-theme='custom'] input.tn-password-input,
html:not([data-theme]) input.tn-password-input {
    border-color: #cbd5e1 !important;
    background-color: #ffffff !important;
    color: #0f172a !important;
    -webkit-text-fill-color: #0f172a !important;
    caret-color: #0f172a !important;
}

/* Light-mode placeholders */
html[data-theme='light'] input.tn-password-input::placeholder,
html[data-theme='white'] input.tn-password-input::placeholder,
html[data-theme='custom'] input.tn-password-input::placeholder,
html:not([data-theme]) input.tn-password-input::placeholder {
    color: #64748b !important;
    -webkit-text-fill-color: #64748b !important;
    opacity: 1 !important;
}

/* Dark theme */
html[data-theme='dark'] input.tn-password-input {
    border-color: #475569 !important;
    background-color: #020617 !important;
    color: #f8fafc !important;
    -webkit-text-fill-color: #f8fafc !important;
    caret-color: #ffffff !important;
}

html[data-theme='dark'] input.tn-password-input::placeholder {
    color: #94a3b8 !important;
    -webkit-text-fill-color: #94a3b8 !important;
    opacity: 1 !important;
}

/* System theme when the computer uses light mode */
@media (prefers-color-scheme: light) {
    html[data-theme='system'] input.tn-password-input {
        border-color: #cbd5e1 !important;
        background-color: #ffffff !important;
        color: #0f172a !important;
        -webkit-text-fill-color: #0f172a !important;
        caret-color: #0f172a !important;
    }

    html[data-theme='system'] input.tn-password-input::placeholder {
        color: #64748b !important;
        -webkit-text-fill-color: #64748b !important;
        opacity: 1 !important;
    }
}

/* System theme when the computer uses dark mode */
@media (prefers-color-scheme: dark) {
    html[data-theme='system'] input.tn-password-input {
        border-color: #475569 !important;
        background-color: #020617 !important;
        color: #f8fafc !important;
        -webkit-text-fill-color: #f8fafc !important;
        caret-color: #ffffff !important;
    }

    html[data-theme='system'] input.tn-password-input::placeholder {
        color: #94a3b8 !important;
        -webkit-text-fill-color: #94a3b8 !important;
        opacity: 1 !important;
    }
}

/* Keep browser autofill text readable */
html[data-theme='light'] input.tn-password-input:-webkit-autofill,
html[data-theme='white'] input.tn-password-input:-webkit-autofill,
html[data-theme='custom'] input.tn-password-input:-webkit-autofill {
    -webkit-text-fill-color: #0f172a !important;
}

html[data-theme='dark'] input.tn-password-input:-webkit-autofill {
    -webkit-text-fill-color: #f8fafc !important;
}

/* Readable labels on the warning panels */
#selfResetPasswordPanel label {
    color: #78350f !important;
}

#selfDeleteAccountPanel label {
    color: #991b1b !important;
}
</style>

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
                            Accepted formats: JPG, PNG, or WEBP. Maximum size: 5 MB.
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

        <div class="border-t border-slate-200 px-6 py-5">
            <div class="tn-session-security-panel rounded-2xl border p-5">
                <h2 class="tn-session-security-title text-lg font-bold">
                    Session Security
                </h2>

                <p class="tn-session-security-description mt-1 text-sm leading-6">
                    Sign out this account from every other browser or device.
                    This browser will remain signed in.
                </p>

                <form method="POST"
                      action="{{ route('profile.other-sessions.destroy') }}"
                      class="mt-5 space-y-4">
                    @csrf
                    @method('DELETE')

                    <div>
                        <label for="other_sessions_password"
                               class="tn-session-security-label mb-2 block text-sm font-semibold">
                            Current Password
                        </label>

                        <div class="tn-password-wrapper">
                            <input id="other_sessions_password"
                                   name="password"
                                   type="password"
                                   required
                                   autocomplete="current-password"
                                   class="tn-session-security-input tn-password-input w-full rounded-xl border px-4 py-2.5 text-sm shadow-sm outline-none transition">

                            <button type="button"
                                    class="tn-password-toggle"
                                    data-password-toggle="other_sessions_password"
                                    aria-label="Show password"
                                    aria-pressed="false">
                                <svg data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>

                                <svg data-eye-closed class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.6 5.4A9.8 9.8 0 0 1 12 5.25C18 5.25 21.75 12 21.75 12a16.8 16.8 0 0 1-3.1 3.95M6.1 6.1C3.65 7.75 2.25 12 2.25 12S6 18.75 12 18.75a9.9 9.9 0 0 0 3.2-.53M9.88 9.88a3 3 0 0 0 4.24 4.24"/>
                                </svg>
                            </button>
                        </div>

                        @error('password', 'logoutOtherSessions')
                            <p class="mt-2 text-sm font-medium text-red-600">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="inline-flex rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                            Sign Out Other Devices
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @php
            $selfServiceRole = strtolower((string) ($userRecord->role ?? auth()->user()?->role ?? ''));
        @endphp

        @if (in_array($selfServiceRole, ['official', 'dao', 'tanod', 'resident'], true))
            <div class="border-t border-slate-200 px-6 py-5">
                <div class="flex justify-end gap-3">
                    <button type="button"
                            onclick="document.getElementById('selfResetPasswordPanel').classList.toggle('hidden')"
                            class="inline-flex rounded-xl border border-yellow-300 bg-yellow-50 px-5 py-2.5 text-sm font-semibold text-yellow-700 hover:bg-yellow-100">
                        Reset Password
                    </button>

                    <button type="button"
                            onclick="document.getElementById('selfDeleteAccountPanel').classList.toggle('hidden')"
                            class="inline-flex rounded-xl border border-red-300 bg-red-50 px-5 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-100">
                        Permanent Delete
                    </button>
                </div>

                <div id="selfResetPasswordPanel"
                     class="mt-5 hidden rounded-2xl border border-yellow-200 bg-yellow-50 p-5">
                    <h2 class="text-lg font-bold text-yellow-800">
                        Reset Password
                    </h2>

                    <p class="mt-1 text-sm text-yellow-700">
                        Update your password using your current password.
                    </p>

                    <form method="POST"
                          action="{{ route('profile.password.update') }}"
                          class="mt-5 space-y-4">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="current_password" class="mb-2 block text-sm font-semibold text-slate-700">
                                Current Password
                            </label>

                            <div class="tn-password-wrapper">
                                <input id="current_password"
                                       name="current_password"
                                       type="password"
                                       autocomplete="current-password"
                                       class="tn-password-input w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                                <button type="button"
                                        class="tn-password-toggle"
                                        data-password-toggle="current_password"
                                        aria-label="Show password"
                                        aria-pressed="false">
                                    <svg data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>

                                    <svg data-eye-closed class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.6 5.4A9.8 9.8 0 0 1 12 5.25C18 5.25 21.75 12 21.75 12a16.8 16.8 0 0 1-3.1 3.95M6.1 6.1C3.65 7.75 2.25 12 2.25 12S6 18.75 12 18.75a9.9 9.9 0 0 0 3.2-.53M9.88 9.88a3 3 0 0 0 4.24 4.24"/>
                                    </svg>
                                </button>
                            </div>

                            @error('current_password', 'updatePassword')
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">
                                New Password
                            </label>

                            <div class="tn-password-wrapper">
                                <input id="password"
                                       name="password"
                                       type="password"
                                       autocomplete="new-password"
                                       class="tn-password-input w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                                <button type="button"
                                        class="tn-password-toggle"
                                        data-password-toggle="password"
                                        aria-label="Show password"
                                        aria-pressed="false">
                                    <svg data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>

                                    <svg data-eye-closed class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.6 5.4A9.8 9.8 0 0 1 12 5.25C18 5.25 21.75 12 21.75 12a16.8 16.8 0 0 1-3.1 3.95M6.1 6.1C3.65 7.75 2.25 12 2.25 12S6 18.75 12 18.75a9.9 9.9 0 0 0 3.2-.53M9.88 9.88a3 3 0 0 0 4.24 4.24"/>
                                    </svg>
                                </button>
                            </div>

                            @error('password', 'updatePassword')
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-700">
                                Confirm New Password
                            </label>

                            <div class="tn-password-wrapper">
                                <input id="password_confirmation"
                                       name="password_confirmation"
                                       type="password"
                                       autocomplete="new-password"
                                       class="tn-password-input w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">

                                <button type="button"
                                        class="tn-password-toggle"
                                        data-password-toggle="password_confirmation"
                                        aria-label="Show password"
                                        aria-pressed="false">
                                    <svg data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>

                                    <svg data-eye-closed class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.6 5.4A9.8 9.8 0 0 1 12 5.25C18 5.25 21.75 12 21.75 12a16.8 16.8 0 0 1-3.1 3.95M6.1 6.1C3.65 7.75 2.25 12 2.25 12S6 18.75 12 18.75a9.9 9.9 0 0 0 3.2-.53M9.88 9.88a3 3 0 0 0 4.24 4.24"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex rounded-xl bg-yellow-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-yellow-600">
                                Save New Password
                            </button>
                        </div>
                    </form>
                </div>

                <div id="selfDeleteAccountPanel"
                     class="mt-5 hidden rounded-2xl border border-red-200 bg-red-50 p-5">
                    <h2 class="text-lg font-bold text-red-800">
                        Permanent Delete Account
                    </h2>

                    <p class="mt-1 text-sm leading-6 text-red-700">
                        This will permanently delete your account. This action cannot be undone.
                    </p>

                    <form id="selfDeleteAccountForm"
                          method="POST"
                          action="{{ route('profile.self-delete') }}"
                          class="mt-5 space-y-4">
                        @csrf
                        @method('DELETE')

                        <div>
                            <label for="delete_password" class="mb-2 block text-sm font-semibold text-slate-700">
                                Confirm Password
                            </label>

                            <div class="tn-password-wrapper">
                                <input id="delete_password"
                                       name="password"
                                       type="password"
                                       required
                                       autocomplete="current-password"
                                       class="tn-password-input w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100">

                                <button type="button"
                                        class="tn-password-toggle"
                                        data-password-toggle="delete_password"
                                        aria-label="Show password"
                                        aria-pressed="false">
                                    <svg data-eye-open viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>

                                    <svg data-eye-closed class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.6 5.4A9.8 9.8 0 0 1 12 5.25C18 5.25 21.75 12 21.75 12a16.8 16.8 0 0 1-3.1 3.95M6.1 6.1C3.65 7.75 2.25 12 2.25 12S6 18.75 12 18.75a9.9 9.9 0 0 0 3.2-.53M9.88 9.88a3 3 0 0 0 4.24 4.24"/>
                                    </svg>
                                </button>
                            </div>

                            @error('password', 'userDeletion')
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                                Delete My Account Permanently
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
    (function () {
        function initializePasswordToggles(root) {
            const scope = root instanceof Element || root instanceof Document
                ? root
                : document;

            scope.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                if (button.dataset.passwordToggleReady === 'true') {
                    return;
                }

                button.dataset.passwordToggleReady = 'true';

                button.addEventListener('click', function () {
                    const inputId = button.getAttribute('data-password-toggle');
                    const input = document.getElementById(inputId);

                    if (! input) {
                        return;
                    }

                    const shouldShow = input.type === 'password';

                    input.type = shouldShow ? 'text' : 'password';
                    button.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
                    button.setAttribute(
                        'aria-label',
                        shouldShow ? 'Hide password' : 'Show password'
                    );

                    const openEye = button.querySelector('[data-eye-open]');
                    const closedEye = button.querySelector('[data-eye-closed]');

                    if (openEye) {
                        openEye.classList.toggle('hidden', shouldShow);
                    }

                    if (closedEye) {
                        closedEye.classList.toggle('hidden', ! shouldShow);
                    }

                    input.focus({ preventScroll: true });

                    const cursorPosition = input.value.length;

                    try {
                        input.setSelectionRange(cursorPosition, cursorPosition);
                    } catch (error) {
                        // Some browser/input combinations do not support selection ranges.
                    }
                });
            });
        }

        function initializeDeleteConfirmation() {
            const form = document.getElementById('selfDeleteAccountForm');
            const passwordInput = document.getElementById('delete_password');

            if (
                ! form
                || ! passwordInput
                || form.dataset.deleteConfirmationReady === 'true'
            ) {
                return;
            }

            form.dataset.deleteConfirmationReady = 'true';

            form.addEventListener('submit', function (event) {
                const passwordValue = passwordInput.value.trim();

                if (! passwordValue) {
                    event.preventDefault();

                    passwordInput.setCustomValidity(
                        'Enter your current password before deleting the account.'
                    );
                    passwordInput.reportValidity();
                    passwordInput.focus({ preventScroll: false });

                    window.setTimeout(function () {
                        passwordInput.setCustomValidity('');
                    }, 0);

                    return;
                }

                const confirmed = window.confirm(
                    'Permanently delete this account? This action cannot be undone.'
                );

                if (! confirmed) {
                    event.preventDefault();
                }
            });
        }

        function bootPasswordToggles() {
            initializePasswordToggles(document);
            initializeDeleteConfirmation();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bootPasswordToggles, {
                once: true,
            });
        } else {
            bootPasswordToggles();
        }

        document.addEventListener('livewire:navigated', bootPasswordToggles);
    })();
</script>
@endsection

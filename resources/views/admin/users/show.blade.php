@extends('layouts.admin')

@section('title', 'User Details | TabangNow')

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <a href="{{ route('admin.users.index') }}"
           class="inline-flex text-sm font-semibold text-blue-700 hover:text-blue-900">
            ← Back to User Management
        </a>

        <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-blue-700">
                    User Account
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                    {{ ucfirst((string) $userRecord->role) }}
                </span>

                @if (! Schema::hasColumn('users', 'is_active') || $userRecord->is_active)
                    <span class="inline-flex rounded-full border border-green-200 bg-green-50 px-3 py-1 text-xs font-bold text-green-700">
                        Active
                    </span>
                @else
                    <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                        Inactive
                    </span>
                @endif
            </div>
        </div>
    </div>

    @php
        $profilePhotoPath = Schema::hasColumn('users', 'profile_photo_path')
            ? ($userRecord->profile_photo_path ?? null)
            : null;

        $profilePhotoUrl = $profilePhotoPath && Route::has('users.profile-photo')
            ? route('users.profile-photo', $userRecord) . '?v=' . optional($userRecord->updated_at)->timestamp
            : null;

        $profileInitial = strtoupper(mb_substr($userRecord->name ?? 'U', 0, 1));
    @endphp

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-base font-bold text-slate-900">
                Account Information
            </h2>
        </div>

        <div class="grid gap-6 p-6 lg:grid-cols-3">
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-950 via-blue-900 to-blue-700 p-6 text-white shadow-lg">
                <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10"></div>
                <div class="absolute -bottom-12 -left-12 h-36 w-36 rounded-full bg-blue-400/20"></div>

                <div class="relative flex h-full flex-col items-center justify-center text-center">
                    <div class="rounded-full bg-white/20 p-1.5 shadow-xl ring-1 ring-white/30">
                        <div class="flex h-36 w-36 items-center justify-center overflow-hidden rounded-full bg-white">
                            @if ($profilePhotoUrl)
                                <img src="{{ $profilePhotoUrl }}"
                                     alt="{{ $userRecord->name }} profile photo"
                                     class="h-full w-full object-cover"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">

                                <div style="display:none;"
                                     class="h-full w-full items-center justify-center bg-blue-50 text-5xl font-bold text-blue-800">
                                    {{ $profileInitial }}
                                </div>
                            @else
                                <div class="flex h-full w-full items-center justify-center bg-blue-50 text-5xl font-bold text-blue-800">
                                    {{ $profileInitial }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <p class="mt-2 text-xl font-bold">
                        {{ $userRecord->name }}
                    </p>

                    <p class="mt-1 text-sm text-blue-100">
                        {{ ucfirst((string) $userRecord->role) }} account
                    </p>
                </div>
            </div>

            <div class="grid gap-5 rounded-2xl border border-slate-200 bg-slate-50 p-6 md:grid-cols-2 lg:col-span-2">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Full Name
                    </p>

                    <p class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $userRecord->name }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Email
                    </p>

                    <p class="mt-1 break-all text-sm font-semibold text-slate-900">
                        {{ $userRecord->email }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4">
    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
        Contact Number
    </p>

    <p class="mt-1 text-sm font-semibold text-slate-900">
        {{ $userRecord->contact_number ?? '—' }}
    </p>
</div>

<div class="rounded-xl border border-slate-200 bg-white p-4">
    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
        Address
    </p>

    <p class="mt-1 text-sm font-semibold leading-6 text-slate-900">
    {{ $userRecord->address ?? '—' }}
</p>
</div>
<div class="rounded-xl border border-slate-200 bg-white p-4">
    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
        Barangay of Residence
    </p>

    <p class="mt-1 text-sm font-semibold text-slate-900">
        {{ $barangayName ?: '—' }}
    </p>
</div>
                </div>
            </div>
        </div>
    </div>
</div>

    @if (strtolower((string) $userRecord->role) === 'resident')
        @php
            $residentAge = $userRecord->resident_age;
            $hasDob = (bool) $userRecord->date_of_birth;
            $ageGatePassed = $hasDob
                && $residentAge !== null
                && $residentAge >= 18
                && (bool) $userRecord->age_attested_at;
        @endphp

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-bold text-slate-900">
                    Resident Age Verification
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Age is calculated automatically from the date of birth submitted during resident registration.
                </p>
            </div>

            <div class="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Date of Birth
                    </p>

                    <p class="mt-2 text-sm font-semibold text-slate-900">
                        {{ $hasDob
                            ? $userRecord->date_of_birth->format('F d, Y')
                            : 'Not provided' }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Current Age
                    </p>

                    <p class="mt-2 text-sm font-semibold text-slate-900">
                        {{ $residentAge !== null
                            ? $residentAge . ' years old'
                            : 'Unavailable' }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Signup Age Gate
                    </p>

                    @if ($ageGatePassed)
                        <span class="mt-2 inline-flex rounded-full border border-green-200 bg-green-50 px-3 py-1 text-xs font-bold text-green-700">
                            Passed 18+ signup gate
                        </span>
                    @elseif ($residentAge !== null && $residentAge < 18)
                        <span class="mt-2 inline-flex rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-bold text-red-700">
                            Under 18 — review required
                        </span>
                    @else
                        <span class="mt-2 inline-flex rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">
                            Legacy / no age record
                        </span>
                    @endif
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Age Attestation
                    </p>

                    <p class="mt-2 text-sm font-semibold text-slate-900">
                        {{ $userRecord->age_attested_at
                            ? $userRecord->age_attested_at->format('M d, Y g:i A')
                            : 'Not recorded' }}
                    </p>
                </div>
            </div>

            <div class="border-t border-blue-100 bg-blue-50 px-6 py-4 text-xs leading-5 text-blue-900">
                This confirms the resident passed TabangNow's declared-age signup control.
                It is not government-ID identity verification.
            </div>
        </div>
    @endif
@endsection

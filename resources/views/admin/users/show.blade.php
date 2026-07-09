@extends('layouts.admin')

@section('title', 'User Details | DaoSystem')

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

                <h1 class="mt-1 text-2xl font-bold text-slate-900">
                    {{ $userRecord->name }}
                </h1>

                <p class="mt-2 text-sm text-slate-600">
                    {{ $userRecord->email }}
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

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-bold text-slate-900">
                    Account Information
                </h2>
            </div>

            <div class="grid gap-5 p-6 md:grid-cols-2">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Full Name
                    </p>

                    <p class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $userRecord->name }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Email
                    </p>

                    <p class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $userRecord->email }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Contact Number
                    </p>

                    <p class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $userRecord->contact_number ?? '—' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Barangay
                    </p>

                    <p class="mt-1 text-sm font-semibold text-slate-900">
                        {{ $barangayName }}
                    </p>
                </div>

                <div class="md:col-span-2">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Address
                    </p>

                    <p class="mt-1 text-sm leading-6 text-slate-700">
                        {{ $userRecord->address ?? '—' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-bold text-slate-900">
                    Actions
                </h2>
            </div>

            <div class="space-y-3 p-6">
                <a href="{{ route('admin.users.edit', $userRecord) }}"
                   class="flex w-full items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                    Edit User
                </a>

                <form method="POST" action="{{ route('admin.users.reset-password', $userRecord) }}">
                    @csrf
                    @method('PATCH')

                    <button type="submit"
                            onclick="return confirm('Reset password for this user?')"
                            class="flex w-full items-center justify-center rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-2.5 text-sm font-semibold text-yellow-700 hover:bg-yellow-100">
                        Reset Password
                    </button>
                </form>

                @if (! Schema::hasColumn('users', 'is_active') || $userRecord->is_active)
                    <form method="POST" action="{{ route('admin.users.deactivate', $userRecord) }}">
                        @csrf
                        @method('PATCH')

                        <button type="submit"
                                onclick="return confirm('Deactivate this user?')"
                                class="flex w-full items-center justify-center rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-200">
                            Deactivate
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.users.activate', $userRecord) }}">
                        @csrf
                        @method('PATCH')

                        <button type="submit"
                                class="flex w-full items-center justify-center rounded-xl border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-700 hover:bg-green-100">
                            Activate
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('admin.users.destroy', $userRecord) }}">
                    @csrf
                    @method('DELETE')

                    <button type="submit"
                            onclick="return confirm('Delete this user? This is blocked if the user has connected records.')"
                            class="flex w-full items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800">
                        Delete User
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
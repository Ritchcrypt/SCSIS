@extends('layouts.admin')

@section('content')
    @php
        $authUser = auth()->user();
        $role = strtolower((string) ($authUser?->role ?? ''));

        $indexRoute = match ($role) {
            'admin' => 'admin.resident-complaints.index',
            'official', 'dao' => 'official.resident-complaints.index',
            'resident' => 'resident.resident-complaints.index',
            default => 'dashboard',
        };

        $updateStatusRoute = match ($role) {
            'admin' => 'admin.resident-complaints.update-status',
            'official', 'dao' => 'official.resident-complaints.update-status',
            default => null,
        };

        $destroyRoute = $role === 'admin'
            ? 'admin.resident-complaints.destroy'
            : null;

        $statusClasses = [
            'submitted' => 'bg-blue-100 text-blue-700 border-blue-200',
            'under_review' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'in_progress' => 'bg-purple-100 text-purple-700 border-purple-200',
            'resolved' => 'bg-green-100 text-green-700 border-green-200',
            'rejected' => 'bg-red-100 text-red-700 border-red-200',
        ];

        $statusClass = $statusClasses[$complaint->status] ?? 'bg-slate-100 text-slate-700 border-slate-200';

        $evidenceUrl = $complaint->evidence_path
            ? asset('storage/' . $complaint->evidence_path)
            : null;
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ Route::has($indexRoute) ? route($indexRoute) : route('dashboard') }}"
                   class="text-sm font-bold text-blue-700 hover:text-blue-800">
                    ← Back to Complaints
                </a>

                <h1 class="mt-2 text-2xl font-black text-slate-900">
    Complaint Details
</h1>

                <p class="mt-1 text-sm text-slate-600">
                    Resident complaint details and evidence.
                </p>
            </div>

            <span class="inline-flex w-fit rounded-full border px-4 py-2 text-sm font-black {{ $statusClass }}">
                {{ $complaint->statusLabel() }}
            </span>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-semibold text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">
                        Complaint Information
                    </h2>

                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Complainant</p>
                            <p class="mt-1 text-sm font-bold text-slate-900">{{ $complaint->complainant_name }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Contact Number</p>
                            <p class="mt-1 text-sm text-slate-700">{{ $complaint->contact_number ?: 'No contact number' }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Submitted Date and Time</p>
                            <p class="mt-1 text-sm text-slate-700">
                                {{ optional($complaint->submitted_at)->format('M d, Y h:i A') }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Current Status</p>
                            <p class="mt-1 text-sm font-bold text-slate-900">{{ $complaint->statusLabel() }}</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Address / Location</p>
                        <p class="mt-2 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-700">
                            {{ $complaint->complaint_address }}
                        </p>
                    </div>

                    <div class="mt-6">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Complaint Description</p>
                        <p class="mt-2 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-700">
                            {{ $complaint->complaint_description }}
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">
                        Evidence Picture
                    </h2>

                    @if ($evidenceUrl)
                        <a href="{{ $evidenceUrl }}" target="_blank" class="mt-4 block overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                            <img src="{{ $evidenceUrl }}"
                                 alt="Complaint evidence"
                                 class="max-h-[32rem] w-full object-contain">
                        </a>
                    @else
                        <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                            <p class="text-sm font-bold text-slate-700">No evidence picture uploaded.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                @if ($canManageComplaints && $updateStatusRoute && Route::has($updateStatusRoute))
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-black text-slate-900">
                            Update Status
                        </h2>

                        <form method="POST"
                              action="{{ route($updateStatusRoute, $complaint) }}"
                              class="mt-5 space-y-4">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label for="status" class="mb-2 block text-sm font-bold text-slate-700">
                                    Status
                                </label>

                                <select id="status"
                                        name="status"
                                        class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                                    @foreach ($statuses as $statusValue => $statusLabel)
                                        <option value="{{ $statusValue }}" @selected($complaint->status === $statusValue)>
                                            {{ $statusLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit"
                                    class="w-full rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">
                                Save Status
                            </button>
                        </form>
                    </div>
                @endif

                @if ($destroyRoute && Route::has($destroyRoute))
                    <div class="rounded-2xl border border-red-200 bg-red-50 p-6">
                        <h2 class="text-lg font-black text-red-800">
                            Delete Complaint
                        </h2>

                        <p class="mt-2 text-sm leading-6 text-red-700">
                            This will remove the complaint, evidence picture, and related notifications.
                        </p>

                        <form method="POST"
                              action="{{ route($destroyRoute, $complaint) }}"
                              class="mt-5"
                              onsubmit="return confirm('Delete this complaint permanently?');">
                            @csrf
                            @method('DELETE')

                            <button type="submit"
                                    class="w-full rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700">
                                Delete Complaint
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
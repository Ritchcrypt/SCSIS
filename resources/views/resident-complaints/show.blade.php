@extends('layouts.admin')

@section('content')
@php
    $role = strtolower((string) auth()->user()?->role);
    $proofs = $proofs ?? collect();

    $indexUrl = match ($role) {
        'admin' => Route::has('admin.resident-complaints.index') ? route('admin.resident-complaints.index') : route('dashboard'),
        'official', 'dao' => Route::has('official.resident-complaints.index') ? route('official.resident-complaints.index') : route('dashboard'),
        'resident' => Route::has('resident.resident-complaints.index') ? route('resident.resident-complaints.index') : route('dashboard'),
        default => route('dashboard'),
    };

    $updateStatusUrl = match ($role) {
        'admin' => Route::has('admin.resident-complaints.update-status') ? route('admin.resident-complaints.update-status', $complaint) : null,
        'official', 'dao' => Route::has('official.resident-complaints.update-status') ? route('official.resident-complaints.update-status', $complaint) : null,
        default => null,
    };

    $destroyUrl = $role === 'admin' && Route::has('admin.resident-complaints.destroy')
        ? route('admin.resident-complaints.destroy', $complaint)
        : null;

    $residentEvidenceUrl = match ($role) {
        'admin' => Route::has('admin.resident-complaints.evidence') ? route('admin.resident-complaints.evidence', $complaint) : null,
        'official', 'dao' => Route::has('official.resident-complaints.evidence') ? route('official.resident-complaints.evidence', $complaint) : null,
        'resident' => Route::has('resident.resident-complaints.evidence') ? route('resident.resident-complaints.evidence', $complaint) : null,
        default => null,
    };

    if (empty($complaint->evidence_path)) {
        $residentEvidenceUrl = null;
    }

    $proofStoreRoute = match ($role) {
        'admin' => Route::has('admin.resident-complaints.proofs.store') ? route('admin.resident-complaints.proofs.store', $complaint) : null,
        'official', 'dao' => Route::has('official.resident-complaints.proofs.store') ? route('official.resident-complaints.proofs.store', $complaint) : null,
        default => null,
    };

    $statusClass = match ($complaint->status) {
        'submitted' => 'border-blue-200 bg-blue-50 text-blue-700',
        'under_review' => 'border-yellow-200 bg-yellow-50 text-yellow-700',
        'in_progress' => 'border-orange-200 bg-orange-50 text-orange-700',
        'resolved' => 'border-green-200 bg-green-50 text-green-700',
        'rejected' => 'border-red-200 bg-red-50 text-red-700',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ $indexUrl }}" class="text-sm font-bold text-blue-700 hover:text-blue-800">← Back to Resident Feedback</a>
            <h1 class="mt-2 text-2xl font-black text-slate-900">Feedback Details</h1>
            <p class="mt-1 text-sm text-slate-600">
                Review what the resident shared and the response or action taken by barangay personnel.
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

    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700">
            <p class="font-black">Please fix the following:</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">{{ $complaint->feedback_subject ?: 'Resident Feedback' }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $complaint->feedbackCategoryLabel() }}</p>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-black text-slate-700">
                        {{ $complaint->feedbackTypeLabel() }}
                    </span>
                </div>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Resident</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">{{ $complaint->complainant_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Contact Number</p>
                        <p class="mt-1 text-sm text-slate-700">{{ $complaint->contact_number ?: 'No contact number' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Submitted</p>
                        <p class="mt-1 text-sm text-slate-700">{{ optional($complaint->submitted_at)->format('M d, Y h:i A') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Service / Experience Rating</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">{{ $complaint->service_rating ? $complaint->service_rating . ' / 5' : 'Not rated' }}</p>
                    </div>
                </div>

                @if (! empty($complaint->complaint_address))
                    <div class="mt-6">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Location / Service Area</p>
                        <p class="mt-2 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-700">{{ $complaint->complaint_address }}</p>
                    </div>
                @endif

                <div class="mt-6">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Feedback Details</p>
                    <p class="mt-2 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-700">{{ $complaint->complaint_description }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-black text-slate-900">Resident Supporting Picture</h2>
                <p class="mt-1 text-sm text-slate-500">Optional picture supplied by the resident to support the feedback.</p>

                @if ($residentEvidenceUrl)
                    <a href="{{ $residentEvidenceUrl }}" target="_blank" class="mt-5 block overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                        <img src="{{ $residentEvidenceUrl }}" alt="Resident feedback attachment" class="max-h-[32rem] w-full object-contain">
                    </a>
                @else
                    <div class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                        <p class="text-sm font-bold text-slate-700">No supporting picture was attached.</p>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-blue-200 bg-white p-6 shadow-sm">
                <div>
                    <h2 class="text-lg font-black text-slate-900">Barangay Response & Action Evidence</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Admins and officials can document what was done so the resident can see the response and action taken.
                    </p>
                </div>

                @if ($canManageComplaints && $proofStoreRoute)
                    <form method="POST"
                          action="{{ $proofStoreRoute }}"
                          enctype="multipart/form-data"
                          class="mt-5 rounded-2xl border border-blue-100 bg-blue-50 p-5">
                        @csrf

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="proof_picture" class="mb-2 block text-sm font-bold text-slate-700">Action Evidence Picture</label>
                                <input id="proof_picture"
                                       name="proof_picture"
                                       type="file"
                                       accept="image/jpeg,image/jpg,image/png,image/webp"
                                       required
                                       class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white hover:file:bg-blue-700">
                            </div>
                            <div>
                                <label for="proof_note" class="mb-2 block text-sm font-bold text-slate-700">Response / Action Note</label>
                                <textarea id="proof_note"
                                          name="proof_note"
                                          rows="3"
                                          placeholder="Explain how staff responded and what action was taken."
                                          class="w-full resize-none rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">{{ old('proof_note') }}</textarea>
                            </div>
                        </div>

                        <button type="submit"
                                class="mt-4 rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">
                            Add Response / Action Evidence
                        </button>
                    </form>
                @endif

                @if ($proofs->count())
                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        @foreach ($proofs as $proof)
                            @php
                                $proofUrl = Route::has('resident-complaint-proofs.file')
                                    ? route('resident-complaint-proofs.file', $proof->id)
                                    : null;
                            @endphp

                            <div class="overflow-hidden rounded-2xl border border-blue-100 bg-blue-50">
                                @if ($proofUrl)
                                    <a href="{{ $proofUrl }}" target="_blank" class="block bg-white">
                                        <img src="{{ $proofUrl }}" alt="Barangay response action evidence" class="h-64 w-full object-contain">
                                    </a>
                                @endif
                                <div class="space-y-2 border-t border-blue-100 bg-white p-4">
                                    <p class="text-sm font-bold text-slate-900">Response by {{ $proof->uploader_name ?? 'Admin / Official' }}</p>
                                    <p class="text-xs font-bold uppercase tracking-wide text-blue-700">{{ ucfirst((string) ($proof->uploader_role ?? 'staff')) }}</p>
                                    @if (! empty($proof->proof_note))
                                        <p class="rounded-xl bg-slate-50 p-3 text-sm leading-6 text-slate-700">{{ $proof->proof_note }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-5 rounded-2xl border border-dashed border-blue-300 bg-blue-50 px-5 py-10 text-center">
                        <p class="text-sm font-bold text-blue-800">No response or action evidence has been added yet.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            @if ($canManageComplaints && $updateStatusUrl)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">Update Feedback Status</h2>
                    <form method="POST" action="{{ $updateStatusUrl }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PATCH')
                        <select id="status"
                                name="status"
                                class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                            @foreach ($statuses as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}" @selected($complaint->status === $statusValue)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="w-full rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">
                            Save Status
                        </button>
                    </form>
                </div>
            @endif

            @if ($destroyUrl)
                <div class="rounded-2xl border border-red-200 bg-red-50 p-6">
                    <h2 class="text-lg font-black text-red-800">Delete Feedback</h2>
                    <p class="mt-2 text-sm leading-6 text-red-700">
                        This removes the feedback, supporting picture, response/action evidence, and related notifications. Deleted feedback is covered by the admin recovery feature.
                    </p>
                    <form method="POST" action="{{ $destroyUrl }}" class="mt-5">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700">
                            Delete Feedback
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('title', 'Case Management | DaoSystem')

@section('content')
@php
    $statusStyles = [
        'open' => 'bg-blue-100 text-blue-700',
        'under_investigation' => 'bg-purple-100 text-purple-700',
        'mediation' => 'bg-yellow-100 text-yellow-700',
        'resolved' => 'bg-emerald-100 text-emerald-700',
        'closed' => 'bg-slate-100 text-slate-700',
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Case Management</h1>
            <p class="mt-1 text-sm text-slate-500">Barangay blotter and case files</p>
        </div>

        <button type="button"
                onclick="openCreateCaseModal()"
                class="inline-flex items-center justify-center rounded-xl bg-blue-950 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-900">
            + New Case
        </button>
    </div>

    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-bold">Please fix the following errors:</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.cases.index') }}" class="max-w-xl">
        <div class="relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>

            <input type="text"
                   name="search"
                   value="{{ $filters['search'] ?? '' }}"
                   placeholder="Search cases..."
                   class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-11 pr-4 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Case No.</th>
                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Subject</th>
                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Incident</th>
                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Hearing</th>
                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Handled By</th>
                        <th class="px-5 py-4 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($cases as $case)
                        @php
                            $statusClass = $statusStyles[$case->status] ?? 'bg-slate-100 text-slate-700';
                        @endphp

                        <tr>
                            <td class="whitespace-nowrap px-5 py-4 font-mono text-sm text-slate-900">
                                {{ $case->case_number }}
                            </td>

                            <td class="px-5 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $case->subject_name }}</p>

                                @if ($case->contact)
                                    <p class="text-xs text-slate-500">{{ $case->contact }}</p>
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                {{ $case->display_type }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $case->display_incident_title }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusClass }}">
                                    {{ $case->display_status }}
                                </span>
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                {{ $case->hearing_date ? $case->hearing_date->format('M d, Y') : '—' }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $case->handled_by ?: '—' }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <button type="button"
                                        onclick="openEditCaseModal(this)"
                                        data-update-url="{{ route('admin.cases.update', $case) }}"
                                        data-case-number="{{ e($case->case_number) }}"
                                        data-case-type="{{ e($case->case_type) }}"
                                        data-subject-name="{{ e($case->subject_name) }}"
                                        data-contact="{{ e($case->contact) }}"
                                        data-address="{{ e($case->address) }}"
                                        data-incident-id="{{ e($case->incident_id) }}"
                                        data-incident-title="{{ e($case->incident_title) }}"
                                        data-status="{{ e($case->status) }}"
                                        data-hearing-date="{{ $case->hearing_date ? $case->hearing_date->format('Y-m-d') : '' }}"
                                        data-handled-by="{{ e($case->handled_by) }}"
                                        data-resolution="{{ e($case->resolution) }}"
                                        data-notes="{{ e($case->notes) }}"
                                        class="mr-3 text-slate-700 hover:text-blue-700"
                                        title="Edit case">
                                    ✎
                                </button>

                                <form method="POST"
                                      action="{{ route('admin.cases.destroy', $case) }}"
                                      class="inline"
                                      onsubmit="return confirm('Delete this case record? This action cannot be undone.');">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                            class="text-red-600 hover:text-red-800"
                                            title="Delete case">
                                        🗑
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center">
                                <p class="text-sm font-semibold text-slate-700">No case records found.</p>
                                <p class="mt-1 text-xs text-slate-500">Create your first barangay case file.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($cases->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $cases->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Create Modal --}}
<div id="createCaseModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-5 flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900">Create New Case</h2>
            <button type="button"
                    onclick="closeCreateCaseModal()"
                    class="text-2xl leading-none text-slate-500 hover:text-slate-900">
                &times;
            </button>
        </div>

        <form method="POST" action="{{ route('admin.cases.store') }}" class="space-y-5">
            @csrf

            @include('cases.partials.form', [
                'mode' => 'create',
                'caseRecord' => null,
                'caseTypes' => $caseTypes,
                'caseStatuses' => $caseStatuses,
                'incidents' => $incidents,
            ])

            <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                <button type="button"
                        onclick="closeCreateCaseModal()"
                        class="rounded-xl border border-slate-300 px-5 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>

                <button type="submit"
                        class="rounded-xl bg-blue-950 px-5 py-2 text-sm font-bold text-white hover:bg-blue-900">
                    Save Case
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="editCaseModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-5 flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900">Edit Case</h2>
            <button type="button"
                    onclick="closeEditCaseModal()"
                    class="text-2xl leading-none text-slate-500 hover:text-slate-900">
                &times;
            </button>
        </div>

        <form id="editCaseForm" method="POST" action="#" class="space-y-5">
            @csrf
            @method('PATCH')

            @include('cases.partials.form', [
                'mode' => 'edit',
                'caseRecord' => null,
                'caseTypes' => $caseTypes,
                'caseStatuses' => $caseStatuses,
                'incidents' => $incidents,
            ])

            <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                <button type="button"
                        onclick="closeEditCaseModal()"
                        class="rounded-xl border border-slate-300 px-5 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>

                <button type="submit"
                        class="rounded-xl bg-blue-950 px-5 py-2 text-sm font-bold text-white hover:bg-blue-900">
                    Update Case
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateCaseModal() {
        const modal = document.getElementById('createCaseModal');

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeCreateCaseModal() {
        const modal = document.getElementById('createCaseModal');

        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function openEditCaseModal(button) {
        const modal = document.getElementById('editCaseModal');
        const form = document.getElementById('editCaseForm');

        form.action = button.dataset.updateUrl;

        setModalValue('edit_case_number', button.dataset.caseNumber);
        setModalValue('edit_case_type', button.dataset.caseType);
        setModalValue('edit_subject_name', button.dataset.subjectName);
        setModalValue('edit_contact', button.dataset.contact);
        setModalValue('edit_address', button.dataset.address);
        setModalValue('edit_incident_id', button.dataset.incidentId);
        setModalValue('edit_incident_title', button.dataset.incidentTitle);
        setModalValue('edit_status', button.dataset.status);
        setModalValue('edit_hearing_date', button.dataset.hearingDate);
        setModalValue('edit_handled_by', button.dataset.handledBy);
        setModalValue('edit_resolution', button.dataset.resolution);
        setModalValue('edit_notes', button.dataset.notes);

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeEditCaseModal() {
        const modal = document.getElementById('editCaseModal');

        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function setModalValue(id, value) {
        const field = document.getElementById(id);

        if (field) {
            field.value = value || '';
        }
    }

    function syncIncidentTitle(selectId, titleInputId) {
        const select = document.getElementById(selectId);
        const titleInput = document.getElementById(titleInputId);

        if (!select || !titleInput) {
            return;
        }

        const selectedOption = select.options[select.selectedIndex];

        if (selectedOption && selectedOption.dataset.title) {
            titleInput.value = selectedOption.dataset.title;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const shouldOpenCreateModal = "{{ request('open_create') }}" === "1";
        const incidentId = "{{ request('incident_id') }}";

        if (shouldOpenCreateModal) {
            openCreateCaseModal();

            if (incidentId !== "") {
                setModalValue('create_incident_id', incidentId);
                syncIncidentTitle('create_incident_id', 'create_incident_title');
            }
        }

        const createIncidentSelect = document.getElementById('create_incident_id');

        if (createIncidentSelect) {
            createIncidentSelect.addEventListener('change', function () {
                syncIncidentTitle('create_incident_id', 'create_incident_title');
            });
        }

        const editIncidentSelect = document.getElementById('edit_incident_id');

        if (editIncidentSelect) {
            editIncidentSelect.addEventListener('change', function () {
                syncIncidentTitle('edit_incident_id', 'edit_incident_title');
            });
        }
    });
</script>
@endsection
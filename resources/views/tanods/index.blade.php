@extends('layouts.admin')

@section('title', 'Tanod Roster | DaoSystem')

@section('content')
@php
    $authUser = auth()->user();

    $isAdmin = $authUser?->isAdmin() ?? false;
    $isOfficial = $authUser?->isOfficial() ?? false;

    $canManageRoster = $isAdmin || $isOfficial;

    $rosterRoutePrefix = match (true) {
        $isAdmin => 'admin',
        $isOfficial => 'official',
        default => 'tanod',
    };

    $rosterIndexRoute = $rosterRoutePrefix . '.tanods.index';

    $tableColumnCount = $canManageRoster
        ? 7
        : 6;
@endphp
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                Tanod Roster
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                {{ $onDutyCount }} on duty • {{ $totalTanods }} total
            </p>
        </div>

        @if ($canManageRoster)
    <button type="button"
            onclick="openAddTanodModal()"
            class="inline-flex items-center justify-center rounded-xl bg-blue-950 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-900">
        + Add Tanod
    </button>
@endif
    </div>

    {{-- Flash Messages --}}
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

    {{-- Search --}}
    <form method="GET"
      action="{{ route($rosterIndexRoute) }}"
      class="max-w-xl">
        <div class="relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>

            <input type="text"
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="Search by name, contact, barangay, shift, or status..."
                   class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-11 pr-4 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        </div>
    </form>

    {{-- Table --}}
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
    Rank
</th>

                    <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        Tanod Member
                    </th>

                    <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        Assigned Barangay
                    </th>

                    <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        Shift
                    </th>

                    <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
    No. of Responses
</th>

                    <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        Status
                    </th>

                    <th class="px-5 py-4 text-right text-xs font-bold uppercase tracking-wide text-slate-500">
                        Actions
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($tanods as $tanod)
                    @php
    $user = $tanod->user;
    $assignedBarangay = $tanod->employee?->barangay;
@endphp

                    <tr>
                        <td class="whitespace-nowrap px-5 py-4 text-sm font-bold text-slate-600">
    #{{ (int) ($tanod->response_rank ?? 0) }}
</td>

                        <td class="px-5 py-4">
                            <div class="flex items-center gap-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-blue-950">
                                    🛡
                                </div>

                                <div>
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ $user?->name ?: 'Unnamed Tanod' }}
                                    </p>

                                    @if ($tanod->contact_number)
                                        <p class="text-xs text-slate-500">
                                            📞 {{ $tanod->contact_number }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                            {{ $assignedBarangay?->barangay_name ?: 'Unassigned' }}
                        </td>

                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                            {{ $tanod->display_shift }}
                        </td>

                        <td class="whitespace-nowrap px-5 py-4">
    <span
        title="Accepted task responses"
        class="inline-flex min-w-10 items-center justify-center rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700 ring-1 ring-blue-100"
    >
        {{ (int) ($tanod->accepted_responses_count ?? 0) }}
    </span>
</td>

                        <td class="whitespace-nowrap px-5 py-4">
                            <span class="rounded-full border px-3 py-1 text-xs font-bold {{ $tanod->status_badge_class }}">
                                {{ $tanod->display_status }}
                            </span>
                            @if (($tanod->auto_duty_enabled ?? false) && $tanod->date_appointed)
                                <p class="mt-2 text-xs font-semibold text-violet-700">
                                    Auto duty: {{ $tanod->date_appointed->format('M j, Y') }} · {{ $tanod->display_shift }} shift
                                </p>
                            @endif
                        </td>

                        @if ($canManageRoster)
                        <td class="whitespace-nowrap px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button"
                                        onclick="openEditTanodModal(this)"
                                        data-update-url="{{ route(
    $rosterRoutePrefix . '.tanods.update',
    $tanod
) }}"
                                        data-full-name="{{ e($user?->name) }}"
                                        data-contact-number="{{ e($tanod->contact_number) }}"
                                        data-email="{{ e($user?->email) }}"
                                        data-barangay-id="{{ e($tanod->employee?->barangay_id) }}"
                                        data-date-appointed="{{ $tanod->date_appointed ? $tanod->date_appointed->format('Y-m-d') : '' }}"
                                        data-shift="{{ e($tanod->shift) }}"
                                        data-status="{{ e($tanod->status) }}"
                                        data-auto-duty-enabled="{{ ($tanod->auto_duty_enabled ?? false) ? '1' : '0' }}"
                                        data-notes="{{ e($tanod->notes) }}"
                                        title="Edit tanod"
                                        aria-label="Edit tanod"
                                        class="tn-action-icon tn-action-icon-edit inline-flex h-10 w-10 items-center justify-center rounded-xl border text-sm font-bold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         class="h-5 w-5"
                                         viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="currentColor"
                                         stroke-width="2"
                                         stroke-linecap="round"
                                         stroke-linejoin="round"
                                         aria-hidden="true">
                                        <path d="M12 20h9" />
                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                    </svg>
                                </button>

                                <form method="POST"
                                      action="{{ route(
    $rosterRoutePrefix . '.tanods.destroy',
    $tanod
) }}"
                                      class="m-0">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                            title="Delete tanod"
                                            aria-label="Delete tanod"
                                            class="tn-action-icon tn-action-icon-delete inline-flex h-10 w-10 items-center justify-center rounded-xl border text-sm font-bold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-red-200">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                             class="h-5 w-5"
                                             viewBox="0 0 24 24"
                                             fill="none"
                                             stroke="currentColor"
                                             stroke-width="2"
                                             stroke-linecap="round"
                                             stroke-linejoin="round"
                                             aria-hidden="true">
                                            <path d="M3 6h18" />
                                            <path d="M8 6V4h8v2" />
                                            <path d="M19 6l-1 14H6L5 6" />
                                            <path d="M10 11v6" />
                                            <path d="M14 11v6" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $tableColumnCount }}"
                            class="px-5 py-14 text-center">
                            <p class="text-sm font-semibold text-slate-700">
                                No tanod members found.
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                Add your first tanod member to start building the roster.
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($tanods->hasPages())
        <div class="border-t border-slate-200 px-5 py-4">
            {{ $tanods->links() }}
        </div>
    @endif
</div>

@if ($canManageRoster)
{{-- Add Tanod Modal --}}
<div id="addTanodModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900">
                Add New Tanod Member
            </h2>

            <button type="button"
                    onclick="closeAddTanodModal()"
                    class="text-2xl leading-none text-slate-500 hover:text-slate-900">
                &times;
            </button>
        </div>

        <form method="POST"
      action="{{ route($rosterRoutePrefix . '.tanods.store') }}"
      class="space-y-5">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Full Name *</label>
                    <input type="text" name="full_name" required placeholder="Full name"
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Contact Number</label>
                    <input type="text" name="contact_number" placeholder="09XXXXXXXXX"
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Email</label>
                    <input type="email" name="email" placeholder="email@example.com"
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <p class="mt-1 text-xs text-slate-500">
                        Leave blank to auto-generate a local tanod email.
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Assigned Barangay *</label>
                    <select name="barangay_id"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="">Select barangay</option>
                        @foreach ($barangays as $barangay)
                            <option value="{{ $barangay->id }}" @selected((string) old('barangay_id') === (string) $barangay->id)>
                                {{ $barangay->barangay_name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">
                        Incident tasks in this barangay will be offered to this tanod first.
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Date Appointed / Duty Start</label>
                    <input type="date" name="date_appointed"
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <p class="mt-1 text-xs text-slate-500">Future dates are allowed for planned duty scheduling.</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Shift</label>
                    <select name="shift"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        @foreach ($shifts as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Status</label>
                    <select name="status"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2 rounded-xl border border-violet-200 bg-violet-50 p-4">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="checkbox" name="auto_duty_enabled" value="1"
                               class="mt-1 h-4 w-4 rounded border-violet-300 text-violet-700 focus:ring-violet-300">
                        <span>
                            <span class="block text-sm font-bold text-violet-900">Auto activate On Duty</span>
                            <span class="mt-1 block text-xs leading-5 text-violet-700">At the selected date and shift start, status changes to On Duty once. You can still edit the status, shift, date, or disable this schedule.</span>
                        </span>
                    </label>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Notes</label>
                    <textarea name="notes" rows="3" placeholder="Additional notes..."
                              class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                <button type="button"
                        onclick="closeAddTanodModal()"
                        class="rounded-xl border border-slate-300 px-5 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>

                <button type="submit"
                        class="rounded-xl bg-blue-950 px-5 py-2 text-sm font-bold text-white hover:bg-blue-900">
                    Add Tanod
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Tanod Modal --}}
<div id="editTanodModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900">
                Edit Tanod
            </h2>

            <button type="button"
                    onclick="closeEditTanodModal()"
                    class="text-2xl leading-none text-slate-500 hover:text-slate-900">
                &times;
            </button>
        </div>
        @endif

        <form id="editTanodForm" method="POST" action="#" class="space-y-5">
            @csrf
            @method('PATCH')

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Full Name *</label>
                    <input id="edit_full_name" type="text" name="full_name" required
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Contact Number</label>
                    <input id="edit_contact_number" type="text" name="contact_number"
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Email</label>
                    <input id="edit_email" type="email" name="email"
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Assigned Barangay *</label>
                    <select id="edit_barangay_id"
                            name="barangay_id"
                            required
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="">Select barangay</option>
                        @foreach ($barangays as $barangay)
                            <option value="{{ $barangay->id }}">
                                {{ $barangay->barangay_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Date Appointed / Duty Start</label>
                    <input id="edit_date_appointed" type="date" name="date_appointed"
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <p class="mt-1 text-xs text-slate-500">Future dates are allowed for planned duty scheduling.</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Shift</label>
                    <select id="edit_shift" name="shift"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        @foreach ($shifts as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Status</label>
                    <select id="edit_status" name="status"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2 rounded-xl border border-violet-200 bg-violet-50 p-4">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input id="edit_auto_duty_enabled" type="checkbox" name="auto_duty_enabled" value="1"
                               class="mt-1 h-4 w-4 rounded border-violet-300 text-violet-700 focus:ring-violet-300">
                        <span>
                            <span class="block text-sm font-bold text-violet-900">Auto activate On Duty</span>
                            <span class="mt-1 block text-xs leading-5 text-violet-700">At the selected date and shift start, status changes to On Duty once. You can still edit the status, shift, date, or disable this schedule.</span>
                        </span>
                    </label>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Notes</label>
                    <textarea id="edit_notes" name="notes" rows="3"
                              class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                <button type="button"
                        onclick="closeEditTanodModal()"
                        class="rounded-xl border border-slate-300 px-5 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>

                <button type="submit"
                        class="rounded-xl bg-blue-950 px-5 py-2 text-sm font-bold text-white hover:bg-blue-900">
                    Update Tanod
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddTanodModal() {
        const modal = document.getElementById('addTanodModal');

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeAddTanodModal() {
        const modal = document.getElementById('addTanodModal');

        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function openEditTanodModal(button) {
        const modal = document.getElementById('editTanodModal');
        const form = document.getElementById('editTanodForm');

        form.action = button.dataset.updateUrl;

        setFieldValue('edit_full_name', button.dataset.fullName);
        setFieldValue('edit_contact_number', button.dataset.contactNumber);
        setFieldValue('edit_email', button.dataset.email);
        setFieldValue('edit_barangay_id', button.dataset.barangayId);
        setFieldValue('edit_date_appointed', button.dataset.dateAppointed);
        setFieldValue('edit_shift', button.dataset.shift);
        setFieldValue('edit_status', button.dataset.status);
        setCheckboxValue(
            'edit_auto_duty_enabled',
            button.dataset.autoDutyEnabled === '1'
        );
        setFieldValue('edit_notes', button.dataset.notes);

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeEditTanodModal() {
        const modal = document.getElementById('editTanodModal');

        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function setFieldValue(id, value) {
        const field = document.getElementById(id);

        if (field) {
            field.value = value || '';
        }
    }

    function setCheckboxValue(id, checked) {
        const field = document.getElementById(id);

        if (field) {
            field.checked = Boolean(checked);
        }
    }
</script>
@endsection
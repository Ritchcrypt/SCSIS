@php
    $prefix = $mode === 'edit' ? 'edit_' : 'create_';
    $selectedIncidentId = old('incident_id') ?? ($mode === 'create' ? request('incident_id') : '');
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label for="{{ $prefix }}case_number" class="mb-2 block text-sm font-semibold text-slate-700">Case Number</label>
        <input id="{{ $prefix }}case_number"
               type="text"
               name="case_number"
               value="{{ $mode === 'create' ? 'AUTO-GENERATED' : '' }}"
               {{ $mode === 'create' ? 'readonly' : '' }}
               class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        @if ($mode === 'create')
            <p class="mt-1 text-xs text-slate-400">Leave as auto-generated.</p>
        @endif
    </div>

    <div>
        <label for="{{ $prefix }}case_type" class="mb-2 block text-sm font-semibold text-slate-700">Case Type *</label>
        <select id="{{ $prefix }}case_type"
                name="case_type"
                required
                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            <option value="">Select type</option>
            @foreach ($caseTypes as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="{{ $prefix }}subject_name" class="mb-2 block text-sm font-semibold text-slate-700">Subject Name *</label>
        <input id="{{ $prefix }}subject_name"
               type="text"
               name="subject_name"
               required
               placeholder="Name of person involved"
               class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
    </div>

    <div>
        <label for="{{ $prefix }}contact" class="mb-2 block text-sm font-semibold text-slate-700">Contact</label>
        <input id="{{ $prefix }}contact"
               type="text"
               name="contact"
               placeholder="09XXXXXXXXX"
               class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
    </div>

    <div class="md:col-span-2">
        <label for="{{ $prefix }}address" class="mb-2 block text-sm font-semibold text-slate-700">Address</label>
        <input id="{{ $prefix }}address"
               type="text"
               name="address"
               placeholder="Full address"
               class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
    </div>

    <div>
        <label for="{{ $prefix }}incident_id" class="mb-2 block text-sm font-semibold text-slate-700">Related Incident</label>
        <select id="{{ $prefix }}incident_id"
                name="incident_id"
                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            <option value="" data-title="">No linked incident</option>
            @foreach ($incidents as $incident)
                @php
                    $optionTitle = $incident->incident_title ?? $incident->title ?? 'Untitled Incident';
                    $optionCode = $incident->incident_code ?? 'INC-' . $incident->id;
                @endphp
                <option value="{{ $incident->id }}"
                        data-title="{{ $optionTitle }}"
                        @selected((string) $selectedIncidentId === (string) $incident->id)>
                    {{ $optionCode }} — {{ $optionTitle }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="{{ $prefix }}incident_title" class="mb-2 block text-sm font-semibold text-slate-700">Incident Title</label>
        <input id="{{ $prefix }}incident_title"
               type="text"
               name="incident_title"
               placeholder="Related incident"
               class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
    </div>

    <div>
        <label for="{{ $prefix }}status" class="mb-2 block text-sm font-semibold text-slate-700">Status</label>
        <select id="{{ $prefix }}status"
                name="status"
                required
                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            @foreach ($caseStatuses as $value => $label)
                <option value="{{ $value }}" @selected($value === 'open')>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="{{ $prefix }}hearing_date" class="mb-2 block text-sm font-semibold text-slate-700">Hearing Date</label>
        <input id="{{ $prefix }}hearing_date"
               type="date"
               name="hearing_date"
               class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
    </div>

    <div class="md:col-span-2">
        <label for="{{ $prefix }}handled_by" class="mb-2 block text-sm font-semibold text-slate-700">Handled By</label>
        <input id="{{ $prefix }}handled_by"
               type="text"
               name="handled_by"
               placeholder="Brgy. Lupon Chair / Brgy. Captain / Assigned Officer"
               class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
    </div>

    <div class="md:col-span-2">
        <label for="{{ $prefix }}resolution" class="mb-2 block text-sm font-semibold text-slate-700">Resolution</label>
        <textarea id="{{ $prefix }}resolution"
                  name="resolution"
                  rows="3"
                  placeholder="Case resolution details..."
                  class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
    </div>

    <div class="md:col-span-2">
        <label for="{{ $prefix }}notes" class="mb-2 block text-sm font-semibold text-slate-700">Notes</label>
        <textarea id="{{ $prefix }}notes"
                  name="notes"
                  rows="3"
                  placeholder="Additional notes..."
                  class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
    </div>
</div>

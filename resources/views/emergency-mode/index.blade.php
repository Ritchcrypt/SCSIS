@extends('layouts.admin')

@section('title', 'Emergency Mode | DaoSystem')

@section('content')
@php
    $agencyStyles = [
        'pnp' => [
            'dot' => 'bg-blue-600',
            'selected' => 'border-blue-600 ring-2 ring-blue-100',
            'button' => 'bg-blue-950 hover:bg-blue-900',
        ],
        'bfp' => [
            'dot' => 'bg-red-600',
            'selected' => 'border-red-600 ring-2 ring-red-100',
            'button' => 'bg-blue-950 hover:bg-blue-900',
        ],
        'mdrrmo' => [
            'dot' => 'bg-orange-500',
            'selected' => 'border-orange-500 ring-2 ring-orange-100',
            'button' => 'bg-blue-950 hover:bg-blue-900',
        ],
    ];
@endphp

<div class="space-y-6">
    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
            <p class="font-bold">Please fix the following errors:</p>

            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Emergency Hotlines --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6 flex items-center gap-3">
            <span class="text-2xl text-blue-800">📞</span>

            <div>
                <h1 class="text-2xl font-bold text-slate-900">Emergency Hotlines</h1>
                <p class="text-sm text-slate-500">Select an agency to log an emergency contact action.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.emergency-mode.notify') }}" id="emergencyNotifyForm">
            @csrf

            <input type="hidden" name="agency" id="selectedAgency" value="pnp">

            {{-- Agency Cards --}}
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($agencies as $key => $agency)
                    @php
                        $style = $agencyStyles[$key] ?? $agencyStyles['pnp'];
                        $isDefault = $key === 'pnp';
                    @endphp

                    <button type="button"
                            data-agency="{{ $key }}"
                            data-agency-name="{{ $agency['name'] }}"
                            data-agency-short="{{ $agency['short_name'] }}"
                            data-agency-hotline="{{ $agency['hotline'] }}"
                            onclick="selectAgency(this)"
                            class="agency-card rounded-2xl border p-5 text-left shadow-sm transition hover:bg-slate-50 {{ $isDefault ? $style['selected'] : 'border-slate-200' }}">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="h-4 w-4 rounded-full {{ $style['dot'] }}"></div>
                            <p class="text-lg font-bold text-slate-900">{{ $agency['name'] }}</p>
                        </div>

                        <p class="text-2xl font-bold text-blue-950">{{ $agency['hotline'] }}</p>
                    </button>
                @endforeach
            </div>

            {{-- Notify Form --}}
            <div class="mt-8 border-t border-slate-200 pt-8">
                <p class="text-lg text-slate-700">
                    Notifying:
                    <span id="notifyingAgency" class="font-bold text-slate-900">PNP (Police)</span>
                </p>

                <div class="mt-6">
                    <label for="incident_id" class="mb-2 block text-sm font-semibold text-slate-700">
                        Related Incident Optional
                    </label>

                    <select id="incident_id"
                            name="incident_id"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="">No linked incident</option>

                        @foreach ($incidents as $incident)
                            @php
                                $incidentTitle = $incident->incident_title ?: ($incident->title ?: 'Untitled Incident');
                                $incidentCode = $incident->incident_code ?: ('INC-' . $incident->id);
                            @endphp

                            <option value="{{ $incident->id }}">
                                {{ $incidentCode }} — {{ $incidentTitle }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-6">
                    <label for="message" class="mb-2 block text-sm font-semibold text-slate-700">
                        Message Optional
                    </label>

                    <textarea id="message"
                              name="message"
                              rows="4"
                              placeholder="Additional details for the agency..."
                              class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">{{ old('message') }}</textarea>
                </div>

                <button type="submit"
                        id="notifyButton"
                        class="mt-6 inline-flex items-center justify-center rounded-xl bg-blue-950 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-900">
                    📡 Notify PNP
                </button>
            </div>
        </form>
    </div>

    {{-- Logs --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-base font-bold text-slate-900">
                Emergency Notification Logs
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Records of agencies logged as contacted by the system.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Agency</th>
                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Message</th>
                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Incident</th>
                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Notified By</th>
                        <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-5 py-4 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-4">
                                <p class="text-sm font-bold text-slate-900">
                                    {{ $log->agency_name }}
                                </p>

                                <p class="text-xs text-blue-950">
                                    {{ $log->hotline }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $log->message ?: 'No message provided' }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                @if ($log->incident)
                                    {{ $log->incident->incident_code ?? 'INC-' . $log->incident->id }}
                                    —
                                    {{ $log->incident->display_title }}
                                @else
                                    —
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-5 py-4">
                                <form method="POST" action="{{ route('admin.emergency-mode.update-status', $log) }}">
                                    @csrf
                                    @method('PATCH')

                                    <select name="status"
                                            onchange="this.form.submit()"
                                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                                        @foreach ($statuses as $value => $label)
                                            <option value="{{ $value }}" @selected($log->status === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                {{ $log->initiator?->name ?: 'System' }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                {{ $log->notified_at ? $log->notified_at->format('M d, Y h:i A') : '—' }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <form method="POST"
                                      action="{{ route('admin.emergency-mode.destroy', $log) }}"
                                      class="inline"
                                      onsubmit="return confirm('Delete this emergency log?');">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                        🗑
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center">
                                <p class="text-sm font-semibold text-slate-700">
                                    No emergency agency logs yet.
                                </p>

                                <p class="mt-1 text-xs text-slate-500">
                                    Select an agency and click notify to create the first log.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>

<script>
    function selectAgency(button) {
        const cards = document.querySelectorAll('.agency-card');
        const selectedAgencyInput = document.getElementById('selectedAgency');
        const notifyingAgency = document.getElementById('notifyingAgency');
        const notifyButton = document.getElementById('notifyButton');

        const agency = button.dataset.agency;
        const agencyName = button.dataset.agencyName;
        const agencyShort = button.dataset.agencyShort;

        cards.forEach(function (card) {
            card.classList.remove('border-blue-600', 'border-red-600', 'border-orange-500', 'ring-2', 'ring-blue-100', 'ring-red-100', 'ring-orange-100');
            card.classList.add('border-slate-200');
        });

        button.classList.remove('border-slate-200');

        if (agency === 'pnp') {
            button.classList.add('border-blue-600', 'ring-2', 'ring-blue-100');
        }

        if (agency === 'bfp') {
            button.classList.add('border-red-600', 'ring-2', 'ring-red-100');
        }

        if (agency === 'mdrrmo') {
            button.classList.add('border-orange-500', 'ring-2', 'ring-orange-100');
        }

        selectedAgencyInput.value = agency;
        notifyingAgency.textContent = agencyName;
        notifyButton.textContent = '📡 Notify ' + agencyShort;
    }
</script>
@endsection
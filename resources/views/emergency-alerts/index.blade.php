@extends('layouts.admin')

@section('title', 'Distress Signal | TabangNow')

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-red-700">Emergency Response</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Distress Signal</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Emergency distress signals submitted from the TabangNow mobile application.
                </p>
            </div>

            <div class="rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
                {{ $alerts->total() }} total alert{{ $alerts->total() === 1 ? '' : 's' }}
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-bold uppercase tracking-wide text-slate-600">
                        <th class="px-5 py-4">Alert</th>
                        <th class="px-5 py-4">Emergency</th>
                        <th class="px-5 py-4">Mobile Number</th>
                        <th class="px-5 py-4">Location</th>
                        <th class="px-5 py-4">Status</th>
                        <th class="px-5 py-4">Triggered</th>
                        <th class="px-5 py-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($alerts as $alert)
                        <tr class="align-top hover:bg-slate-50/70">
                            <td class="px-5 py-4">
                                <div class="font-black text-slate-900">{{ $alert->alert_code }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $alert->display_name }}</div>
                            </td>

                            <td class="max-w-md px-5 py-4 text-slate-800">
                                {{ \Illuminate\Support\Str::limit($alert->emergency_details ?: 'No emergency description available for this earlier alert.', 140) }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 font-semibold text-slate-800">
                                {{ $alert->contact_number ?: 'Not provided' }}
                            </td>

                            <td class="px-5 py-4">
                                @if ($alert->latitude !== null && $alert->longitude !== null)
                                    <div class="font-semibold text-slate-800">
                                        {{ $alert->location_source === 'last_known' ? 'Last known' : 'Current GPS' }}
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ number_format((float) $alert->latitude, 6) }}, {{ number_format((float) $alert->longitude, 6) }}
                                    </div>
                                @else
                                    <span class="text-slate-500">Unavailable</span>
                                @endif
                            </td>

                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide
                                    {{ $alert->status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : ($alert->status === 'acknowledged' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $alert->status }}
                                </span>
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-slate-700">
                                {{ optional($alert->triggered_at)->format('M d, Y h:i A') ?? 'Unknown' }}
                            </td>

                            <td class="px-5 py-4 text-center">
                                <a href="{{ route('emergency-alerts.show', $alert) }}"
                                   class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-800 shadow-sm hover:bg-slate-50">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                                No distress signals have been received yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($alerts->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $alerts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

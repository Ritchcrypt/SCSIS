@extends('layouts.admin')

@section('title', 'Emergency Hotlines | DaoSystem')

@section('content')
@php
    $agencyStyles = [
        'pnp' => [
            'dot' => 'bg-blue-600',
            'selected' => 'border-blue-600 ring-2 ring-blue-100',
        ],
        'bfp' => [
            'dot' => 'bg-red-600',
            'selected' => 'border-red-600 ring-2 ring-red-100',
        ],
        'mdrrmo' => [
            'dot' => 'bg-orange-500',
            'selected' => 'border-orange-500 ring-2 ring-orange-100',
        ],
    ];
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6 flex items-center gap-3">
            <span class="text-2xl text-blue-800">📞</span>

            <div>
                <h1 class="text-2xl font-bold text-slate-900">
                    Emergency Hotlines
                </h1>

                <p class="text-sm text-slate-500">
                    Emergency contact numbers for immediate reference.
                </p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($agencies as $key => $agency)
                @php
                    $style = $agencyStyles[$key] ?? $agencyStyles['pnp'];
                    $isDefault = $key === 'pnp';
                @endphp

                <button type="button"
                        data-agency="{{ $key }}"
                        onclick="selectHotlineAgency(this)"
                        aria-pressed="{{ $isDefault ? 'true' : 'false' }}"
                        class="agency-card cursor-pointer rounded-2xl border p-5 text-left shadow-sm transition duration-150 hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-md focus:outline-none
                               {{ $isDefault ? $style['selected'] : 'border-slate-200' }}">
                    <div class="mb-4 flex items-center gap-3">
                        <div class="h-4 w-4 rounded-full {{ $style['dot'] }}"></div>

                        <p class="text-lg font-bold text-slate-900">
                            {{ $agency['name'] }}
                        </p>
                    </div>

                    <p class="text-2xl font-bold text-blue-950">
                        {{ $agency['hotline'] }}
                    </p>
                </button>
            @endforeach
        </div>
    </div>
</div>

<script>
    function selectHotlineAgency(button) {
        const cards = document.querySelectorAll('.agency-card');
        const agency = button.dataset.agency;

        cards.forEach(function (card) {
            card.classList.remove(
                'border-blue-600',
                'border-red-600',
                'border-orange-500',
                'ring-2',
                'ring-blue-100',
                'ring-red-100',
                'ring-orange-100'
            );

            card.classList.add('border-slate-200');
            card.setAttribute('aria-pressed', 'false');
        });

        button.classList.remove('border-slate-200');
        button.setAttribute('aria-pressed', 'true');

        if (agency === 'pnp') {
            button.classList.add('border-blue-600', 'ring-2', 'ring-blue-100');
        }

        if (agency === 'bfp') {
            button.classList.add('border-red-600', 'ring-2', 'ring-red-100');
        }

        if (agency === 'mdrrmo') {
            button.classList.add('border-orange-500', 'ring-2', 'ring-orange-100');
        }
    }
</script>
@endsection

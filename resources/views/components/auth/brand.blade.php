@props([
    'systemName' => 'TabangNow',
    'systemSubtitle' => 'Dao, Capiz',
    'logoUrl' => null,
])

<a href="{{ route('home') }}"
   class="tn-auth-brand"
   aria-label="{{ $systemName }} home"
   wire:navigate>

    @if ($logoUrl)
        <span class="tn-auth-brand-mark">
            <img src="{{ $logoUrl }}"
                 alt="{{ $systemName }} logo"
                 class="tn-auth-brand-image">
        </span>
    @endif

    <span class="tn-auth-brand-copy">
        <span class="tn-auth-brand-name">
            {{ $systemName }}
        </span>

        <span class="tn-auth-brand-subtitle">
            {{ $systemSubtitle }}
        </span>
    </span>
</a>
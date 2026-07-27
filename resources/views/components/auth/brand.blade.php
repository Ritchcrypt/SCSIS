@props([
    'systemName' => 'TabangNow',
    'systemSubtitle' => 'Dao, Capiz',
    'logoUrl' => null,
])

<a href="{{ route('home') }}"
   class="tn-auth-brand"
   aria-label="{{ $systemName }} home"
   wire:navigate>

    <span class="tn-auth-brand-mark">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}"
                 alt="{{ $systemName }} logo"
                 class="tn-auth-brand-image">
        @else
            <svg viewBox="0 0 48 48"
                 aria-hidden="true"
                 class="tn-auth-brand-fallback">
                <path d="M24 4 40 10v12c0 10.4-6.5 18.7-16 22C14.5 40.7 8 32.4 8 22V10L24 4Z"
                      fill="currentColor"
                      opacity=".18"/>

                <path d="M24 7.2 37 12v10c0 8.6-5.1 15.7-13 18.8C16.1 37.7 11 30.6 11 22V12l13-4.8Z"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2.5"/>

                <path d="M21.5 15h5v6.5H33v5h-6.5V33h-5v-6.5H15v-5h6.5V15Z"
                      fill="currentColor"/>
            </svg>
        @endif
    </span>

    <span class="tn-auth-brand-copy">
        <span class="tn-auth-brand-name">
            {{ $systemName }}
        </span>

        <span class="tn-auth-brand-subtitle">
            {{ $systemSubtitle }}
        </span>
    </span>
</a>
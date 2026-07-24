@php
    $themeUser = auth()->user();

    $themeMode = 'system';
    $themeCustomColor = '#2563eb';

    if (
        $themeUser
        && \Illuminate\Support\Facades\Schema::hasTable('users')
        && \Illuminate\Support\Facades\Schema::hasColumn('users', 'theme_mode')
    ) {
        $themeMode = $themeUser->theme_mode ?: 'system';
    }

    if (
        $themeUser
        && \Illuminate\Support\Facades\Schema::hasTable('users')
        && \Illuminate\Support\Facades\Schema::hasColumn('users', 'theme_custom_color')
        && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $themeUser->theme_custom_color)
    ) {
        $themeCustomColor = strtolower($themeUser->theme_custom_color);
    }

    $isAdminThemeUser = $themeUser?->role === 'admin';

    if ($themeMode === 'custom' && ! $isAdminThemeUser) {
        $themeMode = 'system';
    }

    $themeIcon = match ($themeMode) {
        'light' => '☀',
        'dark' => '🌙',
        'custom' => '🎨',
        default => '🖥',
    };

    $themeLabel = match ($themeMode) {
        'light' => 'White',
        'dark' => 'Dark',
        'custom' => 'Custom',
        default => 'System',
    };

    $themeOptions = [
        [
            'mode' => 'light',
            'label' => 'White',
            'description' => 'Use the bright default interface.',
            'icon' => '☀',
        ],
        [
            'mode' => 'dark',
            'label' => 'Dark',
            'description' => 'Use a darker interface.',
            'icon' => '🌙',
        ],
        [
            'mode' => 'system',
            'label' => 'System',
            'description' => 'Follow your device appearance.',
            'icon' => '🖥',
        ],
    ];
@endphp

@if ($themeUser && \Illuminate\Support\Facades\Route::has('theme-preference.update'))
    <details class="relative z-[110]">
        <summary class="inline-flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-100 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-blue-200"
                 title="Change theme"
                 aria-label="Change theme">
            <span class="text-lg">{{ $themeIcon }}</span>
        </summary>

        <div class="fixed right-20 top-[4.5rem] z-[120] w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-slate-900/10">
            <div class="border-b border-slate-200 px-4 py-3">
                <p class="text-sm font-bold text-slate-900">
                    Theme Preference
                </p>

                <p class="mt-1 text-xs text-slate-500">
                    Current: {{ $themeLabel }}
                </p>
            </div>

            <div class="space-y-1 p-2">
                @foreach ($themeOptions as $option)
                    @php
                        $isCurrentThemeOption = $themeMode === $option['mode'];
                    @endphp

                    <form method="POST"
                          action="{{ route('theme-preference.update') }}"
                          class="m-0">
                        @csrf
                        @method('PATCH')

                        <input type="hidden" name="theme_mode" value="{{ $option['mode'] }}">

                        <button type="submit"
                                class="flex w-full items-start gap-3 rounded-xl px-3 py-3 text-left transition
                                {{ $isCurrentThemeOption
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-slate-700 hover:bg-slate-50 hover:text-slate-950' }}">
                            <span class="mt-0.5 text-base">{{ $option['icon'] }}</span>

                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold">
                                    {{ $option['label'] }}
                                </span>

                                <span class="mt-0.5 block text-xs text-slate-500">
                                    {{ $option['description'] }}
                                </span>
                            </span>

                            @if ($isCurrentThemeOption)
                                <span class="text-sm font-bold">✓</span>
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>

            @if ($isAdminThemeUser)
                <div class="border-t border-slate-200 p-4">
                    <form method="POST"
                          action="{{ route('theme-preference.update') }}"
                          class="space-y-3">
                        @csrf
                        @method('PATCH')

                        <input type="hidden" name="theme_mode" value="custom">

                        <div>
                            <label for="theme_custom_color" class="block text-xs font-bold uppercase tracking-wide text-slate-500">
                                Admin custom color
                            </label>

                            <div class="mt-2 flex items-center gap-3">
                                <input id="theme_custom_color"
                                       type="color"
                                       name="theme_custom_color"
                                       value="{{ $themeCustomColor }}"
                                       class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-1 shadow-sm">

                                <button type="submit"
                                        class="inline-flex h-10 items-center justify-center rounded-lg bg-blue-600 px-4 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700">
                                    Apply Custom
                                </button>
                            </div>

                            <p class="mt-2 text-xs text-slate-500">
                                Applies only to your admin account view.
                            </p>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </details>
@endif
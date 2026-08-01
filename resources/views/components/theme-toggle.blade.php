@php
    $themeUser = auth()->user();

    $themeMode = 'system';
    $themeCustomColor = '#2563eb';

    if (
        $themeUser
        && \Illuminate\Support\Facades\Schema::hasTable('users')
        && \Illuminate\Support\Facades\Schema::hasColumn(
            'users',
            'theme_mode'
        )
    ) {
        $themeMode = strtolower(
            trim($themeUser->theme_mode ?: 'system')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize legacy\Support\Facades\Schema::hasColumn(
            'users',
            'theme_mode'
        )
    ) {
        $themeMode = strtolower(
            trim($theme theme value
    |--------------------------------------------------------------------------
    */

    if ($themeMode === 'white') {
        $themeMode = 'light';
    }

    if (
        $themeUser
        && \Illuminate\Support\Facades\Schema::hasTable('users')
        && \Illuminate\Support\Facades\Schema::hasColumn(
            'users',
            'theme_custom_color'
        )
        && preg_match(
            '/^#[0-9A-Fa-f]{6}$/',
            (string) $themeUser->theme_custom_color
        )
    ) {
        $themeCustomColor = strtolower(
            $themeUser->theme_custom_color
        );
    }

    $isAdminThemeUser =
        $themeUser?->role === 'admin';

    if (
        $themeMode === 'custom'
        && ! $isAdminThemeUser
    ) {
        $themeMode = 'system';
    }

    $themeIcon = match ($themeMode) {
        'light' => '☀️',
        'dark' => '🌙',
        'custom' => '🎨',
        default => '🖥️',
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
            'description' =>
                'Use the bright default interface.',
            'icon' => '☀️',
        ],
        [
            'mode' => 'dark',
            'label' => 'Dark',
            'description' =>
                'Use a darker interface.',
            'icon' => '🌙',
        ],
        [
            'mode' => 'system',
            'label' => 'System',
            'description' =>
                'Follow your device appearance.',
            'icon' => '🖥️',
        ],
    ];
@endphp

@if (
    $themeUser
    && \Illuminate\Support\Facades\Route::has(
        'theme-preference.update'
    )
)
    <details
        id="themePreferenceDetails"
        class="relative z-[110]"
    >
        <summary
            id="themePreferenceTriggerButton"
            class="inline-flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-100 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-blue-200"
            title="Current theme: {{ $themeLabel }}"
            aria-label="Theme preference: {{ $themeLabel }}"
        >
            <span
                id="themePreferenceTriggerIcon"
                data-theme-trigger-icon
                class="inline-flex h-5 w-5 items-center justify-center text-lg leading-none"
                aria-hidden="true"
            >
                {{ $themeIcon }}
            </span>
        </summary>

        <div class="fixed right-20 top-[4.5rem] z-[120] w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-slate-900/10">
            <div class="border-b border-slate-200 px-4 py-3">
                <p class="text-sm font-bold text-slate-900">
                    Theme Preference
                </p>

                <p
                    data-theme-current
                    class="mt-1 text-xs text-slate-500"
                >
                    Current: {{ $themeLabel }}
                </p>
            </div>

            <div class="space-y-1 p-2">
                @foreach ($themeOptions as $option)
                    @php
                        $isCurrentThemeOption =
                            $themeMode === $option['mode'];
                    @endphp

                    <form
                        method="POST"
                        action="{{ route('theme-preference.update') }}"
                        class="m-0"
                        data-theme-form
                    >
                        @csrf
                        @method('PATCH')

                        <input
                            type="hidden"
                            name="theme_mode"
                            value="{{ $option['mode'] }}"
                        >

                        <button
                            type="submit"
                            data-theme-option
                            data-theme-mode="{{ $option['mode'] }}"
                            aria-checked="{{ $isCurrentThemeOption ? 'true' : 'false' }}"
                            class="relative flex w-full items-start gap-3 rounded-xl px-3 py-3 pr-11 text-left text-slate-700 transition hover:bg-slate-50 hover:text-slate-950"
                            @if ($isCurrentThemeOption)
                                style="background-color: #eff6ff; color: #1d4ed8;"
                            @endif
                        >
                            <span class="mt-0.5 text-base">
                                {{ $option['icon'] }}
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold">
                                    {{ $option['label'] }}
                                </span>

                                <span class="mt-0.5 block text-xs text-slate-500">
                                    {{ $option['description'] }}
                                </span>
                            </span>

                            <span
                                data-theme-live-check
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-bold text-blue-600"
                                aria-hidden="true"
                                @if (! $isCurrentThemeOption)
                                    hidden
                                @endif
                            >
                                ✓
                            </span>
                        </button>
                    </form>
                @endforeach
            </div>

            @if ($isAdminThemeUser)
                <div class="border-t border-slate-200 p-4">
                    <form
                        method="POST"
                        action="{{ route('theme-preference.update') }}"
                        class="space-y-3"
                        data-theme-form
                    >
                        @csrf
                        @method('PATCH')

                        <input
                            type="hidden"
                            name="theme_mode"
                            value="custom"
                        >

                        <div>
                            <label
                                for="theme_custom_color"
                                class="block text-xs font-bold uppercase tracking-wide text-slate-500"
                            >
                                Admin custom color
                            </label>

                            <div class="mt-2 flex items-center gap-3">
                                <input
                                    id="theme_custom_color"
                                    type="color"
                                    name="theme_custom_color"
                                    value="{{ $themeCustomColor }}"
                                    class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-1 shadow-sm"
                                >

                                <button
                                    type="submit"
                                    data-theme-mode="custom"
                                    class="inline-flex h-10 items-center justify-center rounded-lg bg-blue-600 px-4 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700"
                                >
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

    <script>
        (() => {
            const root =
                document.documentElement;

            const icons = {
                light: '☀️',
                dark: '🌙',
                system: '🖥️',
                custom: '🎨',
            };

            const labels = {
                light: 'White',
                dark: 'Dark',
                system: 'System',
                custom: 'Custom',
            };

            function normalizeThemeMode(mode) {
                const normalized =
                    String(mode || '')
                        .trim()
                        .toLowerCase();

                return normalized === 'white'
                    ? 'light'
                    : normalized;
            }

            function synchronizeThemeToggle(mode) {
                const normalizedMode =
                    normalizeThemeMode(
                        mode
                        || root.getAttribute(
                            'data-theme'
                        )
                        || 'system'
                    );

                const selectedMode =
                    Object.prototype.hasOwnProperty.call(
                        icons,
                        normalizedMode
                    )
                        ? normalizedMode
                        : 'system';

                const selectedIcon =
                    icons[selectedMode];

                const selectedLabel =
                    labels[selectedMode];

                /*
                 * Update the small icon beside the bell.
                 */
                document
                    .querySelectorAll(
                        '[data-theme-trigger-icon]'
                    )
                    .forEach(function (icon) {
                        icon.textContent =
                            selectedIcon;
                    });

                /*
                 * Update the trigger tooltip and accessibility text.
                 */
                const triggerButton =
                    document.getElementById(
                        'themePreferenceTriggerButton'
                    );

                if (triggerButton) {
                    triggerButton.setAttribute(
                        'title',
                        'Current theme: '
                        + selectedLabel
                    );

                    triggerButton.setAttribute(
                        'aria-label',
                        'Theme preference: '
                        + selectedLabel
                    );
                }

                /*
                 * Update "Current: White/Dark/System".
                 */
                document
                    .querySelectorAll(
                        '[data-theme-current]'
                    )
                    .forEach(function (element) {
                        element.textContent =
                            'Current: '
                            + selectedLabel;
                    });

                /*
                 * Move the highlight and checkmark.
                 */
                document
                    .querySelectorAll(
                        '[data-theme-option]'
                    )
                    .forEach(function (option) {
                        const optionMode =
                            normalizeThemeMode(
                                option.getAttribute(
                                    'data-theme-mode'
                                )
                            );

                        const selected =
                            optionMode
                            === selectedMode;

                        option.setAttribute(
                            'aria-checked',
                            selected
                                ? 'true'
                                : 'false'
                        );

                        option.style.backgroundColor =
                            selected
                                ? '#eff6ff'
                                : '';

                        option.style.color =
                            selected
                                ? '#1d4ed8'
                                : '';

                        const check =
                            option.querySelector(
                                '[data-theme-live-check]'
                            );

                        if (check) {
                            check.hidden =
                                ! selected;
                        }
                    });
            }

            /*
             * Update from the event sent by the global theme handler.
             */
            if (
                ! window
                    .__tabangNowThemeToggleListenerInstalled
            ) {
                window
                    .__tabangNowThemeToggleListenerInstalled =
                    true;

                window.addEventListener(
                    'tabangnow:theme-changed',
                    function (event) {
                        synchronizeThemeToggle(
                            event.detail?.mode
                        );
                    }
                );

                /*
                 * This guarantees synchronization even if another script
                 * changes data-theme without sending the custom event.
                 */
                const observer =
                    new MutationObserver(
                        function (mutations) {
                            const changed =
                                mutations.some(
                                    function (
                                        mutation
                                    ) {
                                        return (
                                            mutation.type
                                                === 'attributes'
                                            && mutation
                                                .attributeName
                                                === 'data-theme'
                                        );
                                    }
                                );

                            if (changed) {
                                synchronizeThemeToggle(
                                    root.getAttribute(
                                        'data-theme'
                                    )
                                );
                            }
                        }
                    );

                observer.observe(
                    root,
                    {
                        attributes: true,
                        attributeFilter: [
                            'data-theme',
                        ],
                    }
                );

                document.addEventListener(
                    'livewire:navigated',
                    function () {
                        synchronizeThemeToggle();
                    }
                );
            }

            /*
             * Synchronize immediately on initial rendering.
             */
            if (
                document.readyState === 'loading'
            ) {
                document.addEventListener(
                    'DOMContentLoaded',
                    function () {
                        synchronizeThemeToggle();
                    },
                    {
                        once: true,
                    }
                );
            } else {
                synchronizeThemeToggle();
            }
        })();
    </script>
@endif
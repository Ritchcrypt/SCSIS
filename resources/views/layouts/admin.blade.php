@php
    $layoutThemeUser = auth()->user();

    $layoutThemeMode = 'system';
    $layoutThemeCustomColor = '#2563eb';
    $layoutThemeRole = strtolower(trim($layoutThemeUser?->role ?? 'guest'));

    if (
        $layoutThemeUser
        && \Illuminate\Support\Facades\Schema::hasTable('users')
        && \Illuminate\Support\Facades\Schema::hasColumn('users', 'theme_mode')
    ) {
        $layoutThemeMode = $layoutThemeUser->theme_mode ?: 'system';
    }

    if ($layoutThemeMode === 'custom' && $layoutThemeRole !== 'admin') {
        $layoutThemeMode = 'system';
    }

    if (
        $layoutThemeUser
        && \Illuminate\Support\Facades\Schema::hasTable('users')
        && \Illuminate\Support\Facades\Schema::hasColumn('users', 'theme_custom_color')
        && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $layoutThemeUser->theme_custom_color)
    ) {
        $layoutThemeCustomColor = strtolower($layoutThemeUser->theme_custom_color);
    }
@endphp

<!DOCTYPE html>
<html lang="en"
      data-theme="{{ $layoutThemeMode }}"
      data-theme-role="{{ $layoutThemeRole }}"
      @if ($layoutThemeMode === 'custom' && $layoutThemeRole === 'admin')
          style="--tn-accent: {{ $layoutThemeCustomColor }};"
      @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta
    name="notification-pulse-url"
    content="{{ route('notifications.pulse') }}"
>

<meta
    name="notification-user-id"
    content="{{ (int) auth()->id() }}"
>
    <meta
        name="theme-preference-url"
        content="{{ route('theme-preference.update') }}"
    >
    <title>TabangNow</title>
    <link
    rel="icon"
    type="image/png"
    sizes="32x32"
    href="/tabangnow-tab-icon-v7.png?v=11"
>

<link
    rel="shortcut icon"
    type="image/png"
    href="/tabangnow-tab-icon-v7.png?v=11"
>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-100 text-slate-900">
    <script>
        (() => {
            if (window.__tabangNowThemeNoReloadInstalled) {
                return;
            }

            window.__tabangNowThemeNoReloadInstalled = true;

            const themeUrl = document
                .querySelector('meta[name="theme-preference-url"]')
                ?.getAttribute('content') ?? '';

            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? '';

            const root = document.documentElement;

            let pendingRequest = null;
            let latestRequestId = 0;

            if (!themeUrl) {
                console.error(
                    'Theme preference URL is missing.'
                );

                return;
            }

            function normalizeThemeMode(mode) {
                const normalized = String(mode ?? '')
                    .trim()
                    .toLowerCase();

                return normalized === 'white'
                    ? 'light'
                    : normalized;
            }

            function getThemeLabel(mode) {
                const labels = {
                    light: 'White',
                    dark: 'Dark',
                    system: 'System',
                    custom: 'Custom',
                };

                return labels[
                    normalizeThemeMode(mode)
                ] ?? 'System';
            }

            function sameThemePath(url) {
                try {
                    const candidate = new URL(
                        url,
                        window.location.href
                    );

                    const expected = new URL(
                        themeUrl,
                        window.location.href
                    );

                    return (
                        candidate.pathname
                        === expected.pathname
                    );
                } catch (error) {
                    return false;
                }
            }

            function isThemeForm(form) {
                if (
                    !(form instanceof HTMLFormElement)
                ) {
                    return false;
                }

                return sameThemePath(form.action)
                    || form.querySelector(
                        '[name="theme_mode"]'
                    ) !== null;
            }

            function getThemeForm(control) {
                if (!control) {
                    return null;
                }

                const form = control.form
                    || control.closest?.('form')
                    || null;

                return isThemeForm(form)
                    ? form
                    : null;
            }

            function getControlThemeMode(control) {
                if (!control) {
                    return '';
                }

                const dataMode = control.getAttribute?.(
                    'data-theme-mode'
                );

                if (dataMode) {
                    return normalizeThemeMode(
                        dataMode
                    );
                }

                if (
                    control.getAttribute?.('name')
                    === 'theme_mode'
                ) {
                    return normalizeThemeMode(
                        control.value
                    );
                }

                const nestedControl =
                    control.querySelector?.(
                        '[name="theme_mode"],'
                        + '[data-theme-mode]'
                    );

                if (nestedControl) {
                    return getControlThemeMode(
                        nestedControl
                    );
                }

                const form = getThemeForm(control);

                if (!form) {
                    return '';
                }

                const checkedControl =
                    form.querySelector(
                        '[name="theme_mode"]:checked'
                    );

                if (checkedControl) {
                    return normalizeThemeMode(
                        checkedControl.value
                    );
                }

                const themeField =
                    form.querySelector(
                        '[name="theme_mode"]'
                    );

                return normalizeThemeMode(
                    themeField?.value ?? ''
                );
            }

            function getThemeMode(
                form,
                submitter = null
            ) {
                const submitterMode =
                    getControlThemeMode(submitter);

                if (submitterMode) {
                    return submitterMode;
                }

                const checkedControl =
                    form.querySelector(
                        '[name="theme_mode"]:checked'
                    );

                if (checkedControl) {
                    return normalizeThemeMode(
                        checkedControl.value
                    );
                }

                const themeField =
                    form.querySelector(
                        '[name="theme_mode"]'
                    );

                return normalizeThemeMode(
                    themeField?.value ?? ''
                );
            }

            function getCustomColor(form) {
                const field = form.querySelector(
                    '[name="theme_custom_color"]'
                );

                return String(
                    field?.value ?? ''
                ).trim();
            }

            function getThemeOptionRow(control) {
                if (!control) {
                    return null;
                }

                const directRow = control.closest?.(
                    '[data-theme-option],'
                    + '[role="menuitemradio"],'
                    + '[role="radio"],'
                    + 'button,'
                    + 'label'
                );

                if (directRow) {
                    return directRow;
                }

                const form = getThemeForm(control);

                if (!form) {
                    return control.parentElement;
                }

                return form.querySelector(
                    '[data-theme-option],'
                    + '[role="menuitemradio"],'
                    + '[role="radio"],'
                    + 'button,'
                    + 'label'
                ) || form;
            }

            function getThemeTitleElement(
                row,
                mode
            ) {
                if (!row) {
                    return null;
                }

                const expectedLabel =
                    getThemeLabel(mode);

                const descendants = Array.from(
                    row.querySelectorAll(
                        'span, p, strong, div'
                    )
                );

                return descendants.find(
                    (element) => (
                        element.children.length === 0
                        && element.textContent
                            .trim()
                            .toLowerCase()
                            === expectedLabel
                                .toLowerCase()
                    )
                ) || null;
            }

            function hideServerCheckmarks(row) {
                if (!row) {
                    return;
                }

                row.querySelectorAll(
                    '[data-theme-check]'
                ).forEach((checkmark) => {
                    checkmark.hidden = true;
                });

                Array.from(
                    row.querySelectorAll('span')
                ).forEach((span) => {
                    if (
                        !span.hasAttribute(
                            'data-theme-live-check'
                        )
                        && span.textContent.trim()
                            === '✓'
                    ) {
                        span.hidden = true;
                    }
                });
            }

            function ensureLiveCheckmark(row) {
                let checkmark = row.querySelector(
                    '[data-theme-live-check]'
                );

                if (checkmark) {
                    return checkmark;
                }

                checkmark =
                    document.createElement('span');

                checkmark.setAttribute(
                    'data-theme-live-check',
                    ''
                );

                checkmark.setAttribute(
                    'aria-hidden',
                    'true'
                );

                checkmark.textContent = '✓';

                Object.assign(
                    checkmark.style,
                    {
                        position: 'absolute',
                        top: '50%',
                        right: '0.9rem',
                        transform:
                            'translateY(-50%)',
                        color: '#2563eb',
                        fontSize: '1.1rem',
                        fontWeight: '700',
                        lineHeight: '1',
                        pointerEvents: 'none',
                    }
                );

                row.appendChild(checkmark);

                return checkmark;
            }

            function syncThemePreferenceUi(mode) {
                const selectedMode =
                    normalizeThemeMode(mode);

                const controls = Array.from(
                    document.querySelectorAll(
                        '[name="theme_mode"],'
                        + '[data-theme-mode]'
                    )
                );

                const processedRows = new Set();

                controls.forEach((control) => {
                    const controlMode =
                        getControlThemeMode(control);

                    if (!controlMode) {
                        return;
                    }

                    const selected =
                        controlMode === selectedMode;

                    if (
                        control
                        instanceof HTMLInputElement
                        && (
                            control.type === 'radio'
                            || control.type
                                === 'checkbox'
                        )
                    ) {
                        control.checked = selected;
                    }

                    control.setAttribute(
                        'aria-checked',
                        selected
                            ? 'true'
                            : 'false'
                    );

                    const row =
                        getThemeOptionRow(control);

                    if (
                        !row
                        || processedRows.has(row)
                    ) {
                        return;
                    }

                    processedRows.add(row);

                    row.setAttribute(
                        'data-theme-selected',
                        selected
                            ? 'true'
                            : 'false'
                    );

                    row.setAttribute(
                        'aria-checked',
                        selected
                            ? 'true'
                            : 'false'
                    );

                    row.style.position = 'relative';
                    row.style.paddingRight =
                        '2.75rem';

                    row.style.backgroundColor =
                        selected
                            ? '#eff6ff'
                            : 'transparent';

                    row.style.boxShadow = selected
                        ? 'inset 0 0 0 1px '
                            + 'rgba(191, 219, 254, 0.7)'
                        : 'none';

                    const title =
                        getThemeTitleElement(
                            row,
                            controlMode
                        );

                    if (title) {
                        title.style.color = selected
                            ? '#1d4ed8'
                            : '#0f172a';
                    }

                    hideServerCheckmarks(row);

                    const liveCheckmark =
                        ensureLiveCheckmark(row);

                    liveCheckmark.hidden =
                        !selected;
                });

                document.querySelectorAll(
                    '[data-theme-current]'
                ).forEach((element) => {
                    element.textContent =
                        'Current: '
                        + getThemeLabel(
                            selectedMode
                        );
                });

                document.querySelectorAll(
                    'p, span, small'
                ).forEach((element) => {
                    if (
                        element.children.length > 0
                    ) {
                        return;
                    }

                    const text =
                        element.textContent.trim();

                    if (
                        /^Current:\s*(White|Light|Dark|System|Custom)$/i
                            .test(text)
                    ) {
                        element.textContent =
                            'Current: '
                            + getThemeLabel(
                                selectedMode
                            );
                    }
                });
            }

            function applyTheme(
                mode,
                customColor = ''
            ) {
                const normalizedMode =
                    normalizeThemeMode(mode);

                const allowedModes = [
                    'light',
                    'dark',
                    'system',
                    'custom',
                ];

                if (
                    !allowedModes.includes(
                        normalizedMode
                    )
                ) {
                    return;
                }

                root.setAttribute(
                    'data-theme',
                    normalizedMode
                );

                const systemUsesDark =
                    window.matchMedia(
                        '(prefers-color-scheme: dark)'
                    ).matches;

                const resolvedDark =
                    normalizedMode === 'dark'
                    || (
                        normalizedMode === 'system'
                        && systemUsesDark
                    );

                root.classList.toggle(
                    'dark',
                    resolvedDark
                );

                if (
                    normalizedMode === 'custom'
                    && /^#[0-9A-Fa-f]{6}$/.test(
                        customColor
                    )
                ) {
                    const normalizedColor =
                        customColor.toLowerCase();

                    root.setAttribute(
                        'data-theme-custom-color',
                        normalizedColor
                    );

                    root.style.setProperty(
                        '--tn-accent',
                        normalizedColor
                    );
                } else {
                    root.removeAttribute(
                        'data-theme-custom-color'
                    );

                    root.style.removeProperty(
                        '--tn-accent'
                    );
                }

                syncThemePreferenceUi(
                    normalizedMode
                );

                window.dispatchEvent(
                    new CustomEvent(
                        'tabangnow:theme-changed',
                        {
                            detail: {
                                mode:
                                    normalizedMode,
                                customColor,
                            },
                        }
                    )
                );
            }

            async function saveTheme(
                form,
                submitter = null
            ) {
                if (!isThemeForm(form)) {
                    return;
                }

                const mode = getThemeMode(
                    form,
                    submitter
                );

                if (!mode) {
                    console.error(
                        'No theme mode was selected.'
                    );

                    return;
                }

                const customColor =
                    getCustomColor(form);

                const previousMode =
                    root.getAttribute(
                        'data-theme'
                    ) || 'system';

                const previousCustomColor =
                    root.getAttribute(
                        'data-theme-custom-color'
                    ) || '';

                applyTheme(
                    mode,
                    customColor
                );

                if (pendingRequest) {
                    pendingRequest.abort();
                }

                const requestController =
                    new AbortController();

                pendingRequest =
                    requestController;

                const requestId =
                    ++latestRequestId;

                form.setAttribute(
                    'aria-busy',
                    'true'
                );

                const formData =
                    new FormData(form);

                formData.set(
                    '_token',
                    csrfToken
                );

                formData.set(
                    '_method',
                    'PATCH'
                );

                formData.set(
                    'theme_mode',
                    mode
                );

                if (
                    mode === 'custom'
                    && customColor !== ''
                ) {
                    formData.set(
                        'theme_custom_color',
                        customColor
                    );
                } else {
                    formData.delete(
                        'theme_custom_color'
                    );
                }

                try {
                    const response = await fetch(
                        themeUrl,
                        {
                            method: 'POST',
                            body: formData,
                            credentials:
                                'same-origin',
                            redirect: 'follow',
                            signal:
                                requestController
                                    .signal,
                            headers: {
                                Accept:
                                    'application/json',
                                'X-Requested-With':
                                    'XMLHttpRequest',
                                'X-CSRF-TOKEN':
                                    csrfToken,
                            },
                        }
                    );

                    if (!response.ok) {
                        let message =
                            'The theme could not '
                            + 'be saved.';

                        try {
                            const data =
                                await response.json();

                            message =
                                data.message
                                || message;
                        } catch (error) {
                            // Keep the safe message.
                        }

                        throw new Error(message);
                    }
                } catch (error) {
                    if (
                        error.name ===
                        'AbortError'
                    ) {
                        return;
                    }

                    if (
                        requestId
                        === latestRequestId
                    ) {
                        applyTheme(
                            previousMode,
                            previousCustomColor
                        );

                        console.error(error);

                        window.alert(
                            error.message
                            || (
                                'The theme could '
                                + 'not be saved. '
                                + 'Please try again.'
                            )
                        );
                    }
                } finally {
                    form.removeAttribute(
                        'aria-busy'
                    );

                    if (
                        pendingRequest
                        === requestController
                    ) {
                        pendingRequest = null;
                    }
                }
            }

            document.addEventListener(
                'submit',
                (event) => {
                    const form = event.target;

                    if (!isThemeForm(form)) {
                        return;
                    }

                    event.preventDefault();
                    event.stopImmediatePropagation();

                    saveTheme(
                        form,
                        event.submitter
                    );
                },
                true
            );

            document.addEventListener(
                'change',
                (event) => {
                    const control =
                        event.target;

                    if (
                        !control.matches?.(
                            '[name="theme_mode"],'
                            + '[name="theme_custom_color"]'
                        )
                    ) {
                        return;
                    }

                    const form =
                        getThemeForm(control);

                    if (!form) {
                        return;
                    }

                    event.preventDefault();
                    event.stopImmediatePropagation();

                    saveTheme(
                        form,
                        control
                    );
                },
                true
            );

            const nativeSubmit =
                HTMLFormElement.prototype.submit;

            HTMLFormElement.prototype.submit =
                function () {
                    if (isThemeForm(this)) {
                        saveTheme(this);

                        return;
                    }

                    nativeSubmit.call(this);
                };

            function initializeThemeUi() {
                syncThemePreferenceUi(
                    root.getAttribute(
                        'data-theme'
                    ) || 'system'
                );
            }

            if (
                document.readyState
                === 'loading'
            ) {
                document.addEventListener(
                    'DOMContentLoaded',
                    initializeThemeUi,
                    {
                        once: true,
                    }
                );
            } else {
                initializeThemeUi();
            }

            document.addEventListener(
                'livewire:navigated',
                initializeThemeUi
            );

            window.matchMedia(
                '(prefers-color-scheme: dark)'
            ).addEventListener(
                'change',
                () => {
                    if (
                        root.getAttribute(
                            'data-theme'
                        ) === 'system'
                    ) {
                        applyTheme('system');
                    }
                }
            );
        })();
    </script>
    <div class="flex min-h-screen">
        <aside id="adminSidebar"
               class="fixed left-0 top-0 z-30 flex h-screen w-72 translate-x-0 flex-col overflow-hidden bg-blue-950 text-white transition-transform duration-300 ease-in-out">
            @php
                $layoutAuthUser = auth()->user();

                $systemSetting = null;

                if (
                    class_exists(\App\Models\SystemSetting::class)
                    && \Illuminate\Support\Facades\Schema::hasTable('system_settings')
                ) {
                    $systemSetting = \App\Models\SystemSetting::query()->first();
                }

                $systemName = $systemSetting?->system_name ?: 'SCSISystem';
                $systemSubtitle = $systemSetting?->system_subtitle ?: 'Dao, Capiz';
                $systemLogoPath = $systemSetting?->system_logo_path;

                $systemLogoExists = $systemLogoPath
                    && \Illuminate\Support\Facades\Storage::disk('public')->exists($systemLogoPath);

                $systemLogoUrl = $systemLogoExists && \Illuminate\Support\Facades\Route::has('system-branding.logo')
                    ? route('system-branding.logo') . '?v=' . optional($systemSetting?->updated_at)->timestamp
                    : null;

                $canEditSystemBranding = $layoutAuthUser?->role === 'admin'
                    && \Illuminate\Support\Facades\Route::has('admin.system-branding.edit');
            @endphp

            @if ($canEditSystemBranding)
                <a href="{{ route('admin.system-branding.edit') }}"
                   title="Edit system branding"
                   class="shrink-0 flex items-center gap-3 border-b border-blue-900 px-6 py-6 transition hover:bg-blue-900">
                    <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-blue-600">
                        @if ($systemLogoUrl)
                            <img src="{{ $systemLogoUrl }}"
                                 alt="{{ $systemName }} Logo"
                                 class="h-full w-full object-cover"
                                 onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">

                            <span class="hidden text-lg font-bold">🛡</span>
                        @else
                            <span class="text-lg font-bold">🛡</span>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <h1 class="truncate text-lg font-bold leading-tight">{{ $systemName }}</h1>
                        <p class="truncate text-sm text-blue-200">{{ $systemSubtitle }}</p>
                    </div>
                </a>
            @else
                <div class="shrink-0 flex items-center gap-3 border-b border-blue-900 px-6 py-6">
                    <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-blue-600">
                        @if ($systemLogoUrl)
                            <img src="{{ $systemLogoUrl }}"
                                 alt="{{ $systemName }} Logo"
                                 class="h-full w-full object-cover"
                                 onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">

                            <span class="hidden text-lg font-bold">🛡</span>
                        @else
                            <span class="text-lg font-bold">🛡</span>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <h1 class="truncate text-lg font-bold leading-tight">{{ $systemName }}</h1>
                        <p class="truncate text-sm text-blue-200">{{ $systemSubtitle }}</p>
                    </div>
                </div>
            @endif

            @php
                $authUser = auth()->user();
                $role = strtolower(trim($authUser?->role ?? ''));

                $authPhotoPath = $authUser && \Illuminate\Support\Facades\Schema::hasColumn('users', 'profile_photo_path')
                    ? ($authUser->profile_photo_path ?? null)
                    : null;

                $authPhotoUrl = $authPhotoPath && Route::has('users.profile-photo')
                    ? route('users.profile-photo', $authUser) . '?v=' . optional($authUser?->updated_at)->timestamp
                    : null;

                $authInitial = strtoupper(mb_substr($authUser?->name ?? 'U', 0, 1));

                $authProfileUrl = '#';

if ($authUser) {
    if (
        $authUser->isAdmin()
        && Route::has('admin.users.edit')
    ) {
        $authProfileUrl = route(
            'admin.users.edit',
            $authUser
        );
    } elseif (Route::has('profile.edit')) {
        $authProfileUrl = route('profile.edit');
    }
}

                $navItems = match ($role) {
                    'admin' => [
                        [
                            'label' => 'Dashboard',
                            'icon' => '▦',
                            'route' => 'admin.dashboard',
                            'active' => ['admin.dashboard'],
                        ],
                        [
                            'label' => 'Incidents',
                            'icon' => '📄',
                            'route' => 'admin.incidents.index',
                            'active' => ['admin.incidents.*'],
                        ],
                        [
                            'label' => 'Tanod Alerts',
                            'icon' => '🔔',
                            'route' => 'admin.tanod-alerts.index',
                            'active' => ['admin.tanod-alerts.*'],
                        ],
                        [
                            'label' => 'Tanod Roster',
                            'icon' => '👥',
                            'route' => 'admin.tanods.index',
                            'active' => ['admin.tanods.*'],
                        ],
                        [
                            'label' => 'Tanod Tasks',
                            'icon' => '📋',
                            'route' => 'admin.tanod-tasks.index',
                            'active' => ['admin.tanod-tasks.*'],
                        ],
                        [
                            'label' => 'Case Management',
                            'icon' => '📘',
                            'route' => 'admin.cases.index',
                            'active' => ['admin.cases.*'],
                        ],
                        [
                            'label' => 'Announcements',
                            'icon' => '📢',
                            'route' => 'admin.announcements.index',
                            'active' => ['admin.announcements.*'],
                        ],
                        [
                            'label' => 'Emergency Hotlines',
                            'icon' => '🚨',
                            'route' => 'admin.emergency-mode.index',
                            'active' => ['admin.emergency-mode.*'],
                        ],
                        [
    'label' => 'Resident Complaints',
    'icon' => '💬',
    'route' => 'admin.resident-complaints.index',
    'active' => ['admin.resident-complaints.*'],
],

                        [
                            'label' => 'Map',
                            'icon' => '🗺',
                            'route' => 'admin.map.index',
                            'active' => ['admin.map.*'],
                        ],
                        [
                            'label' => 'Reports',
                            'icon' => '📊',
                            'route' => 'admin.reports.index',
                            'active' => ['admin.reports.*'],
                        ],
                        [
                            'label' => 'User Management',
                            'icon' => '⚙',
                            'route' => 'admin.users.index',
                            'active' => ['admin.users.*'],
                        ],
                        [
                            'label' => 'Activity Logs',
                            'icon' => '🧾',
                            'route' => 'admin.activity-logs.index',
                            'active' => ['admin.activity-logs.*'],
                        ],
                    ],

                    'official', 'dao' => [
    [
        'label' => 'Dashboard',
        'icon' => '▦',
        'route' => 'official.dashboard',
        'active' => ['official.dashboard'],
    ],
    [
        'label' => 'Incidents',
        'icon' => '📄',
        'route' => 'official.incidents.index',
        'active' => ['official.incidents.*'],
    ],
    [
        'label' => 'Tanod Roster',
        'icon' => '👥',
        'route' => 'official.tanods.index',
        'active' => ['official.tanods.*'],
    ],
    [
        'label' => 'Announcements',
        'icon' => '📢',
        'route' => 'official.announcements.index',
        'active' => ['official.announcements.*'],
    ],
    [
        'label' => 'Emergency Hotlines',
        'icon' => '🚨',
        'route' => 'official.emergency-mode.index',
        'active' => ['official.emergency-mode.*'],
    ],
    [
    'label' => 'Resident Complaints',
    'icon' => '💬',
    'route' => 'official.resident-complaints.index',
    'active' => ['official.resident-complaints.*'],
    ],
    [
        'label' => 'Map',
        'icon' => '🗺',
        'route' => 'official.map.index',
        'active' => ['official.map.*'],
    ],
],

                    'tanod' => [
    [
        'label' => 'Dashboard',
        'icon' => '▦',
        'route' => 'tanod.dashboard',
        'active' => ['tanod.dashboard'],
    ],
    [
        'label' => 'Tanod Alerts',
        'icon' => '🔔',
        'route' => 'tanod.tanod-alerts.index',
        'active' => ['tanod.tanod-alerts.*'],
    ],
    [
        'label' => 'Tanod Tasks',
        'icon' => '📋',
        'route' => 'tanod.tanod-tasks.index',
        'active' => ['tanod.tanod-tasks.*'],
    ],
    [
        'label' => 'Tanod Roster',
        'icon' => '👥',
        'route' => 'tanod.tanods.index',
        'active' => ['tanod.tanods.*'],
    ],
    [
        'label' => 'Assigned Incidents',
        'icon' => '📄',
        'route' => 'tanod.incidents.index',
        'active' => ['tanod.incidents.*'],
    ],
    [
        'label' => 'Announcements',
        'icon' => '📢',
        'route' => 'tanod.announcements.index',
        'active' => ['tanod.announcements.*'],
    ],
    [
        'label' => 'Emergency Hotlines',
        'icon' => '🚨',
        'route' => 'tanod.emergency-mode.index',
        'active' => ['tanod.emergency-mode.*'],
    ],
],

                    'resident' => [
    [
        'label' => 'Dashboard',
        'icon' => '▦',
        'route' => 'resident.dashboard',
        'active' => ['resident.dashboard'],
    ],
    [
        'label' => 'Report Incident',
        'icon' => '📄',
        'route' => 'resident.incidents.index',
        'active' => [
            'resident.incidents.index',
            'resident.incidents.show',
            'resident.incidents.create',
        ],
    ],
    [
        'label' => 'Complaints Form',
        'icon' => '📝',
        'route' => 'resident.resident-complaints.create',
        'active' => [
            'resident.resident-complaints.index',
            'resident.resident-complaints.create',
            'resident.resident-complaints.show',
        ],
    ],
    [
        'label' => 'Announcements',
        'icon' => '📢',
        'route' => 'resident.announcements.index',
        'active' => ['resident.announcements.*'],
    ],
    [
        'label' => 'Emergency Hotlines',
        'icon' => '🚨',
        'route' => 'resident.emergency-mode.index',
        'active' => ['resident.emergency-mode.*'],
    ],
],

                    default => [],
                };
            @endphp

            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-4 py-5">
                @foreach ($navItems as $item)
                    @continue(! Route::has($item['route']))

                    @php
    $activePatterns = $item['active'] ?? [$item['route']];

    if (is_string($activePatterns)) {
        $activePatterns = [$activePatterns];
    }

    $isActive = collect($activePatterns)
        ->contains(fn ($pattern) => request()->routeIs($pattern));
@endphp

                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm
                       {{ $isActive
                            ? 'bg-blue-600 font-semibold text-white'
                            : 'font-medium text-blue-100 hover:bg-blue-900 hover:text-white' }}">
                        <span>{{ $item['icon'] }}</span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="shrink-0 border-t border-blue-900 px-4 py-4">
                <details class="relative group">
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-2xl px-2 py-2 hover:bg-blue-900">
                        <div class="relative h-10 w-10 shrink-0">
                            @if ($authPhotoUrl)
                                <img src="{{ $authPhotoUrl }}"
                                     alt="{{ $authUser->name }} profile photo"
                                     class="h-10 w-10 rounded-full border border-blue-800 object-cover shadow-sm"
                                     onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">

                                <div class="hidden h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">
                                    {{ $authInitial }}
                                </div>
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">
                                    {{ $authInitial }}
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-white">
                                {{ $authUser?->name ?? 'User' }}
                            </p>
                            <p class="truncate text-xs text-blue-200">
                                {{ ucfirst($authUser?->role ?? 'User') }}
                            </p>
                        </div>
                    </summary>

                    <div class="absolute bottom-full left-0 right-0 z-50 mb-3 overflow-hidden rounded-2xl border border-blue-900 bg-blue-950 shadow-2xl">
                        <div class="border-b border-blue-900 px-4 py-4">
                            <div class="flex items-center gap-3">
                                <div class="relative h-11 w-11 shrink-0">
                                    @if ($authPhotoUrl)
                                        <img src="{{ $authPhotoUrl }}"
                                             alt="{{ $authUser->name }} profile photo"
                                             class="h-11 w-11 rounded-full border border-blue-800 object-cover shadow-sm"
                                             onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">

                                        <div class="hidden h-11 w-11 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">
                                            {{ $authInitial }}
                                        </div>
                                    @else
                                        <div class="flex h-11 w-11 items-center justify-center rounded-full bg-blue-600 text-sm font-bold text-white">
                                            {{ $authInitial }}
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-white">
                                        {{ $authUser?->name ?? 'User' }}
                                    </p>
                                    <p class="truncate text-xs text-blue-200">
                                        {{ ucfirst($authUser?->role ?? 'User') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="py-2">
                            <a href="{{ $authProfileUrl }}"
                               class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-blue-100 hover:bg-blue-900 hover:text-white">
                                <span>👤</span>
                                <span>Profile</span>
                            </a>
                        </div>

                        <div class="border-t border-blue-900 py-2">
                            <form method="POST" action="{{ route('logout') }}" class="m-0">
                                @csrf

                                <button type="submit"
                                        class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-medium text-blue-100 hover:bg-blue-900 hover:text-white">
                                    <span>↪</span>
                                    <span>Logout</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </details>
            </div>
        </aside>

        <div id="adminMainContent"
     class="min-h-screen w-full flex-1 pl-72 transition-[padding] duration-300 ease-in-out">
    <header class="sticky top-0 z-[100] isolate flex h-16 w-full items-center justify-between border-b border-slate-200 bg-white px-8 shadow-sm">
        <button id="sidebarToggleButton"
                type="button"
                aria-label="Toggle sidebar"
                aria-controls="adminSidebar"
                aria-expanded="true"
                title="Open or close sidebar"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-100 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-blue-200">
            <svg xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 class="h-6 w-6"
                 aria-hidden="true">
                <path stroke-linecap="round"
                      d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <div class="ml-auto flex items-center gap-3">
            @if (view()->exists('components.theme-toggle'))
                @include('components.theme-toggle')
            @endif

            @if (view()->exists('components.notification-bell'))
                @include('components.notification-bell')
            @endif
        </div>
    </header>

    <main class="p-8">
        @yield('content')
    </main>
</div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('adminSidebar');
            const mainContent = document.getElementById('adminMainContent');
            const toggleButton = document.getElementById('sidebarToggleButton');
            const notificationBackdrop = document.getElementById('notificationBackdrop');
            const storageKey = 'tabangnow.admin.sidebar-collapsed';

            if (!sidebar || !mainContent || !toggleButton) {
                return;
            }

            function applySidebarState(isCollapsed) {
                sidebar.classList.toggle('-translate-x-full', isCollapsed);
                sidebar.classList.toggle('translate-x-0', !isCollapsed);

                mainContent.classList.toggle('pl-0', isCollapsed);
                mainContent.classList.toggle('pl-72', !isCollapsed);

                if (notificationBackdrop) {
                    notificationBackdrop.classList.toggle('left-0', isCollapsed);
                    notificationBackdrop.classList.toggle('left-72', !isCollapsed);
                }

                toggleButton.setAttribute(
                    'aria-expanded',
                    isCollapsed ? 'false' : 'true'
                );

                toggleButton.title = isCollapsed
                    ? 'Open sidebar'
                    : 'Close sidebar';
            }

            let isCollapsed = false;

            try {
                isCollapsed = localStorage.getItem(storageKey) === '1';
            } catch (error) {
                isCollapsed = false;
            }

            applySidebarState(isCollapsed);

            toggleButton.addEventListener('click', function () {
                const nextCollapsedState = !sidebar.classList.contains(
                    '-translate-x-full'
                );

                applySidebarState(nextCollapsedState);

                try {
                    localStorage.setItem(
                        storageKey,
                        nextCollapsedState ? '1' : '0'
                    );
                } catch (error) {
                    console.warn('Unable to save sidebar state.', error);
                }
            });
        });
    </script>

    <script>
        (() => {
            if (window.__tabangNowHistorySecurityV2Installed) {
                return;
            }

            window.__tabangNowHistorySecurityV2Installed = true;

            const root = document.documentElement;

            function concealAuthenticatedDocument() {
                root.setAttribute(
                    'data-auth-history-hidden',
                    'true'
                );

                root.style.setProperty(
                    'visibility',
                    'hidden',
                    'important'
                );

                root.style.setProperty(
                    'pointer-events',
                    'none',
                    'important'
                );
            }

            function revealAuthenticatedDocument() {
                root.removeAttribute(
                    'data-auth-history-hidden'
                );

                root.style.removeProperty(
                    'visibility'
                );

                root.style.removeProperty(
                    'pointer-events'
                );
            }

            function isHistoryTraversal(event = null) {
                const navigationEntry = performance
                    .getEntriesByType('navigation')[0];

                return Boolean(
                    event?.persisted
                    || navigationEntry?.type
                        === 'back_forward'
                );
            }

            function refreshThroughLaravel() {
                concealAuthenticatedDocument();
                window.location.reload();
            }

            window.addEventListener(
                'pagehide',
                function () {
                    concealAuthenticatedDocument();
                }
            );

            window.addEventListener(
                'pageshow',
                function (event) {
                    if (isHistoryTraversal(event)) {
                        refreshThroughLaravel();
                        return;
                    }

                    revealAuthenticatedDocument();
                }
            );

            window.addEventListener(
                'popstate',
                function () {
                    refreshThroughLaravel();
                }
            );
        })();
    </script>
</body>
</html>

@php
    $distressUser = auth()->user();
    $distressRole = strtolower(trim((string) ($distressUser?->role ?? '')));

    $distressRouteName = match ($distressRole) {
        'admin' => \Illuminate\Support\Facades\Route::has('admin.mobile-sos.index')
            ? 'admin.mobile-sos.index'
            : null,
        'official', 'dao' => \Illuminate\Support\Facades\Route::has('official.mobile-sos.index')
            ? 'official.mobile-sos.index'
            : null,
        default => null,
    };

    $incidentRouteName = match ($distressRole) {
        'admin' => \Illuminate\Support\Facades\Route::has('admin.incidents.index')
            ? 'admin.incidents.index'
            : null,
        'official', 'dao' => \Illuminate\Support\Facades\Route::has('official.incidents.index')
            ? 'official.incidents.index'
            : null,
        default => null,
    };
@endphp

@if ($distressRouteName && $incidentRouteName)
    <script>
        (() => {
            if (window.__tabangNowDistressSignalNavInstalled) {
                return;
            }

            window.__tabangNowDistressSignalNavInstalled = true;

            const distressUrl = @json(route($distressRouteName));
            const incidentsUrl = @json(route($incidentRouteName));

            function normalizePath(url) {
                try {
                    return new URL(url, window.location.href).pathname.replace(/\/$/, '') || '/';
                } catch (error) {
                    return '';
                }
            }

            function isDistressSignalActive() {
                const currentPath = normalizePath(window.location.href);
                const distressPath = normalizePath(distressUrl);

                return currentPath === distressPath
                    || currentPath.startsWith(`${distressPath}/`)
                    || currentPath.startsWith('/emergency-alerts/');
            }

            function styleDistressSignalLink(link) {
                link.className = isDistressSignalActive()
                    ? 'flex items-center gap-3 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white'
                    : 'flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-blue-100 hover:bg-blue-900 hover:text-white';
            }

            function installDistressSignalLink() {
                const nav = document.querySelector('#adminSidebar nav');

                if (!nav) {
                    return;
                }

                const existingLink = nav.querySelector('[data-distress-signal-nav]');

                if (existingLink) {
                    styleDistressSignalLink(existingLink);
                    return;
                }

                const incidentsPath = normalizePath(incidentsUrl);
                const incidentsLink = Array.from(nav.querySelectorAll('a[href]'))
                    .find((link) => normalizePath(link.href) === incidentsPath);

                if (!incidentsLink) {
                    return;
                }

                const link = document.createElement('a');
                link.href = distressUrl;
                link.setAttribute('data-distress-signal-nav', '');
                styleDistressSignalLink(link);

                const icon = document.createElement('span');
                icon.textContent = '🆘';

                const label = document.createElement('span');
                label.textContent = 'Distress Signal';

                link.append(icon, label);
                incidentsLink.insertAdjacentElement('afterend', link);
            }

            if (document.readyState === 'loading') {
                document.addEventListener(
                    'DOMContentLoaded',
                    installDistressSignalLink,
                    { once: true }
                );
            } else {
                installDistressSignalLink();
            }

            document.addEventListener(
                'livewire:navigated',
                installDistressSignalLink
            );
        })();
    </script>
@endif

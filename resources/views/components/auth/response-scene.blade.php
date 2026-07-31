<style>
    /*
    |--------------------------------------------------------------------------
    | TabangNow authentication map
    |--------------------------------------------------------------------------
    |
    | This component uses a real OpenStreetMap base map centered on Dao, Capiz.
    | Only incident markers and popups are added. No moving vehicle, custom
    | roads, route lines, buildings, or status cards are drawn.
    |
    */


    .tn-auth-scene-copy .tn-auth-headline-community {
        display: block;
        white-space: nowrap;
        font-size: 0.82em;
        line-height: 1.06;
        letter-spacing: -0.035em;
    }

    .tn-auth-real-map-shell {
        position: relative;
        overflow: hidden;
        min-height: 390px;
        border: 1px solid rgba(147, 197, 253, 0.42);
        border-radius: 2rem;
        background: #dbeafe;
        box-shadow:
            0 24px 70px rgba(2, 6, 23, 0.24),
            inset 0 1px 0 rgba(255, 255, 255, 0.55);
        isolation: isolate;
    }

    .tn-auth-real-map {
        width: 100%;
        height: clamp(390px, 43vw, 475px);
        min-height: 390px;
        background: #dbeafe;
    }

    .tn-auth-map-loading {
        position: absolute;
        inset: 0;
        z-index: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        background:
            radial-gradient(circle at center, rgba(219, 234, 254, 0.9), rgba(191, 219, 254, 0.96));
        color: #1e3a8a;
        font-size: 0.84rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-align: center;
        transition:
            opacity 250ms ease,
            visibility 250ms ease;
    }

    .tn-auth-map-loading.is-hidden {
        visibility: hidden;
        opacity: 0;
        pointer-events: none;
    }

    .tn-auth-map-badge {
        position: absolute;
        left: 1rem;
        bottom: 1rem;
        z-index: 450;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.55rem 0.72rem;
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 0.85rem;
        background: rgba(255, 255, 255, 0.9);
        color: #334155;
        font-size: 0.65rem;
        font-weight: 800;
        letter-spacing: 0.07em;
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.14);
        backdrop-filter: blur(10px);
        pointer-events: none;
    }

    .tn-auth-map-badge-dot {
        width: 0.55rem;
        height: 0.55rem;
        border-radius: 9999px;
        background: #22c55e;
        box-shadow: 0 0 0 0.25rem rgba(34, 197, 94, 0.16);
    }

    .tn-incident-div-icon {
        border: 0;
        background: transparent;
    }

    .tn-incident-marker {
        --incident-color: #22c55e;

        position: relative;
        width: 34px;
        height: 34px;
        transform: translateZ(0);
    }

    .tn-incident-marker__pulse,
    .tn-incident-marker__ring,
    .tn-incident-marker__core {
        position: absolute;
        inset: 50% auto auto 50%;
        border-radius: 9999px;
        transform: translate(-50%, -50%);
    }

    .tn-incident-marker__pulse {
        width: 32px;
        height: 32px;
        background: var(--incident-color);
        opacity: 0.34;
        animation: tnIncidentMapPulse 1.8s ease-out infinite;
    }

    .tn-incident-marker__ring {
        width: 23px;
        height: 23px;
        border: 3px solid rgba(255, 255, 255, 0.96);
        background: var(--incident-color);
        box-shadow:
            0 0 0 3px color-mix(in srgb, var(--incident-color) 52%, transparent),
            0 7px 18px rgba(15, 23, 42, 0.24);
    }

    .tn-incident-marker__core {
        width: 7px;
        height: 7px;
        background: #ffffff;
    }


    .tn-auth-real-map .leaflet-popup-content-wrapper {
        border: 1px solid rgba(148, 163, 184, 0.32);
        border-radius: 1rem;
        box-shadow: 0 16px 36px rgba(15, 23, 42, 0.22);
    }

    .tn-auth-real-map .leaflet-popup-content {
        margin: 0.8rem 0.95rem;
        min-width: 148px;
    }

    .tn-auth-real-map .leaflet-popup-tip {
        box-shadow: 3px 3px 7px rgba(15, 23, 42, 0.1);
    }

    .tn-auth-map-popup {
        display: grid;
        gap: 0.18rem;
        color: #0f172a;
        font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .tn-auth-map-popup strong {
        font-size: 0.78rem;
        line-height: 1.2;
    }

    .tn-auth-map-popup span {
        font-size: 0.7rem;
        font-weight: 800;
    }

    .tn-auth-map-popup small {
        color: #64748b;
        font-size: 0.62rem;
        line-height: 1.35;
    }

    .tn-auth-real-map .leaflet-control-zoom {
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 0.75rem;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.14);
    }

    .tn-auth-real-map .leaflet-control-zoom a {
        color: #1e3a8a;
    }

    .tn-auth-real-map .leaflet-control-attribution {
        padding: 0.15rem 0.35rem;
        background: rgba(255, 255, 255, 0.84);
        font-size: 0.55rem;
        backdrop-filter: blur(5px);
    }

    @keyframes tnIncidentMapPulse {
        0% {
            opacity: 0.42;
            transform: translate(-50%, -50%) scale(0.55);
        }

        72% {
            opacity: 0;
            transform: translate(-50%, -50%) scale(1.75);
        }

        100% {
            opacity: 0;
            transform: translate(-50%, -50%) scale(1.75);
        }
    }

    @media (max-width: 1280px) {
        .tn-auth-scene-copy .tn-auth-headline-community {
            font-size: 0.75em;
        }

        .tn-auth-real-map-shell,
        .tn-auth-real-map {
            min-height: 350px;
        }

        .tn-auth-real-map {
            height: clamp(350px, 42vw, 430px);
        }

        .tn-auth-map-badge {
            left: 0.75rem;
            bottom: 0.75rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .tn-incident-marker__pulse {
            animation: none;
        }
    }
</style>

<div class="tn-auth-scene">
    <div class="tn-auth-scene-copy">
        <span class="tn-auth-scene-eyebrow">
            Barangay response network
        </span>

        <h2>
            Report faster.<br>
            Respond smarter.<br>
            <span class="tn-auth-headline-community">
                Keep the community safer.
            </span>
        </h2>

        <p>
            TabangNow connects residents, barangay officials, and response
            personnel through one coordinated community platform.
        </p>
    </div>

    <div class="tn-auth-real-map-shell">
        <div id="tn-dao-auth-map"
             class="tn-auth-real-map"
             data-tabangnow-real-map="dao-capiz-v5"
             role="img"
             aria-label="Map of Dao, Capiz with animated incident signals">
        </div>

        <div id="tn-dao-auth-map-loading"
             class="tn-auth-map-loading">
            Loading Dao, Capiz map…
        </div>

        <div class="tn-auth-map-badge"
             aria-hidden="true">
            <span class="tn-auth-map-badge-dot"></span>
            DAO, CAPIZ · INCIDENT MONITOR
        </div>
    </div>
</div>

<script>
    (() => {
        'use strict';

        const leafletCssUrl = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        const leafletJsUrl = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        const leafletCssIntegrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
        const leafletJsIntegrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';

        const daoCenter = [11.39444, 122.68583];

        const incidentLocations = [
            [11.39444, 122.68583],
            [11.39915, 122.67950],
            [11.38940, 122.69180],
            [11.40180, 122.69010],
            [11.38710, 122.68120],
            [11.39685, 122.67590],
            [11.40325, 122.68340],
            [11.39165, 122.69510],
            [11.38495, 122.68760],
            [11.39805, 122.69320],
        ];


        const severities = [
            {
                label: 'Low',
                color: '#22c55e',
            },
            {
                label: 'Moderate',
                color: '#eab308',
            },
            {
                label: 'High',
                color: '#f97316',
            },
            {
                label: 'Critical',
                color: '#ef4444',
            },
        ];

        let leafletPromise = null;

        function ensureLeafletCss() {
            const existing = document.querySelector(
                'link[data-tabangnow-leaflet-css]'
            );

            if (existing) {
                return;
            }

            const link = document.createElement('link');

            link.rel = 'stylesheet';
            link.href = leafletCssUrl;
            link.integrity = leafletCssIntegrity;
            link.crossOrigin = '';
            link.dataset.tabangnowLeafletCss = 'true';

            document.head.appendChild(link);
        }

        function ensureLeaflet() {
            if (window.L) {
                return Promise.resolve(window.L);
            }

            if (leafletPromise) {
                return leafletPromise;
            }

            ensureLeafletCss();

            leafletPromise = new Promise((resolve, reject) => {
                const existing = document.querySelector(
                    'script[data-tabangnow-leaflet-js]'
                );

                if (existing) {
                    existing.addEventListener('load', () => resolve(window.L), {
                        once: true,
                    });

                    existing.addEventListener(
                        'error',
                        () => reject(new Error('Leaflet failed to load.')),
                        { once: true }
                    );

                    return;
                }

                const script = document.createElement('script');

                script.src = leafletJsUrl;
                script.integrity = leafletJsIntegrity;
                script.crossOrigin = '';
                script.dataset.tabangnowLeafletJs = 'true';

                script.addEventListener('load', () => resolve(window.L), {
                    once: true,
                });

                script.addEventListener(
                    'error',
                    () => reject(new Error('Leaflet failed to load.')),
                    { once: true }
                );

                document.head.appendChild(script);
            });

            return leafletPromise;
        }

        function incidentIcon(color) {
            return window.L.divIcon({
                className: 'tn-incident-div-icon',
                html: `
                    <div class="tn-incident-marker"
                         style="--incident-color: ${color};">
                        <span class="tn-incident-marker__pulse"></span>
                        <span class="tn-incident-marker__ring"></span>
                        <span class="tn-incident-marker__core"></span>
                    </div>
                `,
                iconSize: [34, 34],
                iconAnchor: [17, 17],
                popupAnchor: [0, -18],
            });
        }

        function popupContent(severity) {
            return `
                <div class="tn-auth-map-popup">
                    <strong>Incident signal</strong>
                    <span style="color: ${severity.color};">
                        ${severity.label} priority
                    </span>
                    <small>
                        Community incident update · Dao, Capiz
                    </small>
                </div>
            `;
        }

        function startIncidentLoop(map, mapElement) {
            let incidentIndex = 0;
            let incidentMarker = null;
            let loopTimer = null;
            let hideTimer = null;

            const showNextIncident = () => {
                if (!document.body.contains(mapElement)) {
                    window.clearTimeout(loopTimer);
                    window.clearTimeout(hideTimer);
                    return;
                }

                const location =
                    incidentLocations[incidentIndex % incidentLocations.length];

                const severity =
                    severities[incidentIndex % severities.length];

                if (!incidentMarker) {
                    incidentMarker = window.L.marker(location, {
                        icon: incidentIcon(severity.color),
                        keyboard: false,
                        riseOnHover: true,
                        zIndexOffset: 800,
                    }).addTo(map);
                } else {
                    incidentMarker
                        .setLatLng(location)
                        .setIcon(incidentIcon(severity.color))
                        .setOpacity(1);

                    if (!map.hasLayer(incidentMarker)) {
                        incidentMarker.addTo(map);
                    }
                }

                incidentMarker
                    .bindPopup(popupContent(severity), {
                        closeButton: false,
                        autoClose: true,
                        closeOnClick: false,
                        offset: [0, -2],
                    })
                    .openPopup();

                hideTimer = window.setTimeout(() => {
                    if (!incidentMarker) {
                        return;
                    }

                    map.closePopup();
                    incidentMarker.setOpacity(0);
                }, 1850);

                incidentIndex += 1;

                loopTimer = window.setTimeout(showNextIncident, 2450);
            };

            showNextIncident();
        }

        function initializeMap(mapElement) {
            if (
                !mapElement ||
                mapElement.dataset.tabangnowMapInitialized === 'true'
            ) {
                return;
            }

            mapElement.dataset.tabangnowMapInitialized = 'true';

            const loadingElement = document.getElementById(
                'tn-dao-auth-map-loading'
            );

            ensureLeaflet()
                .then(() => {
                    if (!document.body.contains(mapElement)) {
                        return;
                    }

                    const map = window.L.map(mapElement, {
                        attributionControl: true,
                        boxZoom: false,
                        doubleClickZoom: true,
                        dragging: true,
                        keyboard: false,
                        scrollWheelZoom: false,
                        tap: true,
                        touchZoom: true,
                        zoomControl: false,
                    }).setView(daoCenter, 14);

                    window.L.control
                        .zoom({
                            position: 'bottomright',
                        })
                        .addTo(map);

                    const tileLayer = window.L.tileLayer(
                        'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                        {
                            maxZoom: 19,
                            attribution:
                                '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                        }
                    );

                    let firstTileLoaded = false;

                    tileLayer.on('tileload', () => {
                        if (firstTileLoaded) {
                            return;
                        }

                        firstTileLoaded = true;

                        loadingElement?.classList.add('is-hidden');
                    });

                    tileLayer.on('tileerror', () => {
                        if (!firstTileLoaded && loadingElement) {
                            loadingElement.textContent =
                                'The Dao map could not load. Check the internet connection.';
                        }
                    });

                    tileLayer.addTo(map);

                    startIncidentLoop(map, mapElement);

                    window.setTimeout(() => {
                        map.invalidateSize();

                        if (firstTileLoaded) {
                            loadingElement?.classList.add('is-hidden');
                        }
                    }, 250);
                })
                .catch(() => {
                    if (loadingElement) {
                        loadingElement.textContent =
                            'The Dao map could not load. Check the internet connection.';
                    }

                    mapElement.dataset.tabangnowMapInitialized = 'false';
                });
        }

        function bootTabangNowMap() {
            initializeMap(
                document.querySelector(
                    '[data-tabangnow-real-map="dao-capiz-v5"]'
                )
            );
        }

        if (document.readyState === 'loading') {
            document.addEventListener(
                'DOMContentLoaded',
                bootTabangNowMap,
                { once: true }
            );
        } else {
            bootTabangNowMap();
        }

        document.addEventListener(
            'livewire:navigated',
            bootTabangNowMap
        );
    })();
</script>

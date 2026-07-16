@extends('layouts.admin')

@section('title', 'Barangay Map | DaoSystem')

@section('content')
<link rel="stylesheet"
      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
    #barangayMap {
        height: 620px;
        width: 100%;
        border-radius: 1rem;
        z-index: 1;
    }

    .incident-pin {
        width: 20px;
        height: 20px;
        border-radius: 9999px;
        border: 3px solid white;
        box-shadow: 0 6px 14px rgba(15, 23, 42, 0.35);
    }

    .leaflet-popup-content-wrapper {
        border-radius: 14px;
    }

    .leaflet-popup-content {
        margin: 14px 16px;
        min-width: 230px;
    }
</style>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                Barangay Map
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Incident location pins and heatmap based on reported incidents.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white px-5 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Mapped Incidents
            </p>

            <p class="mt-1 text-2xl font-bold text-blue-950">
                {{ $recordCount }}
            </p>
        </div>
    </div>

    {{-- Map Container --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-base font-bold text-slate-900">
                    Incident Map View
                </h2>

                <p class="text-sm text-slate-500">
                    Move, zoom, and click pins to inspect incident records.
                </p>
            </div>

            <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
                <button type="button"
                        id="pinsModeButton"
                        onclick="switchMapMode('pins')"
                        class="rounded-lg bg-blue-950 px-4 py-2 text-sm font-bold text-white">
                    Pins
                </button>

                <button type="button"
                        id="heatModeButton"
                        onclick="switchMapMode('heat')"
                        class="rounded-lg px-4 py-2 text-sm font-bold text-slate-600 hover:text-blue-950">
                    Heatmap
                </button>
            </div>
        </div>

        {{-- Map Legend --}}
        <div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <span class="text-xs font-bold uppercase tracking-wide text-slate-500">
                Map Legend
            </span>

            <div class="flex items-center gap-2">
                <span class="h-3.5 w-3.5 rounded-full bg-green-500 ring-2 ring-white shadow-sm"></span>
                <span class="text-sm font-medium text-slate-600">Low</span>
            </div>

            <div class="flex items-center gap-2">
                <span class="h-3.5 w-3.5 rounded-full bg-yellow-500 ring-2 ring-white shadow-sm"></span>
                <span class="text-sm font-medium text-slate-600">Moderate</span>
            </div>

            <div class="flex items-center gap-2">
                <span class="h-3.5 w-3.5 rounded-full bg-orange-500 ring-2 ring-white shadow-sm"></span>
                <span class="text-sm font-medium text-slate-600">High</span>
            </div>

            <div class="flex items-center gap-2">
                <span class="h-3.5 w-3.5 rounded-full bg-red-500 ring-2 ring-white shadow-sm"></span>
                <span class="text-sm font-medium text-slate-600">Critical</span>
            </div>
        </div>

        <div id="barangayMap"></div>
    </div>
</div>

<script type="application/json" id="mapIncidentsJson">@json($mapIncidents)</script>
<script type="application/json" id="mapCenterJson">@json($mapCenter)</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>

<script>
    const mapIncidents = JSON.parse(
        document.getElementById('mapIncidentsJson').textContent || '[]'
    );

    const mapCenter = JSON.parse(
        document.getElementById('mapCenterJson').textContent
        || '{"latitude":11.3945,"longitude":122.6858,"zoom":12}'
    );

    const map = L.map('barangayMap', {
        zoomControl: true,
        scrollWheelZoom: true,
    }).setView([mapCenter.latitude, mapCenter.longitude], mapCenter.zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const pinLayer = L.layerGroup().addTo(map);
    let heatLayer = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function createIncidentIcon(color) {
        return L.divIcon({
            className: '',
            html: `<div class="incident-pin" style="background:${color};"></div>`,
            iconSize: [20, 20],
            iconAnchor: [10, 10],
            popupAnchor: [0, -10],
        });
    }

    function popupTemplate(incident) {
        return `
            <div>
                <p style="font-size:12px;font-weight:700;color:#64748b;margin:0 0 4px;">
                    ${escapeHtml(incident.code)}
                </p>

                <h3 style="font-size:15px;font-weight:800;color:#0f172a;margin:0 0 8px;">
                    ${escapeHtml(incident.title)}
                </h3>

                <div style="font-size:13px;color:#475569;line-height:1.6;">
                    <p style="margin:0;"><strong>Category:</strong> ${escapeHtml(incident.category)}</p>
                    <p style="margin:0;"><strong>Barangay:</strong> ${escapeHtml(incident.barangay)}</p>
                    <p style="margin:0;"><strong>Location:</strong> ${escapeHtml(incident.location)}</p>
                    <p style="margin:0;"><strong>Status:</strong> ${escapeHtml(incident.status)}</p>
                    <p style="margin:0;"><strong>Severity:</strong> ${escapeHtml(incident.severity_label)}</p>
                    <p style="margin:0;"><strong>Reported:</strong> ${escapeHtml(incident.reported_at)}</p>
                </div>

                <a href="${escapeHtml(incident.view_url)}"
                   style="display:inline-block;margin-top:12px;background:#172554;color:white;text-decoration:none;font-size:12px;font-weight:800;padding:8px 12px;border-radius:10px;">
                    View Incident
                </a>
            </div>
        `;
    }

    function renderPins() {
        pinLayer.clearLayers();

        mapIncidents.forEach(function (incident) {
            const marker = L.marker([incident.latitude, incident.longitude], {
                icon: createIncidentIcon(incident.pin_color),
            });

            marker.bindPopup(popupTemplate(incident));
            marker.addTo(pinLayer);
        });
    }

    function renderHeatmap() {
        const heatPoints = mapIncidents.map(function (incident) {
            return [
                incident.latitude,
                incident.longitude,
                incident.heat_intensity
            ];
        });

        heatLayer = L.heatLayer(heatPoints, {
            radius: 35,
            blur: 25,
            maxZoom: 17,
            minOpacity: 0.35,
            gradient: {
                0.25: '#22c55e',
                0.45: '#eab308',
                0.70: '#f97316',
                1.00: '#ef4444'
            }
        });
    }

    function switchMapMode(mode) {
        const pinsButton = document.getElementById('pinsModeButton');
        const heatButton = document.getElementById('heatModeButton');

        if (heatLayer && map.hasLayer(heatLayer)) {
            map.removeLayer(heatLayer);
        }

        if (map.hasLayer(pinLayer)) {
            map.removeLayer(pinLayer);
        }

        pinsButton.className =
            'rounded-lg px-4 py-2 text-sm font-bold text-slate-600 hover:text-blue-950';

        heatButton.className =
            'rounded-lg px-4 py-2 text-sm font-bold text-slate-600 hover:text-blue-950';

        if (mode === 'pins') {
            pinLayer.addTo(map);

            pinsButton.className =
                'rounded-lg bg-blue-950 px-4 py-2 text-sm font-bold text-white';

            return;
        }

        if (mode === 'heat') {
            renderHeatmap();

            if (heatLayer) {
                heatLayer.addTo(map);
            }

            heatButton.className =
                'rounded-lg bg-blue-950 px-4 py-2 text-sm font-bold text-white';
        }
    }

    function fitMapToIncidents() {
        if (mapIncidents.length === 0) {
            return;
        }

        const bounds = mapIncidents.map(function (incident) {
            return [incident.latitude, incident.longitude];
        });

        map.fitBounds(bounds, {
            padding: [40, 40],
            maxZoom: 16,
        });
    }

    renderPins();
    fitMapToIncidents();

    if (mapIncidents.length === 0) {
        L.popup()
            .setLatLng([mapCenter.latitude, mapCenter.longitude])
            .setContent(`
                <div style="font-size:13px;color:#475569;">
                    <strong>No mapped incidents yet.</strong>
                </div>
            `)
            .openOn(map);
    }
</script>
@endsection

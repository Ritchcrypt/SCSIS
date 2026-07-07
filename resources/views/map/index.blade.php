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

    .picker-pin {
        width: 26px;
        height: 26px;
        border-radius: 9999px;
        background: #172554;
        border: 4px solid white;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.45);
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

    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
            <p class="font-bold">Please fix the following errors:</p>

            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Filters --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET"
              action="{{ route('admin.map.index') }}"
              class="grid gap-4 lg:grid-cols-[1fr_220px_auto_auto] lg:items-end">

            <div>
                <label for="search" class="mb-2 block text-sm font-semibold text-slate-700">
                    Search Location / Incident
                </label>

                <input type="text"
                       id="search"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Search by title, code, location, barangay..."
                       class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label for="status" class="mb-2 block text-sm font-semibold text-slate-700">
                    Status
                </label>

                <select id="status"
                        name="status"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit"
                    class="rounded-xl bg-blue-950 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-900">
                Apply Filter
            </button>

            <a href="{{ route('admin.map.index') }}"
               class="rounded-xl border border-slate-300 px-5 py-3 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">
                Reset
            </a>
        </form>
    </div>

    {{-- Map Container --}}
    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Incident Map View
                    </h2>

                    <p class="text-sm text-slate-500">
                        Move, zoom, click pins, or use picker mode to save incident coordinates.
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

            <div id="barangayMap"></div>
        </div>

        {{-- Side Panel --}}
        <div class="space-y-4">
            {{-- Location Picker --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-bold text-slate-900">
                    Set Incident Location
                </h3>

                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Select an incident, click <span class="font-semibold text-slate-900">Start Picking</span>, then click the exact location on the map.
                </p>

                <form id="locationPickerForm"
                      method="POST"
                      action="#"
                      class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="picker_incident_id" class="mb-2 block text-sm font-semibold text-slate-700">
                            Incident
                        </label>

                        <select id="picker_incident_id"
                                onchange="handleIncidentSelection(this)"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                            <option value="">Select incident</option>

                            @foreach ($locationIncidentOptions as $incidentOption)
                                <option value="{{ $incidentOption['id'] }}"
                                        data-update-url="{{ $incidentOption['update_url'] }}"
                                        data-latitude="{{ $incidentOption['latitude'] }}"
                                        data-longitude="{{ $incidentOption['longitude'] }}"
                                        data-location="{{ e($incidentOption['location']) }}"
                                        data-severity="{{ $incidentOption['severity'] }}">
                                    {{ $incidentOption['code'] }} — {{ $incidentOption['title'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="map_location_name" class="mb-2 block text-sm font-semibold text-slate-700">
                            Location Name
                        </label>

                        <input type="text"
                               id="map_location_name"
                               name="map_location_name"
                               placeholder="Example: Dao Public Market, Purok 2"
                               class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>

                    <div>
                        <label for="map_severity" class="mb-2 block text-sm font-semibold text-slate-700">
                            Severity
                        </label>

                        <select id="map_severity"
                                name="map_severity"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                            @foreach ($severityOptions as $value => $label)
                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-1">
                        <div>
                            <label for="latitude" class="mb-2 block text-sm font-semibold text-slate-700">
                                Latitude
                            </label>

                            <input type="text"
                                   id="latitude"
                                   name="latitude"
                                   readonly
                                   required
                                   placeholder="Click map"
                                   class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700 shadow-sm">
                        </div>

                        <div>
                            <label for="longitude" class="mb-2 block text-sm font-semibold text-slate-700">
                                Longitude
                            </label>

                            <input type="text"
                                   id="longitude"
                                   name="longitude"
                                   readonly
                                   required
                                   placeholder="Click map"
                                   class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700 shadow-sm">
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <button type="button"
                                id="startPickerButton"
                                onclick="startLocationPicking()"
                                class="rounded-xl border border-blue-900 px-5 py-3 text-sm font-bold text-blue-950 hover:bg-blue-50">
                            📍 Start Picking
                        </button>

                        <button type="submit"
                                id="saveLocationButton"
                                disabled
                                class="rounded-xl bg-blue-950 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-900 disabled:cursor-not-allowed disabled:bg-slate-400">
                            Save Location
                        </button>
                    </div>
                </form>
            </div>

            {{-- Map Legend --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-bold text-slate-900">
                    Map Legend
                </h3>

                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center gap-3">
                        <span class="h-4 w-4 rounded-full bg-green-500"></span>
                        <span class="text-slate-600">Low severity</span>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="h-4 w-4 rounded-full bg-yellow-500"></span>
                        <span class="text-slate-600">Moderate severity</span>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="h-4 w-4 rounded-full bg-orange-500"></span>
                        <span class="text-slate-600">High severity</span>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="h-4 w-4 rounded-full bg-red-500"></span>
                        <span class="text-slate-600">Critical severity</span>
                    </div>
                </div>
            </div>

            {{-- Current View --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-bold text-slate-900">
                    Current View
                </h3>

                <div class="mt-4 space-y-3 text-sm text-slate-600">
                    <p>
                        <span class="font-semibold text-slate-900">Mode:</span>
                        <span id="currentModeLabel">Pins</span>
                    </p>

                    <p>
                        <span class="font-semibold text-slate-900">Records:</span>
                        {{ $recordCount }}
                    </p>

                    <p>
                        <span class="font-semibold text-slate-900">Filter:</span>
                        {{ $statuses[$selectedStatus] ?? 'All Status' }}
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-bold text-slate-900">
                    Important
                </h3>

                <p class="mt-3 text-sm leading-6 text-slate-600">
                    Only incidents with saved latitude and longitude will appear on this map.
                    Incidents without coordinates are hidden from the map layer.
                </p>
            </div>
        </div>
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
        document.getElementById('mapCenterJson').textContent || '{"latitude":11.3945,"longitude":122.6858,"zoom":12}'
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
    let currentMode = 'pins';
    let pickingLocation = false;
    let pickerMarker = null;

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

    function createPickerIcon() {
        return L.divIcon({
            className: '',
            html: '<div class="picker-pin"></div>',
            iconSize: [26, 26],
            iconAnchor: [13, 13],
            popupAnchor: [0, -12],
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
        currentMode = mode;

        const pinsButton = document.getElementById('pinsModeButton');
        const heatButton = document.getElementById('heatModeButton');
        const modeLabel = document.getElementById('currentModeLabel');

        if (heatLayer) {
            map.removeLayer(heatLayer);
        }

        if (map.hasLayer(pinLayer)) {
            map.removeLayer(pinLayer);
        }

        pinsButton.className = 'rounded-lg px-4 py-2 text-sm font-bold text-slate-600 hover:text-blue-950';
        heatButton.className = 'rounded-lg px-4 py-2 text-sm font-bold text-slate-600 hover:text-blue-950';

        if (mode === 'pins') {
            pinLayer.addTo(map);
            pinsButton.className = 'rounded-lg bg-blue-950 px-4 py-2 text-sm font-bold text-white';
            modeLabel.textContent = 'Pins';
            return;
        }

        if (mode === 'heat') {
            renderHeatmap();

            if (heatLayer) {
                heatLayer.addTo(map);
            }

            heatButton.className = 'rounded-lg bg-blue-950 px-4 py-2 text-sm font-bold text-white';
            modeLabel.textContent = 'Heatmap';
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

    function handleIncidentSelection(select) {
        const selectedOption = select.options[select.selectedIndex];
        const form = document.getElementById('locationPickerForm');
        const saveButton = document.getElementById('saveLocationButton');

        if (! selectedOption || ! selectedOption.value) {
            form.action = '#';
            saveButton.disabled = true;
            setPickerFields('', '', '', 'moderate');
            removePickerMarker();
            return;
        }

        form.action = selectedOption.dataset.updateUrl || '#';

        const latitude = selectedOption.dataset.latitude || '';
        const longitude = selectedOption.dataset.longitude || '';
        const locationName = selectedOption.dataset.location || '';
        const severity = selectedOption.dataset.severity || 'moderate';

        setPickerFields(latitude, longitude, locationName, severity);

        if (latitude && longitude) {
            placePickerMarker(parseFloat(latitude), parseFloat(longitude));
            map.setView([parseFloat(latitude), parseFloat(longitude)], 16);
            saveButton.disabled = false;
        } else {
            removePickerMarker();
            saveButton.disabled = true;
        }
    }

    function setPickerFields(latitude, longitude, locationName, severity) {
        document.getElementById('latitude').value = latitude || '';
        document.getElementById('longitude').value = longitude || '';
        document.getElementById('map_location_name').value = locationName || '';
        document.getElementById('map_severity').value = severity || 'moderate';
    }

    function startLocationPicking() {
        const incidentSelect = document.getElementById('picker_incident_id');
        const startButton = document.getElementById('startPickerButton');

        if (! incidentSelect.value) {
            alert('Select an incident first.');
            return;
        }

        pickingLocation = true;
        startButton.textContent = 'Click the map now...';
        startButton.className = 'rounded-xl border border-orange-500 bg-orange-50 px-5 py-3 text-sm font-bold text-orange-700';
    }

    function stopLocationPicking() {
        const startButton = document.getElementById('startPickerButton');

        pickingLocation = false;
        startButton.textContent = '📍 Start Picking';
        startButton.className = 'rounded-xl border border-blue-900 px-5 py-3 text-sm font-bold text-blue-950 hover:bg-blue-50';
    }

    function placePickerMarker(latitude, longitude) {
        removePickerMarker();

        pickerMarker = L.marker([latitude, longitude], {
            icon: createPickerIcon(),
        }).addTo(map);

        pickerMarker.bindPopup('Selected incident location').openPopup();
    }

    function removePickerMarker() {
        if (pickerMarker) {
            map.removeLayer(pickerMarker);
            pickerMarker = null;
        }
    }

    map.on('click', function (event) {
        if (! pickingLocation) {
            return;
        }

        const latitude = event.latlng.lat.toFixed(7);
        const longitude = event.latlng.lng.toFixed(7);

        document.getElementById('latitude').value = latitude;
        document.getElementById('longitude').value = longitude;
        document.getElementById('saveLocationButton').disabled = false;

        placePickerMarker(latitude, longitude);
        stopLocationPicking();
    });

    renderPins();
    fitMapToIncidents();

    if (mapIncidents.length === 0) {
        L.popup()
            .setLatLng([mapCenter.latitude, mapCenter.longitude])
            .setContent(`
                <div style="font-size:13px;color:#475569;">
                    <strong>No mapped incidents yet.</strong><br>
                    Select an incident, start picking, then click the map to save its location.
                </div>
            `)
            .openOn(map);
    }
</script>
@endsection
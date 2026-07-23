@php
    $feed = [];

    if (class_exists(\App\Services\WeatherDisasterFeedService::class)) {
        try {
            $feed = app(\App\Services\WeatherDisasterFeedService::class)->feed();
        } catch (\Throwable $e) {
            $feed = [];
        }
    }

    $weather = $feed['weather'] ?? [
        'location' => 'Dao, Capiz',
        'temperature' => null,
        'feels_like' => null,
        'humidity' => null,
        'wind_speed' => null,
        'rain_chance' => null,
        'condition' => 'Weather feed temporarily unavailable',
        'icon' => '🌤️',
        'risk_level' => 'normal',
        'advisory' => 'Weather service is not fully connected yet. Dashboard is running safely.',
        'source' => 'Local fallback',
        'updated_at' => now()->format('M d, Y h:i A'),
        'status_message' => 'Local fallback mode.',
    ];

    $advisories = collect($feed['advisories'] ?? []);
    $announcementsUrl = $feed['announcements_url'] ?? null;

    $riskLevel = $weather['risk_level'] ?? 'normal';

    $riskClass = match ($riskLevel) {
        'warning' => 'border-red-200 bg-red-50 text-red-800',
        'watch' => 'border-orange-200 bg-orange-50 text-orange-800',
        'notice' => 'border-yellow-200 bg-yellow-50 text-yellow-800',
        default => 'border-green-200 bg-green-50 text-green-800',
    };

    $riskLabel = match ($riskLevel) {
        'warning' => 'Warning',
        'watch' => 'Watch',
        'notice' => 'Notice',
        default => 'Normal',
    };

    $temperature = is_numeric($weather['temperature'] ?? null)
        ? number_format((float) $weather['temperature'], 1) . '°C'
        : '—';

    $feelsLike = is_numeric($weather['feels_like'] ?? null)
        ? number_format((float) $weather['feels_like'], 1) . '°C'
        : '—';

    $humidity = is_numeric($weather['humidity'] ?? null)
        ? (int) $weather['humidity'] . '%'
        : '—';

    $windSpeed = is_numeric($weather['wind_speed'] ?? null)
        ? number_format((float) $weather['wind_speed'], 1) . ' km/h'
        : '—';

    $rainChance = is_numeric($weather['rain_chance'] ?? null)
        ? (int) $weather['rain_chance'] . '%'
        : '—';
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-2xl">
                {{ $weather['icon'] ?? '🌤️' }}
            </div>

            <div>
                <h2 class="text-lg font-black text-slate-900">
                    Weather & Disaster Feed
                </h2>

                <p class="text-sm text-slate-500">
                    {{ $weather['location'] ?? 'Dao, Capiz' }} only
                </p>
            </div>
        </div>

        <span class="inline-flex w-fit rounded-full border px-3 py-1 text-xs font-black uppercase {{ $riskClass }}">
            {{ $riskLabel }}
        </span>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Temperature</p>
            <p class="mt-2 text-2xl font-black text-slate-900">{{ $temperature }}</p>
            <p class="mt-1 text-xs text-slate-500">Feels like {{ $feelsLike }}</p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Condition</p>
            <p class="mt-2 text-sm font-black text-slate-900">{{ $weather['condition'] ?? 'Unavailable' }}</p>
            <p class="mt-1 text-xs text-slate-500">Source: {{ $weather['source'] ?? 'Weather feed' }}</p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Humidity</p>
            <p class="mt-2 text-2xl font-black text-slate-900">{{ $humidity }}</p>
            <p class="mt-1 text-xs text-slate-500">Relative humidity</p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Wind Speed</p>
            <p class="mt-2 text-2xl font-black text-slate-900">{{ $windSpeed }}</p>
            <p class="mt-1 text-xs text-slate-500">10-meter wind</p>
        </div>

        <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Rain Chance</p>
            <p class="mt-2 text-2xl font-black text-slate-900">{{ $rainChance }}</p>
            <p class="mt-1 text-xs text-slate-500">Current forecast hour</p>
        </div>
    </div>

    <div class="mt-5 rounded-2xl border {{ $riskClass }} p-4">
        <p class="text-sm font-black">
            Automatic Weather Advisory
        </p>

        <p class="mt-1 text-sm leading-6">
            {{ $weather['advisory'] ?? 'Weather advisory is currently unavailable.' }}
        </p>

        <p class="mt-2 text-xs font-semibold opacity-80">
            {{ $weather['status_message'] ?? 'Weather feed loaded.' }}
            @if (! empty($weather['updated_at']))
                Updated {{ $weather['updated_at'] }}.
            @endif
        </p>
    </div>

    <div class="mt-6 border-t border-slate-200 pt-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-sm font-black uppercase tracking-wide text-slate-700">
                    Local Disaster Advisories from Announcements
                </h3>

                <p class="mt-1 text-sm text-slate-500">
                    Weather, disaster, calamity, emergency, flood, typhoon, or evacuation announcements appear here.
                </p>
            </div>

            @if ($announcementsUrl)
                <a href="{{ $announcementsUrl }}"
                   class="text-sm font-bold text-blue-700 hover:text-blue-800">
                    Open Announcements →
                </a>
            @endif
        </div>

        @if ($advisories->count())
            <div class="mt-4 space-y-3">
                @foreach ($advisories as $advisory)
                    <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4 text-orange-800">
                        <p class="text-sm font-black">
                            {{ $advisory['title'] ?? 'Weather / Disaster Advisory' }}
                        </p>

                        <p class="mt-1 text-sm leading-6">
                            {{ $advisory['message'] ?? 'Please check the Announcements module for details.' }}
                        </p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center">
                <p class="text-sm font-bold text-slate-700">
                    No active weather or disaster announcement posted.
                </p>

                <p class="mt-1 text-xs text-slate-500">
                    Admin or official can post a disaster-related announcement in the Announcement module.
                </p>
            </div>
        @endif
    </div>
</div>
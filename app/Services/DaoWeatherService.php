<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DaoWeatherService
{
    private const LOCATION_NAME = 'Dao, Capiz';
    private const LATITUDE = 11.3938;
    private const LONGITUDE = 122.6852;
    private const TIMEZONE = 'Asia/Manila';

    private const LIVE_CACHE_KEY = 'dao_weather_feed_live';
    private const LAST_GOOD_CACHE_KEY = 'dao_weather_feed_last_good';

    public function current(): array
    {
        if (strtolower((string) env('WEATHER_FEED_MODE', 'fake')) !== 'live') {
            return $this->fakeDevelopmentWeather();
        }

        try {
            return Cache::remember(self::LIVE_CACHE_KEY, now()->addMinutes(30), function () {
                $weather = $this->fetchLiveWeather();

                Cache::put(self::LAST_GOOD_CACHE_KEY, $weather, now()->addDays(3));

                return $weather;
            });
        } catch (\Throwable $exception) {
            Log::warning('Dao weather feed failed.', [
                'message' => $exception->getMessage(),
            ]);

            $cachedWeather = Cache::get(self::LAST_GOOD_CACHE_KEY);

            if (is_array($cachedWeather)) {
                $cachedWeather['is_cached'] = true;
                $cachedWeather['status_message'] = 'Using last available weather data.';

                return $cachedWeather;
            }

            return $this->fallbackWeather();
        }
    }

    private function fakeDevelopmentWeather(): array
    {
        return [
            'location' => self::LOCATION_NAME,
            'latitude' => self::LATITUDE,
            'longitude' => self::LONGITUDE,
            'temperature' => 28.5,
            'feels_like' => 31.0,
            'humidity' => 82,
            'wind_speed' => 9.5,
            'wind_direction' => 120,
            'rain_chance' => 45,
            'precipitation' => 0.0,
            'weather_code' => 2,
            'condition' => 'Partly cloudy',
            'icon' => '⛅',
            'risk_level' => 'notice',
            'advisory' => 'Development mode weather data. Live Open-Meteo API is currently disabled.',
            'source' => 'Development mode',
            'is_cached' => false,
            'status_message' => 'Fake local weather data. Set WEATHER_FEED_MODE=live to enable Open-Meteo.',
            'updated_at' => Carbon::now(self::TIMEZONE)->format('M d, Y h:i A'),
        ];
    }

    private function fetchLiveWeather(): array
    {
        $response = Http::timeout(8)
            ->retry(2, 300)
            ->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => self::LATITUDE,
                'longitude' => self::LONGITUDE,
                'timezone' => self::TIMEZONE,
                'forecast_days' => 1,
                'current' => implode(',', [
                    'temperature_2m',
                    'relative_humidity_2m',
                    'apparent_temperature',
                    'precipitation',
                    'weather_code',
                    'wind_speed_10m',
                    'wind_direction_10m',
                ]),
                'hourly' => implode(',', [
                    'precipitation_probability',
                ]),
            ])
            ->throw()
            ->json();

        $current = $response['current'] ?? [];
        $hourly = $response['hourly'] ?? [];

        $weatherCode = (int) ($current['weather_code'] ?? 0);
        $condition = $this->conditionFromCode($weatherCode);

        $rainChance = $this->nearestHourlyValue(
            $hourly['time'] ?? [],
            $hourly['precipitation_probability'] ?? []
        );

        $temperature = $this->nullableFloat($current['temperature_2m'] ?? null);
        $humidity = $this->nullableInt($current['relative_humidity_2m'] ?? null);
        $windSpeed = $this->nullableFloat($current['wind_speed_10m'] ?? null);
        $precipitation = $this->nullableFloat($current['precipitation'] ?? null);

        $riskLevel = $this->riskLevel(
            weatherCode: $weatherCode,
            rainChance: $rainChance,
            precipitation: $precipitation
        );

        return [
            'location' => self::LOCATION_NAME,
            'latitude' => self::LATITUDE,
            'longitude' => self::LONGITUDE,
            'temperature' => $temperature,
            'feels_like' => $this->nullableFloat($current['apparent_temperature'] ?? null),
            'humidity' => $humidity,
            'wind_speed' => $windSpeed,
            'wind_direction' => $this->nullableInt($current['wind_direction_10m'] ?? null),
            'rain_chance' => $rainChance,
            'precipitation' => $precipitation,
            'weather_code' => $weatherCode,
            'condition' => $condition['label'],
            'icon' => $condition['icon'],
            'risk_level' => $riskLevel,
            'advisory' => $this->automaticAdvisory($riskLevel),
            'source' => 'Open-Meteo',
            'is_cached' => false,
            'status_message' => 'Live weather data for Dao, Capiz.',
            'updated_at' => Carbon::now(self::TIMEZONE)->format('M d, Y h:i A'),
        ];
    }

    private function nearestHourlyValue(array $times, array $values): ?int
    {
        if (empty($times) || empty($values)) {
            return null;
        }

        $now = Carbon::now(self::TIMEZONE)->startOfHour();

        $nearestIndex = null;
        $nearestDiff = null;

        foreach ($times as $index => $time) {
            if (! array_key_exists($index, $values)) {
                continue;
            }

            try {
                $hour = Carbon::parse($time, self::TIMEZONE);
            } catch (\Throwable) {
                continue;
            }

            $diff = abs($hour->diffInMinutes($now, false));

            if ($nearestDiff === null || $diff < $nearestDiff) {
                $nearestIndex = $index;
                $nearestDiff = $diff;
            }
        }

        if ($nearestIndex === null) {
            return null;
        }

        return $this->nullableInt($values[$nearestIndex]);
    }

    private function conditionFromCode(int $code): array
    {
        return match ($code) {
            0 => ['label' => 'Clear sky', 'icon' => '☀️'],
            1 => ['label' => 'Mainly clear', 'icon' => '🌤️'],
            2 => ['label' => 'Partly cloudy', 'icon' => '⛅'],
            3 => ['label' => 'Overcast', 'icon' => '☁️'],
            45, 48 => ['label' => 'Foggy', 'icon' => '🌫️'],
            51, 53, 55 => ['label' => 'Drizzle', 'icon' => '🌦️'],
            61, 63 => ['label' => 'Rain', 'icon' => '🌧️'],
            65 => ['label' => 'Heavy rain', 'icon' => '🌧️'],
            80, 81 => ['label' => 'Rain showers', 'icon' => '🌦️'],
            82 => ['label' => 'Heavy rain showers', 'icon' => '🌧️'],
            95 => ['label' => 'Thunderstorm', 'icon' => '⛈️'],
            96, 99 => ['label' => 'Thunderstorm with hail', 'icon' => '⛈️'],
            default => ['label' => 'Weather data available', 'icon' => '🌤️'],
        };
    }

    private function riskLevel(int $weatherCode, ?int $rainChance, ?float $precipitation): string
    {
        if (in_array($weatherCode, [65, 82, 95, 96, 99], true)) {
            return 'warning';
        }

        if (($rainChance !== null && $rainChance >= 70) || ($precipitation !== null && $precipitation >= 5)) {
            return 'watch';
        }

        if ($rainChance !== null && $rainChance >= 40) {
            return 'notice';
        }

        return 'normal';
    }

    private function automaticAdvisory(string $riskLevel): string
    {
        return match ($riskLevel) {
            'warning' => 'Possible severe weather conditions. Residents should stay alert and monitor official announcements.',
            'watch' => 'Rain is likely. Low-lying areas should monitor possible water rise or localized flooding.',
            'notice' => 'There is a moderate chance of rain. Residents should stay prepared for sudden weather changes.',
            default => 'No immediate weather-related risk detected for Dao, Capiz.',
        };
    }

    private function fallbackWeather(): array
    {
        return [
            'location' => self::LOCATION_NAME,
            'latitude' => self::LATITUDE,
            'longitude' => self::LONGITUDE,
            'temperature' => null,
            'feels_like' => null,
            'humidity' => null,
            'wind_speed' => null,
            'wind_direction' => null,
            'rain_chance' => null,
            'precipitation' => null,
            'weather_code' => null,
            'condition' => 'Weather feed temporarily unavailable',
            'icon' => '🌤️',
            'risk_level' => 'normal',
            'advisory' => 'Live weather data is temporarily unavailable. The dashboard is still running safely.',
            'source' => 'Local fallback',
            'is_cached' => false,
            'status_message' => 'Weather feed temporarily unavailable.',
            'updated_at' => Carbon::now(self::TIMEZONE)->format('M d, Y h:i A'),
        ];
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 1) : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }
}
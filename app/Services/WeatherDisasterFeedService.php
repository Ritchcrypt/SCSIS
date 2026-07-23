<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class WeatherDisasterFeedService
{
    public function __construct(
        private readonly DaoWeatherService $weatherService
    ) {
    }

    public function feed(): array
    {
        return [
            'weather' => $this->weatherService->current(),
            'advisories' => $this->announcementAdvisories(),
            'announcements_url' => $this->announcementsUrl(),
        ];
    }

    private function announcementAdvisories(): Collection
    {
        if (! Schema::hasTable('announcements')) {
            return collect();
        }

        $columns = Schema::getColumnListing('announcements');

        $query = DB::table('announcements');

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        }

        if (in_array('status', $columns, true)) {
            $query->where(function ($statusQuery) {
                $statusQuery
                    ->whereNull('status')
                    ->orWhereNotIn(DB::raw('LOWER(status)'), [
                        'draft',
                        'archived',
                        'inactive',
                        'disabled',
                    ]);
            });
        }

        if (in_array('starts_at', $columns, true)) {
            $query->where(function ($startsQuery) {
                $startsQuery
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            });
        }

        if (in_array('ends_at', $columns, true)) {
            $query->where(function ($endsQuery) {
                $endsQuery
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
        }

        if (in_array('audience', $columns, true)) {
            $audiences = $this->allowedAudiences();

            $query->where(function ($audienceQuery) use ($audiences) {
                $audienceQuery
                    ->whereNull('audience')
                    ->orWhereIn(DB::raw('LOWER(audience)'), $audiences);
            });
        }

        if (in_array('show_in_weather_feed', $columns, true)) {
            $query->where('show_in_weather_feed', true);
        } else {
            $this->applyLegacyKeywordFilter($query, $columns);
        }

        $dateColumn = $this->firstExistingColumn($columns, [
            'published_at',
            'starts_at',
            'created_at',
            'updated_at',
            'id',
        ]);

        if ($dateColumn) {
            $query->orderByDesc($dateColumn);
        }

        return $query
            ->limit(3)
            ->get()
            ->map(fn ($announcement) => $this->formatAnnouncement($announcement));
    }

    private function applyLegacyKeywordFilter($query, array $columns): void
    {
        $searchableColumns = array_values(array_intersect($columns, [
            'type',
            'category',
            'category_name',
            'priority',
            'severity',
            'title',
            'announcement_title',
            'headline',
            'subject',
            'message',
            'body',
            'content',
            'description',
            'announcement_message',
            'announcement_body',
        ]));

        if (empty($searchableColumns)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $keywords = [
            'weather',
            'disaster',
            'calamity',
            'emergency',
            'typhoon',
            'storm',
            'rain',
            'rainfall',
            'flood',
            'flooding',
            'evacuation',
            'landslide',
            'warning',
            'danger',
            'mdrrmo',
            'pagasa',
        ];

        $query->where(function ($keywordQuery) use ($searchableColumns, $keywords) {
            foreach ($searchableColumns as $column) {
                foreach ($keywords as $keyword) {
                    $keywordQuery->orWhere($column, 'like', '%' . $keyword . '%');
                }
            }
        });
    }

    private function formatAnnouncement(object $announcement): array
    {
        $title = $this->firstFilledValue($announcement, [
            'title',
            'announcement_title',
            'headline',
            'subject',
        ]) ?? 'Weather / Disaster Advisory';

        $message = $this->firstFilledValue($announcement, [
            'message',
            'body',
            'content',
            'description',
            'announcement_message',
            'announcement_body',
        ]) ?? 'Please check the Announcements module for details.';

        $type = $this->firstFilledValue($announcement, [
            'severity',
            'priority',
            'type',
            'category',
            'category_name',
        ]) ?? 'advisory';

        $publishedAt = $this->firstFilledValue($announcement, [
            'published_at',
            'starts_at',
            'created_at',
            'updated_at',
        ]);

        return [
            'id' => $announcement->id ?? null,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'severity' => $this->severityFromText($title . ' ' . $message . ' ' . $type),
            'published_at' => $publishedAt,
        ];
    }

    private function severityFromText(string $text): string
    {
        $text = strtolower($text);

        if (
            str_contains($text, 'danger')
            || str_contains($text, 'emergency')
            || str_contains($text, 'evacuation')
        ) {
            return 'danger';
        }

        if (
            str_contains($text, 'warning')
            || str_contains($text, 'typhoon')
            || str_contains($text, 'flood')
            || str_contains($text, 'calamity')
        ) {
            return 'warning';
        }

        if (
            str_contains($text, 'watch')
            || str_contains($text, 'rain')
            || str_contains($text, 'weather')
        ) {
            return 'watch';
        }

        return 'info';
    }

    private function allowedAudiences(): array
    {
        $user = Auth::user();
        $role = strtolower((string) ($user?->role ?? ''));

        $audiences = [
            'everyone',
            'all',
            'public',
            'general',
            $role,
        ];

        if ($role !== '') {
            $audiences[] = $role . 's';
        }

        if ($role === 'resident') {
            $audiences[] = 'residents';
        }

        if ($role === 'tanod') {
            $audiences[] = 'tanods';
        }

        if ($role === 'official' || $role === 'dao') {
            $audiences[] = 'officials';
            $audiences[] = 'dao';
        }

        if ($role === 'admin') {
            $audiences[] = 'admins';
        }

        return array_values(array_unique(array_filter($audiences)));
    }

    private function announcementsUrl(): ?string
    {
        $user = Auth::user();
        $role = strtolower((string) ($user?->role ?? ''));

        $routeName = match ($role) {
            'admin' => 'admin.announcements.index',
            'official', 'dao' => 'official.announcements.index',
            'tanod' => 'tanod.announcements.index',
            'resident' => 'resident.announcements.index',
            default => null,
        };

        if (! $routeName || ! Route::has($routeName)) {
            return null;
        }

        return URL::route($routeName);
    }

    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function firstFilledValue(object $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (! property_exists($row, $column)) {
                continue;
            }

            $value = $row->{$column};

            if ($value === null) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\SqlLikePattern;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(
            'viewAny',
            ActivityLog::class
        );

        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:200',
            ],
            'category' => [
                'nullable',
                'string',
                'max:50',
                'regex:/\A[A-Za-z0-9_.-]+\z/',
            ],
            'event' => [
                'nullable',
                'string',
                'max:100',
                'regex:/\A[A-Za-z0-9_.-]+\z/',
            ],
            'actor_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
            'per_page' => [
                'nullable',
                'integer',
                Rule::in([
                    10,
                    25,
                    50,
                    100,
                    250,
                ]),
            ],
        ]);

        $search = SqlLikePattern::normalize(
            $validated['search'] ?? null
        );

        $searchPattern = SqlLikePattern::contains(
            $search
        );

        $category = $this->nullableString(
            $validated['category'] ?? null
        );

        $event = $this->nullableString(
            $validated['event'] ?? null
        );

        $actorId = isset($validated['actor_id'])
            ? (int) $validated['actor_id']
            : null;

        $dateFrom = isset($validated['date_from'])
            ? CarbonImmutable::createFromFormat(
                'Y-m-d',
                $validated['date_from'],
                config('app.timezone')
            )->startOfDay()
            : null;

        $dateTo = isset($validated['date_to'])
            ? CarbonImmutable::createFromFormat(
                'Y-m-d',
                $validated['date_to'],
                config('app.timezone')
            )->endOfDay()
            : null;

        $query = ActivityLog::query()
            ->when(
                $searchPattern !== null,
                function (
                    Builder $query
                ) use (
                    $searchPattern
                ): void {
                    $query->where(
                        function (
                            Builder $searchQuery
                        ) use (
                            $searchPattern
                        ): void {
                            SqlLikePattern::whereContains(
                                $searchQuery,
                                'event',
                                $searchPattern
                            );

                            foreach ([
                                'category',
                                'description',
                                'actor_name',
                                'actor_role',
                                'target_name',
                                'target_role',
                                'route_name',
                                'ip_address',
                            ] as $column) {
                                SqlLikePattern::orWhereContains(
                                    $searchQuery,
                                    $column,
                                    $searchPattern
                                );
                            }
                        }
                    );
                }
            )
            ->when(
                $category !== null,
                fn (Builder $query): Builder =>
                    $query->where(
                        'category',
                        $category
                    )
            )
            ->when(
                $event !== null,
                fn (Builder $query): Builder =>
                    $query->where(
                        'event',
                        $event
                    )
            )
            ->when(
                $actorId !== null,
                fn (Builder $query): Builder =>
                    $query->where(
                        'actor_id',
                        $actorId
                    )
            )
            ->when(
                $dateFrom !== null,
                fn (Builder $query): Builder =>
                    $query->where(
                        'created_at',
                        '>=',
                        $dateFrom
                    )
            )
            ->when(
                $dateTo !== null,
                fn (Builder $query): Builder =>
                    $query->where(
                        'created_at',
                        '<=',
                        $dateTo
                    )
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $perPage = (int) (
            $validated['per_page']
            ?? 50
        );

        $activityLogs = $query
            ->paginate($perPage)
            ->withQueryString();

        $categories = ActivityLog::query()
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->limit(100)
            ->pluck('category');

        $events = ActivityLog::query()
            ->whereNotNull('event')
            ->where('event', '<>', '')
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->limit(300)
            ->pluck('event');

        $actors = ActivityLog::query()
            ->whereNotNull('actor_id')
            ->whereNotNull('actor_name')
            ->where('actor_name', '<>', '')
            ->select([
                'actor_id',
                'actor_name',
                'actor_role',
            ])
            ->distinct()
            ->orderBy('actor_name')
            ->limit(500)
            ->get();

        return view(
            'admin.activity-logs.index',
            [
                'activityLogs' => $activityLogs,
                'categories' => $categories,
                'events' => $events,
                'actors' => $actors,
                'filters' => [
                    'search' => $search ?? '',
                    'category' => $category ?? '',
                    'event' => $event ?? '',
                    'actor_id' => $actorId,
                    'date_from' =>
                        $validated['date_from']
                        ?? '',
                    'date_to' =>
                        $validated['date_to']
                        ?? '',
                    'per_page' => $perPage,
                ],
            ]
        );
    }

    public function show(
        ActivityLog $activityLog
    ): View {
        Gate::authorize(
            'view',
            $activityLog
        );

        return view(
            'admin.activity-logs.show',
            [
                'activityLog' => $activityLog,
                'displayMetadata' =>
                    $this->redactForDisplay(
                        $activityLog->metadata
                        ?? []
                    ),
            ]
        );
    }

    private function nullableString(
        mixed $value
    ): ?string {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        return $value === ''
            ? null
            : $value;
    }

    private function redactForDisplay(
        mixed $value,
        ?string $key = null
    ): mixed {
        if (
            $key !== null
            && $this->isSensitiveKey(
                $key
            )
        ) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $redacted = [];

            foreach (
                $value
                as $itemKey => $itemValue
            ) {
                $redacted[$itemKey] =
                    $this->redactForDisplay(
                        $itemValue,
                        is_string($itemKey)
                            ? $itemKey
                            : null
                    );
            }

            return $redacted;
        }

        if (is_object($value)) {
            return $this->redactForDisplay(
                (array) $value,
                $key
            );
        }

        if (is_string($value)) {
            return mb_substr(
                $value,
                0,
                2000
            );
        }

        if (
            is_null($value)
            || is_bool($value)
            || is_int($value)
            || is_float($value)
        ) {
            return $value;
        }

        return '[UNSUPPORTED VALUE]';
    }

    private function isSensitiveKey(
        string $key
    ): bool {
        $normalized = strtolower(
            str_replace(
                [
                    '-',
                    '.',
                    ' ',
                ],
                '_',
                trim($key)
            )
        );

        return in_array(
            $normalized,
            [
                'password',
                'password_confirmation',
                'current_password',
                'new_password',
                'token',
                'access_token',
                'refresh_token',
                'reset_token',
                'remember_token',
                'authorization',
                'proxy_authorization',
                'cookie',
                'cookies',
                'set_cookie',
                'secret',
                'client_secret',
                'api_key',
                'private_key',
                'app_key',
                'db_password',
                'mail_password',
            ],
            true
        );
    }
}

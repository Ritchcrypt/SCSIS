<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\SqlLikePattern;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
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

        $filters = $this->filters(
            $validated
        );

        $query = $this->applyFilters(
            ActivityLog::query(),
            $filters
        )
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $activityLogs = $query
            ->paginate(
                $filters['per_page']
            )
            ->withQueryString();

        $categories = ActivityLog::query()
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->limit(100)
            ->pluck('category')
            ->values();

        $events = ActivityLog::query()
            ->whereNotNull('event')
            ->where('event', '<>', '')
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->limit(300)
            ->pluck('event')
            ->values();

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
            ->get()
            ->map(
                fn (ActivityLog $activityLog): array => [
                    'id' =>
                        (int) $activityLog
                            ->actor_id,
                    'name' =>
                        (string) $activityLog
                            ->actor_name,
                    'role' =>
                        $activityLog
                            ->actor_role,
                    'label' =>
                        (string) $activityLog
                            ->actor_name
                        . (
                            filled(
                                $activityLog
                                    ->actor_role
                            )
                            ? ' ('
                                . ucfirst(
                                    (string)
                                        $activityLog
                                            ->actor_role
                                )
                                . ')'
                            : ''
                        ),
                ]
            )
            ->values();

        return response()
            ->json([
                'data' =>
                    $activityLogs
                        ->getCollection()
                        ->map(
                            fn (
                                ActivityLog
                                    $activityLog
                            ): array =>
                                $this
                                    ->serializeIndex(
                                        $activityLog
                                    )
                        )
                        ->values()
                        ->all(),

                'matching_records' =>
                    $activityLogs->total(),

                'filters' => [
                    'search' =>
                        $filters['search']
                        ?? '',
                    'category' =>
                        $filters['category']
                        ?? '',
                    'event' =>
                        $filters['event']
                        ?? '',
                    'actor_id' =>
                        $filters['actor_id'],
                    'date_from' =>
                        $validated[
                            'date_from'
                        ]
                        ?? '',
                    'date_to' =>
                        $validated[
                            'date_to'
                        ]
                        ?? '',
                    'per_page' =>
                        $filters['per_page'],
                ],

                'options' => [
                    'categories' =>
                        $categories,
                    'events' => $events,
                    'actors' => $actors,
                    'per_page' => [
                        10,
                        25,
                        50,
                        100,
                        250,
                    ],
                ],

                'pagination' => [
                    'current_page' =>
                        $activityLogs
                            ->currentPage(),
                    'last_page' =>
                        $activityLogs
                            ->lastPage(),
                    'per_page' =>
                        $activityLogs
                            ->perPage(),
                    'total' =>
                        $activityLogs
                            ->total(),
                    'from' =>
                        $activityLogs
                            ->firstItem(),
                    'to' =>
                        $activityLogs
                            ->lastItem(),
                ],

                /*
                 * This describes the capture mechanism without
                 * exposing proxy/header internals to the client.
                 */
                'ip_capture' => [
                    'source' =>
                        'Laravel request()->ip()',
                    'trusted_proxy_support' =>
                        true,
                    'trusted_proxy_configured' =>
                        trim(
                            (string) env(
                                'TRUSTED_PROXIES',
                                ''
                            )
                        ) !== '',
                    'development_note' =>
                        '127.0.0.1 or ::1 is expected when requests reach Laravel through localhost or ADB reverse. Production should keep request()->ip() and configure TRUSTED_PROXIES only for the actual deployment proxy.',
                ],

                'permissions' => [
                    'can_view' => true,
                    'read_only' => true,
                ],
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    public function show(
        ActivityLog $activityLog
    ): JsonResponse {
        Gate::authorize(
            'view',
            $activityLog
        );

        return response()
            ->json([
                'data' => [
                    ...$this
                        ->serializeIndex(
                            $activityLog
                        ),
                    'target_user_id' =>
                        $activityLog
                            ->target_user_id,
                    'target_name' =>
                        $activityLog
                            ->target_name,
                    'target_role' =>
                        $activityLog
                            ->target_role,
                    'route_name' =>
                        $activityLog
                            ->route_name,
                    'request_method' =>
                        $activityLog
                            ->request_method,
                    'user_agent' =>
                        $activityLog
                            ->user_agent,
                    'metadata' =>
                        $this
                            ->redactForDisplay(
                                $activityLog
                                    ->metadata
                                ?? []
                            ),
                ],
                'permissions' => [
                    'can_view' => true,
                    'read_only' => true,
                ],
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }

    private function filters(
        array $validated
    ): array {
        $search = SqlLikePattern::normalize(
            $validated['search']
            ?? null
        );

        $category =
            $this->nullableString(
                $validated['category']
                ?? null
            );

        $event =
            $this->nullableString(
                $validated['event']
                ?? null
            );

        $actorId =
            isset(
                $validated['actor_id']
            )
            ? (int) $validated[
                'actor_id'
            ]
            : null;

        $dateFrom =
            isset(
                $validated['date_from']
            )
            ? CarbonImmutable
                ::createFromFormat(
                    'Y-m-d',
                    $validated[
                        'date_from'
                    ],
                    config(
                        'app.timezone'
                    )
                )
                ->startOfDay()
            : null;

        $dateTo =
            isset(
                $validated['date_to']
            )
            ? CarbonImmutable
                ::createFromFormat(
                    'Y-m-d',
                    $validated[
                        'date_to'
                    ],
                    config(
                        'app.timezone'
                    )
                )
                ->endOfDay()
            : null;

        return [
            'search' => $search,
            'search_pattern' =>
                SqlLikePattern::contains(
                    $search
                ),
            'category' => $category,
            'event' => $event,
            'actor_id' => $actorId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'per_page' => (int) (
                $validated[
                    'per_page'
                ]
                ?? 50
            ),
        ];
    }

    private function applyFilters(
        Builder $query,
        array $filters
    ): Builder {
        $searchPattern =
            $filters[
                'search_pattern'
            ];

        return $query
            ->when(
                $searchPattern !== null,
                function (
                    Builder $query
                ) use (
                    $searchPattern
                ): void {
                    $query->where(
                        function (
                            Builder
                                $searchQuery
                        ) use (
                            $searchPattern
                        ): void {
                            SqlLikePattern
                                ::whereContains(
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
                                SqlLikePattern
                                    ::orWhereContains(
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
                $filters[
                    'category'
                ] !== null,
                fn (
                    Builder $query
                ): Builder =>
                    $query->where(
                        'category',
                        $filters[
                            'category'
                        ]
                    )
            )
            ->when(
                $filters['event']
                    !== null,
                fn (
                    Builder $query
                ): Builder =>
                    $query->where(
                        'event',
                        $filters[
                            'event'
                        ]
                    )
            )
            ->when(
                $filters['actor_id']
                    !== null,
                fn (
                    Builder $query
                ): Builder =>
                    $query->where(
                        'actor_id',
                        $filters[
                            'actor_id'
                        ]
                    )
            )
            ->when(
                $filters['date_from']
                    !== null,
                fn (
                    Builder $query
                ): Builder =>
                    $query->where(
                        'created_at',
                        '>=',
                        $filters[
                            'date_from'
                        ]
                    )
            )
            ->when(
                $filters['date_to']
                    !== null,
                fn (
                    Builder $query
                ): Builder =>
                    $query->where(
                        'created_at',
                        '<=',
                        $filters[
                            'date_to'
                        ]
                    )
            );
    }

    private function serializeIndex(
        ActivityLog $activityLog
    ): array {
        $ipAddress =
            $this->nullableString(
                $activityLog
                    ->ip_address
            );

        return [
            'id' =>
                (int) $activityLog->id,
            'actor_id' =>
                $activityLog->actor_id,
            'actor_name' =>
                $activityLog
                    ->actor_name,
            'actor_role' =>
                $activityLog
                    ->actor_role,
            'event' =>
                (string)
                    $activityLog->event,
            'event_label' =>
                str(
                    str_replace(
                        '.',
                        ' ',
                        (string)
                            $activityLog
                                ->event
                    )
                )->headline()->toString(),
            'category' =>
                (string)
                    $activityLog
                        ->category,
            'category_label' =>
                str(
                    (string)
                        $activityLog
                            ->category
                )->headline()->toString(),
            'description' =>
                (string)
                    $activityLog
                        ->description,
            'ip_address' =>
                $ipAddress,
            'ip_context' =>
                $this->ipContext(
                    $ipAddress
                ),
            'created_at' =>
                $activityLog
                    ->created_at
                    ? $activityLog
                        ->created_at
                        ->toIso8601String()
                    : null,
        ];
    }

    private function ipContext(
        ?string $ipAddress
    ): array {
        if (! $ipAddress) {
            return [
                'type' =>
                    'unavailable',
                'label' =>
                    'Unavailable',
                'note' =>
                    'No request IP was recorded for this audit event.',
            ];
        }

        if (
            in_array(
                $ipAddress,
                [
                    '127.0.0.1',
                    '::1',
                ],
                true
            )
        ) {
            return [
                'type' =>
                    'loopback',
                'label' =>
                    'Loopback / Local bridge',
                'note' =>
                    'This is a real loopback address, not a static placeholder. It is expected when the request reaches Laravel through localhost or ADB reverse.',
            ];
        }

        $public =
            filter_var(
                $ipAddress,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE
                | FILTER_FLAG_NO_RES_RANGE
            ) !== false;

        return [
            'type' =>
                $public
                ? 'public'
                : 'private_or_reserved',
            'label' =>
                $public
                ? 'Public / proxy-resolved'
                : 'Private / reserved network',
            'note' =>
                $public
                ? 'Laravel recorded the resolved client address for this request.'
                : 'Laravel recorded a private or reserved network address for this request.',
        ];
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
                    $this
                        ->redactForDisplay(
                            $itemValue,
                            is_string(
                                $itemKey
                            )
                            ? $itemKey
                            : null
                        );
            }

            return $redacted;
        }

        if (is_object($value)) {
            return $this
                ->redactForDisplay(
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

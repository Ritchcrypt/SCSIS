<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TanodAlertController extends Controller
{
    use RecordsOperationalActivity;

    /**
     * These are the operational notification types that belong in
     * Tanod Alerts. Announcements intentionally remain in the
     * Announcements module and are not duplicated here.
     */
    private const ALERT_TYPES = [
        'dispatch',
        'escalation',
        'emergency',
        'calamity',
        'resolved',
        'tanod_alert',
    ];

    private const FILTER_TYPES = [
        'dispatch',
        'escalation',
        'emergency',
        'calamity',
        'resolved',
    ];

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', UserNotification::class);

        $user = $this->authenticatedUser($request);

        $validated = $request->validate([
            'type' => [
                'nullable',
                'string',
                'max:50',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $selectedType = strtolower(
            trim((string) ($validated['type'] ?? 'all'))
        );

        if (
            $selectedType !== 'all'
            && ! in_array(
                $selectedType,
                self::FILTER_TYPES,
                true
            )
        ) {
            $selectedType = 'all';
        }

        $baseQuery = $this->ownedAlertQuery($user);

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'unread' => (clone $baseQuery)
                ->where('is_read', false)
                ->count(),
            'acknowledged' => (clone $baseQuery)
                ->whereNotNull('acknowledged_at')
                ->count(),
        ];

        $query = $this->ownedAlertQuery($user)
            ->with([
                'acknowledgedBy:id,name,role',
            ]);

        if ($selectedType !== 'all') {
            $query->where('type', $selectedType);
        }

        $alerts = $query
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        $items = collect($alerts->items())
            ->map(
                fn (UserNotification $alert): array =>
                    $this->formatAlert($alert)
            )
            ->values();

        return response()->json([
            'data' => $items,
            'stats' => $stats,
            'selected_type' => $selectedType,
            'filter_options' => [
                [
                    'value' => 'all',
                    'label' => 'All types',
                ],
                ...array_map(
                    fn (string $type): array => [
                        'value' => $type,
                        'label' => $this->typeLabel($type),
                    ],
                    self::FILTER_TYPES
                ),
            ],
            'pagination' => [
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
                'has_more' => $alerts->hasMorePages(),
            ],
        ]);
    }

    public function markRead(
        Request $request,
        UserNotification $alert
    ): JsonResponse {
        Gate::authorize('update', $alert);

        $this->ensureOperationalAlert($request, $alert);

        if (! (bool) $alert->is_read) {
            $alert->forceFill([
                'is_read' => true,
                'read_at' => $alert->read_at ?? now(),
            ])->save();
        }

        $alert->loadMissing([
            'acknowledgedBy:id,name,role',
        ]);

        return response()->json([
            'message' => 'Alert marked as read.',
            'data' => $this->formatAlert($alert),
        ]);
    }

    public function acknowledge(
        Request $request,
        UserNotification $alert
    ): JsonResponse {
        Gate::authorize('acknowledge', $alert);

        $user = $this->authenticatedUser($request);
        $this->ensureOperationalAlert($request, $alert);

        $changed = false;

        DB::transaction(function () use (
            $alert,
            $user,
            &$changed
        ): void {
            $lockedAlert = UserNotification::query()
                ->whereKey($alert->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAlert->acknowledged_at !== null) {
                return;
            }

            $lockedAlert->forceFill([
                'acknowledged_by' => $user->id,
                'acknowledged_at' => now(),
                'is_read' => true,
                'read_at' => $lockedAlert->read_at ?? now(),
            ])->save();

            $changed = true;
        });

        $alert->refresh();
        $alert->load([
            'acknowledgedBy:id,name,role',
        ]);

        if ($changed) {
            $this->recordOperationalActivity(
                event: 'tanod_alert.acknowledged',
                category: 'tanod_alert',
                description: 'A Tanod Alert was acknowledged.',
                metadata: [
                    'notification_id' => (int) $alert->id,
                    'type' => (string) $alert->type,
                    'source_id' => $alert->source_id,
                ],
                request: $request,
            );
        }

        return response()->json([
            'message' => $changed
                ? 'Alert acknowledged.'
                : 'Alert was already acknowledged.',
            'data' => $this->formatAlert($alert),
        ]);
    }

    public function destroy(
        Request $request,
        UserNotification $alert
    ): JsonResponse {
        Gate::authorize('delete', $alert);

        $this->ensureOperationalAlert($request, $alert);

        $metadata = [
            'notification_id' => (int) $alert->id,
            'type' => (string) $alert->type,
            'source_id' => $alert->source_id,
        ];

        $alert->delete();

        $this->recordOperationalActivity(
            event: 'tanod_alert.deleted',
            category: 'tanod_alert',
            description: 'A Tanod Alert was deleted.',
            metadata: $metadata,
            request: $request,
        );

        return response()->json([
            'message' => 'Alert deleted successfully.',
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Gate::authorize(
            'markAllRead',
            UserNotification::class
        );

        $user = $this->authenticatedUser($request);

        $updated = $this->ownedAlertQuery($user)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->recordOperationalActivity(
                event: 'tanod_alert.marked_all_read',
                category: 'tanod_alert',
                description: 'All unread Tanod Alerts were marked as read.',
                metadata: [
                    'updated_count' => $updated,
                ],
                request: $request,
            );
        }

        return response()->json([
            'message' => $updated > 0
                ? 'All alerts marked as read.'
                : 'There were no unread alerts.',
            'updated_count' => $updated,
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        Gate::authorize(
            'deleteAny',
            UserNotification::class
        );

        $user = $this->authenticatedUser($request);

        $count = $this->ownedAlertQuery($user)->count();

        if ($count > 0) {
            $this->ownedAlertQuery($user)->delete();

            $this->recordOperationalActivity(
                event: 'tanod_alert.cleared',
                category: 'tanod_alert',
                description: 'All owned Tanod Alerts were cleared.',
                metadata: [
                    'deleted_count' => $count,
                ],
                request: $request,
            );
        }

        return response()->json([
            'message' => $count > 0
                ? 'All alerts cleared.'
                : 'There were no alerts to clear.',
            'deleted_count' => $count,
        ]);
    }

    private function ownedAlertQuery(User $user): Builder
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->whereIn('type', self::ALERT_TYPES);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        return $user;
    }

    private function ensureOperationalAlert(
        Request $request,
        UserNotification $alert
    ): void {
        $user = $this->authenticatedUser($request);

        if (
            (int) $alert->user_id !== (int) $user->id
            || ! in_array(
                strtolower(trim((string) $alert->type)),
                self::ALERT_TYPES,
                true
            )
        ) {
            abort(404);
        }
    }

    private function formatAlert(
        UserNotification $alert
    ): array {
        $type = strtolower(
            trim((string) $alert->type)
        );

        $relatedTarget = match ($type) {
            'dispatch',
            'escalation',
            'emergency',
            'resolved' => $alert->source_id
                ? 'incident'
                : null,
            'calamity' => 'announcements',
            default => null,
        };

        return [
            'id' => (int) $alert->id,
            'type' => $type,
            'type_label' => $this->typeLabel($type),
            'source_id' => $alert->source_id !== null
                ? (int) $alert->source_id
                : null,
            'title' => (string) ($alert->title ?? ''),
            'message' => (string) ($alert->message ?? ''),
            'is_read' => (bool) $alert->is_read,
            'read_at' => $this->dateValue($alert->read_at),
            'acknowledged_at' =>
                $this->dateValue($alert->acknowledged_at),
            'acknowledged_by' => $alert->acknowledgedBy
                ? [
                    'id' => (int) $alert->acknowledgedBy->id,
                    'name' => (string) $alert->acknowledgedBy->name,
                    'role' => (string) $alert->acknowledgedBy->role,
                ]
                : null,
            'created_at' => $this->dateValue($alert->created_at),
            'updated_at' => $this->dateValue($alert->updated_at),
            'related_target' => $relatedTarget,
            'can_acknowledge' => $alert->acknowledged_at === null,
        ];
    }


    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        try {
            return \Carbon\Carbon::parse($value)
                ->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'dispatch' => 'Dispatch',
            'escalation' => 'Escalation',
            'emergency' => 'Emergency',
            'calamity' => 'Calamity',
            'resolved' => 'Resolved',
            'tanod_alert' => 'Tanod Alert',
            default => ucwords(
                str_replace('_', ' ', $type)
            ),
        };
    }
}

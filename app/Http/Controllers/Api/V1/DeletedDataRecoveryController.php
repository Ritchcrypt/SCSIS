<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeletedDataArchive;
use App\Services\ActivityLogger;
use App\Services\DeletedDataArchiveService;
use App\Support\SqlLikePattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class DeletedDataRecoveryController extends Controller
{
    private const TYPE_LABELS = [
        'incident' => 'Incident',
        'resident_complaint' => 'Resident Feedback',
        'announcement' => 'Announcement',
        'emergency_hotline' => 'Emergency Hotline',
        'tanod_task' => 'Tanod Task',
        'distress_signal' => 'Distress Signal',
    ];

    public function __construct(
        private readonly DeletedDataArchiveService $archives,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DeletedDataArchive::class);

        $status = strtolower(trim((string) $request->query('status', 'recoverable')));
        if (! in_array($status, ['recoverable', 'restored', 'all'], true)) {
            $status = 'recoverable';
        }

        $type = strtolower(trim((string) $request->query('type', '')));
        if ($type !== '' && ! array_key_exists($type, self::TYPE_LABELS)) {
            $type = '';
        }

        $search = SqlLikePattern::normalize($request->query('search'));
        $searchPattern = SqlLikePattern::contains($search);

        $query = DeletedDataArchive::query()
            ->where('resource_type', '<>', 'case_record')
            ->when($status === 'recoverable', fn ($q) => $q->whereNull('restored_at'))
            ->when($status === 'restored', fn ($q) => $q->whereNotNull('restored_at'))
            ->when($type !== '', fn ($q) => $q->where('resource_type', $type))
            ->when($searchPattern !== null, function ($q) use ($search, $searchPattern): void {
                $q->where(function ($inner) use ($search, $searchPattern): void {
                    SqlLikePattern::whereContains($inner, 'resource_label', $searchPattern);
                    SqlLikePattern::orWhereContains($inner, 'deleted_by_name', $searchPattern);
                    if (is_numeric($search)) {
                        $inner->orWhere('resource_id', (int) $search);
                    }
                });
            })
            ->orderByDesc('deleted_at')
            ->orderByDesc('id');

        $records = $query->paginate(20)->withQueryString();

        return response()->json([
            'data' => collect($records->items())->map(fn (DeletedDataArchive $archive) => [
                'id' => (int) $archive->id,
                'resource_type' => $archive->resource_type,
                'resource_type_label' => self::TYPE_LABELS[$archive->resource_type] ?? $archive->resource_type,
                'resource_id' => (int) $archive->resource_id,
                'resource_label' => $archive->resource_label,
                'deleted_by_name' => $archive->deleted_by_name,
                'deleted_by_role' => $archive->deleted_by_role,
                'deleted_at' => $archive->deleted_at?->toIso8601String(),
                'restored_at' => $archive->restored_at?->toIso8601String(),
                'can_restore' => Gate::allows('restore', $archive)
                    && $archive->resource_type !== 'case_record',
            ])->values()->all(),
            'summary' => [
                'recoverable' => DeletedDataArchive::query()
                    ->where('resource_type', '<>', 'case_record')
                    ->whereNull('restored_at')->count(),
                'restored' => DeletedDataArchive::query()
                    ->where('resource_type', '<>', 'case_record')
                    ->whereNotNull('restored_at')->count(),
            ],
            'filters' => [
                'status' => $status,
                'type' => $type,
                'search' => $search ?? '',
            ],
            'types' => self::TYPE_LABELS,
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    public function restore(Request $request, DeletedDataArchive $deletedDataArchive): JsonResponse
    {
        Gate::authorize('restore', $deletedDataArchive);
        abort_if($deletedDataArchive->resource_type === 'case_record', 404);

        try {
            $this->archives->restore($deletedDataArchive, $request->user());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Recovery could not be completed. Check the server logs for details.',
            ], 422);
        }

        $deletedDataArchive->refresh();

        $this->activityLogger->record(
            event: 'data_recovery.restored',
            category: 'data_recovery',
            description: 'Administrator restored deleted application data.',
            actor: $request->user(),
            metadata: [
                'deleted_data_archive_id' => (int) $deletedDataArchive->id,
                'resource_type' => $deletedDataArchive->resource_type,
                'resource_id' => (int) $deletedDataArchive->resource_id,
                'resource_label' => $deletedDataArchive->resource_label,
            ],
            request: $request,
        );

        return response()->json([
            'message' => 'Deleted data restored successfully.',
            'data' => [
                'id' => (int) $deletedDataArchive->id,
                'restored_at' => $deletedDataArchive->restored_at?->toIso8601String(),
            ],
        ]);
    }
}

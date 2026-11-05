<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeletedDataArchive;
use App\Services\ActivityLogger;
use App\Services\DeletedDataArchiveService;
use App\Support\SqlLikePattern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
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
        private readonly ActivityLogger $activityLogger
    ) {
    }

    public function index(Request $request): View
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

        $records = DeletedDataArchive::query()
            ->whereIn('resource_type', array_keys(self::TYPE_LABELS))
            ->when(
                $status === 'recoverable',
                fn ($query) => $query->whereNull('restored_at')
            )
            ->when(
                $status === 'restored',
                fn ($query) => $query->whereNotNull('restored_at')
            )
            ->when(
                $type !== '',
                fn ($query) => $query->where('resource_type', $type)
            )
            ->when(
                $searchPattern !== null,
                function ($query) use ($search, $searchPattern): void {
                    $query->where(function ($searchQuery) use ($search, $searchPattern): void {
                        SqlLikePattern::whereContains(
                            $searchQuery,
                            'resource_label',
                            $searchPattern
                        );

                        SqlLikePattern::orWhereContains(
                            $searchQuery,
                            'deleted_by_name',
                            $searchPattern
                        );

                        if (is_numeric($search)) {
                            $searchQuery->orWhere('resource_id', (int) $search);
                        }
                    });
                }
            )
            ->latest('deleted_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.activity-logs.recovery', [
            'records' => $records,
            'typeLabels' => self::TYPE_LABELS,
            'filters' => [
                'status' => $status,
                'type' => $type,
                'search' => $search ?? '',
            ],
            'summary' => [
                'recoverable' => DeletedDataArchive::query()
                    ->whereIn('resource_type', array_keys(self::TYPE_LABELS))
                    ->whereNull('restored_at')
                    ->count(),
                'restored' => DeletedDataArchive::query()
                    ->whereIn('resource_type', array_keys(self::TYPE_LABELS))
                    ->whereNotNull('restored_at')
                    ->count(),
            ],
        ]);
    }

    /**
     * Permanently discard recovery snapshots, not live application records.
     * Only types exposed on this recovery page are in scope. Auditing is
     * deliberately stored in the separate, untouched activity_logs table.
     */
    public function destroyAll(Request $request): RedirectResponse
    {
        Gate::authorize('deleteAny', DeletedDataArchive::class);

        $deletedCount = DeletedDataArchive::query()
            ->whereIn('resource_type', array_keys(self::TYPE_LABELS))
            ->delete();

        $this->activityLogger->record(
            event: 'data_recovery.archives_purged',
            category: 'data_recovery',
            description: 'Administrator permanently deleted recovery snapshots.',
            actor: $request->user(),
            metadata: ['deleted_archive_count' => $deletedCount],
            request: $request,
        );

        return redirect()
            ->route('admin.activity-logs.deleted-data.index')
            ->with(
                'success',
                $deletedCount === 1
                    ? '1 recovery record was permanently deleted. Live application data was not affected.'
                    : number_format($deletedCount)
                        .' recovery records were permanently deleted. Live application data was not affected.'
            );
    }

    public function restore(
        Request $request,
        DeletedDataArchive $deletedDataArchive
    ): RedirectResponse {
        Gate::authorize('restore', $deletedDataArchive);

        abort_unless(
            array_key_exists($deletedDataArchive->resource_type, self::TYPE_LABELS),
            404
        );

        try {
            $this->archives->restore(
                $deletedDataArchive,
                $request->user()
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'restore' => $exception->getMessage(),
            ]);
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

        return redirect()
            ->route('admin.activity-logs.deleted-data.index')
            ->with('success', 'Deleted data restored successfully.');
    }
}

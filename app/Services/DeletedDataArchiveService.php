<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\CaseRecord;
use App\Models\DeletedDataArchive;
use App\Models\EmergencyHotline;
use App\Models\Incident;
use App\Models\MobileEmergencyAlert;
use App\Models\ResidentComplaint;
use App\Models\TanodTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DeletedDataArchiveService
{
    private const SINGLE_ROUTE_MAP = [
        'admin.incidents.destroy' => ['incident', 'incident', Incident::class],
        'api.v1.incidents.destroy' => ['incident', 'incident', Incident::class],
        'admin.resident-complaints.destroy' => ['resident_complaint', 'residentComplaint', ResidentComplaint::class],
        'api.v1.resident-complaints.destroy' => ['resident_complaint', 'residentComplaint', ResidentComplaint::class],
        'api.v1.cases.destroy' => ['case_record', 'caseRecord', CaseRecord::class],
        'admin.announcements.destroy' => ['announcement', 'announcement', Announcement::class],
        'official.announcements.destroy' => ['announcement', 'announcement', Announcement::class],
        'api.v1.announcements.destroy' => ['announcement', 'announcement', Announcement::class],
        'admin.emergency-mode.destroy' => ['emergency_hotline', 'emergencyHotline', EmergencyHotline::class],
        'api.v1.emergency-hotlines.destroy' => ['emergency_hotline', 'emergencyHotline', EmergencyHotline::class],
        'admin.tanod-tasks.destroy' => ['tanod_task', 'tanodTask', TanodTask::class],
        'api.v1.tanod-tasks.destroy' => ['tanod_task', 'tanodTask', TanodTask::class],
        'emergency-alerts.destroy.post' => ['distress_signal', 'emergencyAlert', MobileEmergencyAlert::class],
        'emergency-alerts.destroy' => ['distress_signal', 'emergencyAlert', MobileEmergencyAlert::class],
        'api.v1.emergency-alerts.destroy' => ['distress_signal', 'emergencyAlert', MobileEmergencyAlert::class],
    ];

    private const BULK_DISTRESS_ROUTES = [
        'emergency-alerts.destroy-all.post',
        'emergency-alerts.destroy-all',
        'api.v1.emergency-alerts.destroy-all',
    ];

    private const RESOURCE_DEFINITIONS = [
        'incident' => [
            'root' => 'incidents',
            'children' => [
                ['evidence', 'incident_id'],
                ['incident_evidence', 'incident_id'],
                ['incident_evidences', 'incident_id'],
                ['incident_attachments', 'incident_id'],
                ['attachments', 'incident_id'],
                ['incident_messages', 'incident_id'],
                ['incident_status_histories', 'incident_id'],
                ['incident_status_history', 'incident_id'],
                ['incident_escalations', 'incident_id'],
                ['case_records', 'incident_id'],
            ],
            'references' => [
                ['tanod_tasks', 'incident_id'],
                ['emergency_agency_logs', 'incident_id'],
            ],
        ],
        'resident_complaint' => [
            'root' => 'resident_complaints',
            'children' => [
                ['resident_complaint_proofs', 'resident_complaint_id'],
            ],
            'references' => [],
        ],
        'case_record' => [
            'root' => 'case_records',
            'children' => [],
            'references' => [],
        ],
        'announcement' => [
            'root' => 'announcements',
            'children' => [
                ['announcement_barangay', 'announcement_id'],
            ],
            'references' => [],
        ],
        'emergency_hotline' => [
            'root' => 'emergency_hotlines',
            'children' => [],
            'references' => [],
        ],
        'tanod_task' => [
            'root' => 'tanod_tasks',
            'children' => [
                ['tanod_task_responses', 'tanod_task_id'],
            ],
            'references' => [],
        ],
        'distress_signal' => [
            'root' => 'mobile_emergency_alerts',
            'children' => [],
            'references' => [],
        ],
    ];

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    public function __construct(
        private readonly SecureUploadService $secureUploads
    ) {
    }

    /**
     * @return array<int, int>
     */
    public function captureForRequest(Request $request): array
    {
        if (! Schema::hasTable('deleted_data_archives')) {
            return [];
        }

        $routeName = $request->route()?->getName();

        if (! is_string($routeName) || $routeName === '') {
            return [];
        }

        if (in_array($routeName, self::BULK_DISTRESS_ROUTES, true)) {
            return MobileEmergencyAlert::query()
                ->orderBy('id')
                ->get()
                ->map(fn (MobileEmergencyAlert $alert): ?int => $this->capture(
                    'distress_signal',
                    $alert,
                    $request
                )?->id)
                ->filter()
                ->values()
                ->all();
        }

        $mapping = self::SINGLE_ROUTE_MAP[$routeName] ?? null;

        if (! is_array($mapping)) {
            return [];
        }

        [$resourceType, $parameterName, $modelClass] = $mapping;
        $routeValue = $request->route($parameterName);

        if ($routeValue instanceof $modelClass) {
            $model = $routeValue;
        } elseif (is_scalar($routeValue)) {
            /** @var Model|null $model */
            $model = $modelClass::query()->find($routeValue);
        } else {
            $model = null;
        }

        if (! $model instanceof Model) {
            return [];
        }

        $archive = $this->capture(
            $resourceType,
            $model,
            $request
        );

        return $archive ? [(int) $archive->id] : [];
    }

    public function discardIfOriginalStillExists(array $archiveIds): void
    {
        if ($archiveIds === [] || ! Schema::hasTable('deleted_data_archives')) {
            return;
        }

        DeletedDataArchive::query()
            ->whereIn('id', array_map('intval', $archiveIds))
            ->get()
            ->each(function (DeletedDataArchive $archive): void {
                $payload = $archive->payload;
                $rootTable = (string) data_get($payload, 'root.table', '');
                $rootId = data_get($payload, 'root.row.id');

                if (
                    $rootTable === ''
                    || $rootId === null
                    || ! Schema::hasTable($rootTable)
                    || ! DB::table($rootTable)->where('id', $rootId)->exists()
                ) {
                    return;
                }

                $this->deleteBackupFiles(is_array($payload) ? $payload : []);
                $archive->delete();
            });
    }

    public function restore(
        DeletedDataArchive $archive,
        User $restoredBy
    ): void {
        $archive->refresh();

        if ($archive->restored_at !== null) {
            throw new RuntimeException('This deleted-data record has already been restored.');
        }

        $payload = $archive->payload;

        if (! is_array($payload)) {
            throw new RuntimeException('The recovery payload is invalid.');
        }

        $definition = $this->definition($archive->resource_type);
        $this->validatePayload($payload, $definition);

        $createdFiles = $this->restoreBackupFiles($payload);

        try {
            DB::transaction(function () use (
                $archive,
                $payload,
                $definition,
                $restoredBy
            ): void {
                $lockedArchive = DeletedDataArchive::query()
                    ->lockForUpdate()
                    ->findOrFail($archive->id);

                if ($lockedArchive->restored_at !== null) {
                    throw new RuntimeException('This deleted-data record has already been restored.');
                }

                $root = $payload['root'];
                $rootTable = (string) $root['table'];
                $rootRow = (array) $root['row'];
                $rootId = $rootRow['id'] ?? null;

                if ($rootId === null) {
                    throw new RuntimeException('The recovery root record has no primary key.');
                }

                if (DB::table($rootTable)->where('id', $rootId)->exists()) {
                    throw new RuntimeException(
                        'Restore blocked because a current record already uses the original ID.'
                    );
                }

                DB::table($rootTable)->insert($rootRow);

                foreach ((array) ($payload['children'] ?? []) as $childGroup) {
                    $table = (string) ($childGroup['table'] ?? '');
                    $rows = (array) ($childGroup['rows'] ?? []);

                    foreach ($rows as $row) {
                        $row = (array) $row;
                        $rowId = $row['id'] ?? null;

                        if ($rowId !== null && DB::table($table)->where('id', $rowId)->exists()) {
                            throw new RuntimeException(
                                "Restore blocked because {$table} already contains ID {$rowId}."
                            );
                        }

                        DB::table($table)->insert($row);
                    }
                }

                foreach ((array) ($payload['references'] ?? []) as $referenceGroup) {
                    $table = (string) ($referenceGroup['table'] ?? '');
                    $foreignKey = (string) ($referenceGroup['foreign_key'] ?? '');
                    $ids = array_values(array_filter(array_map(
                        'intval',
                        (array) ($referenceGroup['ids'] ?? [])
                    )));

                    if ($ids !== []) {
                        DB::table($table)
                            ->whereIn('id', $ids)
                            ->update([$foreignKey => $archive->resource_id]);
                    }
                }

                $lockedArchive->forceFill([
                    'restored_by_user_id' => $restoredBy->id,
                    'restored_by_name' => Str::limit((string) $restoredBy->name, 255, ''),
                    'restored_at' => now(),
                ])->save();
            });
        } catch (Throwable $exception) {
            $this->removeRestoredFiles($createdFiles);

            throw $exception;
        }
    }

    private function capture(
        string $resourceType,
        Model $model,
        Request $request
    ): ?DeletedDataArchive {
        $definition = $this->definition($resourceType);
        $rootTable = $definition['root'];
        $resourceId = (int) $model->getKey();
        $rootRow = DB::table($rootTable)->where('id', $resourceId)->first();

        if (! $rootRow) {
            return null;
        }

        $children = [];

        foreach ($definition['children'] as [$table, $foreignKey]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $foreignKey)) {
                continue;
            }

            $children[] = [
                'table' => $table,
                'foreign_key' => $foreignKey,
                'rows' => DB::table($table)
                    ->where($foreignKey, $resourceId)
                    ->orderBy('id')
                    ->get()
                    ->map(fn (object $row): array => (array) $row)
                    ->all(),
            ];
        }

        $references = [];

        foreach ($definition['references'] as [$table, $foreignKey]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $foreignKey)) {
                continue;
            }

            $references[] = [
                'table' => $table,
                'foreign_key' => $foreignKey,
                'ids' => DB::table($table)
                    ->where($foreignKey, $resourceId)
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all(),
            ];
        }

        $payload = [
            'version' => 1,
            'root' => [
                'table' => $rootTable,
                'row' => (array) $rootRow,
            ],
            'children' => $children,
            'references' => $references,
            'files' => [],
        ];

        /** @var User|null $actor */
        $actor = $request->user();

        $archive = DeletedDataArchive::query()->create([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'resource_label' => $this->resourceLabel($resourceType, $model),
            'deleted_by_user_id' => $actor?->id,
            'deleted_by_name' => $actor?->name,
            'deleted_by_role' => $actor?->role,
            'source_route' => $request->route()?->getName(),
            'source_method' => strtoupper($request->method()),
            'payload' => $payload,
            'deleted_at' => now(),
        ]);

        try {
            $payload['files'] = $this->backupFiles(
                $archive,
                $resourceType,
                $payload
            );

            $archive->forceFill(['payload' => $payload])->save();
        } catch (Throwable $exception) {
            $this->deleteBackupFiles($payload);
            $archive->delete();

            throw $exception;
        }

        return $archive;
    }

    private function backupFiles(
        DeletedDataArchive $archive,
        string $resourceType,
        array $payload
    ): array {
        $paths = $this->filePaths($resourceType, $payload);
        $allowedPrefixes = match ($resourceType) {
            'incident' => ['incidents/evidence'],
            'resident_complaint' => ['resident-complaints'],
            default => [],
        };

        if ($allowedPrefixes === []) {
            return [];
        }

        $files = [];

        foreach ($paths as $path) {
            $storedFile = $this->secureUploads->resolve(
                $path,
                $allowedPrefixes,
                self::ALLOWED_MIME_TYPES
            );

            if (! $storedFile) {
                $files[] = [
                    'original_path' => $path,
                    'original_disk' => null,
                    'backup_path' => null,
                    'missing_at_delete' => true,
                ];
                continue;
            }

            $backupPath = 'recovery/deleted-data/'
                . $archive->id
                . '/'
                . Str::uuid()
                . '-'
                . basename($storedFile['path']);

            $stream = Storage::disk($storedFile['disk'])->readStream(
                $storedFile['path']
            );

            if (! is_resource($stream)) {
                throw new RuntimeException('A recoverable file could not be opened for backup.');
            }

            try {
                $written = Storage::disk('local')->put($backupPath, $stream);
            } finally {
                fclose($stream);
            }

            if (! $written) {
                throw new RuntimeException('A recoverable file could not be copied to recovery storage.');
            }

            $files[] = [
                'original_path' => $storedFile['path'],
                'original_disk' => $storedFile['disk'],
                'backup_path' => $backupPath,
                'missing_at_delete' => false,
            ];
        }

        return $files;
    }

    private function restoreBackupFiles(array $payload): array
    {
        $created = [];

        foreach ((array) ($payload['files'] ?? []) as $file) {
            if ((bool) ($file['missing_at_delete'] ?? false)) {
                continue;
            }

            $originalPath = (string) ($file['original_path'] ?? '');
            $originalDisk = (string) ($file['original_disk'] ?? '');
            $backupPath = (string) ($file['backup_path'] ?? '');

            if ($originalPath === '' || $originalDisk === '' || $backupPath === '') {
                throw new RuntimeException('A recovery file descriptor is incomplete.');
            }

            if (Storage::disk($originalDisk)->exists($originalPath)) {
                continue;
            }

            if (! Storage::disk('local')->exists($backupPath)) {
                throw new RuntimeException('A required recovery file is missing from private recovery storage.');
            }

            $stream = Storage::disk('local')->readStream($backupPath);

            if (! is_resource($stream)) {
                throw new RuntimeException('A recovery file could not be opened.');
            }

            try {
                $written = Storage::disk($originalDisk)->put($originalPath, $stream);
            } finally {
                fclose($stream);
            }

            if (! $written) {
                throw new RuntimeException('A recovery file could not be restored.');
            }

            $created[] = [
                'disk' => $originalDisk,
                'path' => $originalPath,
            ];
        }

        return $created;
    }

    private function removeRestoredFiles(array $files): void
    {
        foreach ($files as $file) {
            $disk = (string) ($file['disk'] ?? '');
            $path = (string) ($file['path'] ?? '');

            if ($disk !== '' && $path !== '') {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    private function deleteBackupFiles(array $payload): void
    {
        foreach ((array) ($payload['files'] ?? []) as $file) {
            $backupPath = (string) ($file['backup_path'] ?? '');

            if ($backupPath !== '') {
                Storage::disk('local')->delete($backupPath);
            }
        }
    }

    private function filePaths(string $resourceType, array $payload): array
    {
        $paths = [];

        if ($resourceType === 'resident_complaint') {
            $rootRow = (array) data_get($payload, 'root.row', []);
            $paths[] = $rootRow['evidence_path'] ?? null;

            foreach ((array) ($payload['children'] ?? []) as $group) {
                if (($group['table'] ?? null) !== 'resident_complaint_proofs') {
                    continue;
                }

                foreach ((array) ($group['rows'] ?? []) as $row) {
                    $paths[] = ((array) $row)['proof_path'] ?? null;
                }
            }
        }

        if ($resourceType === 'incident') {
            $fileColumns = ['file_path', 'path', 'file_url', 'url'];

            foreach ((array) ($payload['children'] ?? []) as $group) {
                foreach ((array) ($group['rows'] ?? []) as $row) {
                    $row = (array) $row;

                    foreach ($fileColumns as $column) {
                        $paths[] = $row[$column] ?? null;
                    }
                }
            }
        }

        return collect($paths)
            ->filter(fn (mixed $path): bool => is_scalar($path))
            ->map(fn (mixed $path): string => trim((string) $path))
            ->filter(fn (string $path): bool => $path !== '' && ! str_starts_with($path, 'http'))
            ->unique()
            ->values()
            ->all();
    }

    private function resourceLabel(string $resourceType, Model $model): string
    {
        $label = match ($resourceType) {
            'incident' => $model->incident_code
                ?: $model->incident_title
                ?: $model->title
                ?: 'Incident #' . $model->getKey(),
            'resident_complaint' => $model->complainant_name
                ?: 'Complaint #' . $model->getKey(),
            'case_record' => $model->case_number
                ? 'Case ' . $model->case_number
                : 'Case #' . $model->getKey(),
            'announcement' => $model->title
                ?: 'Announcement #' . $model->getKey(),
            'emergency_hotline' => $model->agency_name
                ?: 'Emergency Hotline #' . $model->getKey(),
            'tanod_task' => $model->title
                ?: 'Tanod Task #' . $model->getKey(),
            'distress_signal' => $model->alert_code
                ?: 'Distress Signal #' . $model->getKey(),
            default => $resourceType . ' #' . $model->getKey(),
        };

        return Str::limit(trim((string) $label), 255, '');
    }

    private function definition(string $resourceType): array
    {
        $definition = self::RESOURCE_DEFINITIONS[$resourceType] ?? null;

        if (! is_array($definition)) {
            throw new RuntimeException('This deleted-data resource type is not recoverable.');
        }

        return $definition;
    }

    private function validatePayload(array $payload, array $definition): void
    {
        $rootTable = (string) data_get($payload, 'root.table', '');

        if ($rootTable !== $definition['root']) {
            throw new RuntimeException('The recovery payload root table is invalid.');
        }

        $allowedChildren = collect($definition['children'])
            ->pluck(0)
            ->all();

        foreach ((array) ($payload['children'] ?? []) as $group) {
            if (! in_array((string) ($group['table'] ?? ''), $allowedChildren, true)) {
                throw new RuntimeException('The recovery payload contains an unauthorized child table.');
            }
        }

        $allowedReferences = collect($definition['references'])
            ->map(fn (array $item): string => $item[0] . ':' . $item[1])
            ->all();

        foreach ((array) ($payload['references'] ?? []) as $group) {
            $key = (string) ($group['table'] ?? '')
                . ':'
                . (string) ($group['foreign_key'] ?? '');

            if (! in_array($key, $allowedReferences, true)) {
                throw new RuntimeException('The recovery payload contains an unauthorized relationship reference.');
            }
        }
    }
}

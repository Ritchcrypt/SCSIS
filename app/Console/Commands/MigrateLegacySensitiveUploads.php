<?php

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\SecureUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateLegacySensitiveUploads extends Command
{
    protected $signature = 'secure-uploads:migrate-legacy
        {--dry-run : List the number of legacy sensitive files without moving them}
        {--force : Move legacy sensitive files to private storage}';

    protected $description =
        'Move legacy sensitive uploads from public storage to private storage';

    public function handle(
        SecureUploadService $secureUploads,
        ActivityLogger $activityLogger
    ): int {
        $prefixes = $secureUploads->sensitivePrefixes();

        if ($prefixes === []) {
            $this->error(
                'No sensitive upload prefixes are configured.'
            );

            return self::FAILURE;
        }

        $files = collect($prefixes)
            ->flatMap(
                fn (string $prefix) =>
                    Storage::disk('public')
                        ->allFiles($prefix)
            )
            ->map(
                fn (string $path): ?string =>
                    $secureUploads->normalizePath($path)
            )
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ((bool) $this->option('dry-run')) {
            $this->info(
                $files->count()
                . ' legacy sensitive file(s) are eligible for migration. '
                . 'No files were changed.'
            );

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force')) {
            $this->error(
                'No files were moved. Run with --dry-run first, then add '
                . '--force after reviewing the result.'
            );

            return self::FAILURE;
        }

        $counts = [
            SecureUploadService::LEGACY_MOVED => 0,
            SecureUploadService::LEGACY_DEDUPLICATED => 0,
            SecureUploadService::LEGACY_MISSING => 0,
            SecureUploadService::LEGACY_CONFLICT => 0,
            SecureUploadService::LEGACY_FAILED => 0,
        ];

        foreach ($files as $path) {
            $status = $secureUploads->migrateLegacyFile(
                $path,
                $prefixes
            );

            if (! array_key_exists($status, $counts)) {
                $status = SecureUploadService::LEGACY_FAILED;
            }

            $counts[$status]++;
        }

        $activityLogger->record(
            event: 'secure_uploads.legacy_migrated',
            category: 'security',
            description: 'Legacy sensitive uploads were migrated to private storage.',
            metadata: [
                'eligible_count' => $files->count(),
                'moved_count' =>
                    $counts[SecureUploadService::LEGACY_MOVED],
                'deduplicated_count' =>
                    $counts[SecureUploadService::LEGACY_DEDUPLICATED],
                'missing_count' =>
                    $counts[SecureUploadService::LEGACY_MISSING],
                'conflict_count' =>
                    $counts[SecureUploadService::LEGACY_CONFLICT],
                'failed_count' =>
                    $counts[SecureUploadService::LEGACY_FAILED],
            ],
        );

        $this->table(
            [
                'Result',
                'Count',
            ],
            [
                [
                    'Moved',
                    $counts[SecureUploadService::LEGACY_MOVED],
                ],
                [
                    'Public duplicate removed',
                    $counts[SecureUploadService::LEGACY_DEDUPLICATED],
                ],
                [
                    'Already missing',
                    $counts[SecureUploadService::LEGACY_MISSING],
                ],
                [
                    'Conflict',
                    $counts[SecureUploadService::LEGACY_CONFLICT],
                ],
                [
                    'Failed',
                    $counts[SecureUploadService::LEGACY_FAILED],
                ],
            ]
        );

        if (
            $counts[SecureUploadService::LEGACY_CONFLICT] > 0
            || $counts[SecureUploadService::LEGACY_FAILED] > 0
        ) {
            $this->error(
                'Migration completed with conflicts or failures. '
                . 'No conflicting public file was overwritten.'
            );

            return self::FAILURE;
        }

        $this->info(
            'Legacy sensitive upload migration completed successfully.'
        );

        return self::SUCCESS;
    }
}

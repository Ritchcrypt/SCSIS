<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SecureUploadService
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    /**
     * Store an already validated upload using a random server-generated name.
     */
    public function store(
        UploadedFile $file,
        string $policy
    ): string {
        $configuration = $this->policy(
            $policy
        );

        $disk = (string) (
            $configuration['disk']
            ?? 'local'
        );

        $directory = $this->safeDirectory(
            (string) (
                $configuration['directory']
                ?? ''
            )
        );

        $mimeType = $this->detectMimeType(
            $file->getRealPath()
        );

        $extension = self::MIME_EXTENSIONS[$mimeType] ?? null;

        if ($extension === null) {
            throw new RuntimeException(
                'The uploaded file type cannot be stored.'
            );
        }

        $filename = Str::uuid()
            . '.'
            . $extension;

        $path = Storage::disk($disk)->putFileAs(
            $directory,
            $file,
            $filename
        );

        if (
            ! is_string($path)
            || $path === ''
        ) {
            throw new RuntimeException(
                'The uploaded file could not be stored.'
            );
        }

        return $this->normalizePath(
            $path
        );
    }

    /**
     * Resolve an authorised private file, with public-disk fallback for legacy
     * uploads created before Phase 8.
     *
     * @return array{
     *     disk: string,
     *     path: string,
     *     absolute_path: string,
     *     mime_type: string
     * }|null
     */
    public function resolve(
        mixed $path,
        array $allowedPrefixes,
        array $allowedMimeTypes
    ): ?array {
        $path = $this->normalizePath(
            $path
        );

        if (
            $path === null
            || ! $this->hasAllowedPrefix(
                $path,
                $allowedPrefixes
            )
        ) {
            return null;
        }

        $allowedMimeTypes = array_map(
            static fn (mixed $item): string => Str::lower(
                trim((string) $item)
            ),
            $allowedMimeTypes
        );

        foreach ($this->readDisks() as $disk) {
            if (! Storage::disk($disk)->exists($path)) {
                continue;
            }

            $absolutePath = Storage::disk($disk)->path(
                $path
            );

            if (
                ! is_file($absolutePath)
                || ! is_readable($absolutePath)
            ) {
                continue;
            }

            $mimeType = $this->detectMimeType(
                $absolutePath
            );

            if (
                $mimeType === null
                || ! in_array(
                    $mimeType,
                    $allowedMimeTypes,
                    true
                )
            ) {
                continue;
            }

            return [
                'disk' => $disk,
                'path' => $path,
                'absolute_path' => $absolutePath,
                'mime_type' => $mimeType,
            ];
        }

        return null;
    }

    /**
     * Delete a stored file from the private disk and any approved legacy disk.
     */
    public function delete(
        mixed $path,
        array $allowedPrefixes
    ): void {
        $path = $this->normalizePath(
            $path
        );

        if (
            $path === null
            || ! $this->hasAllowedPrefix(
                $path,
                $allowedPrefixes
            )
        ) {
            return;
        }

        foreach ($this->readDisks() as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    public function normalizePath(
        mixed $path
    ): ?string {
        if (! is_scalar($path)) {
            return null;
        }

        $path = str_replace(
            '\\',
            '/',
            trim((string) $path)
        );

        $path = preg_replace(
            '#^/?storage/#',
            '',
            $path
        );

        $path = preg_replace(
            '#^/?public/#',
            '',
            (string) $path
        );

        $path = preg_replace(
            '#^/?private/#',
            '',
            (string) $path
        );

        $path = ltrim(
            (string) $path,
            '/'
        );

        if (
            $path === ''
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
        ) {
            return null;
        }

        return $path;
    }

    public function safeOriginalName(
        mixed $name,
        string $fallback
    ): string {
        $name = basename(
            str_replace(
                '\\',
                '/',
                trim((string) $name)
            )
        );

        $name = preg_replace(
            '/[\x00-\x1F\x7F"\\\\\/]+/',
            '_',
            $name
        );

        $name = trim(
            (string) $name,
            " .\t\n\r\0\x0B"
        );

        if ($name === '') {
            $name = $fallback;
        }

        return Str::limit(
            $name,
            255,
            ''
        );
    }

    public function policy(
        string $policy
    ): array {
        $configuration = config(
            "secure_uploads.policies.{$policy}"
        );

        if (! is_array($configuration)) {
            throw new RuntimeException(
                "Secure upload policy [{$policy}] is not configured."
            );
        }

        return $configuration;
    }

    private function readDisks(): array
    {
        return array_values(
            array_unique([
                'local',
                ...array_map(
                    static fn (mixed $disk): string => trim(
                        (string) $disk
                    ),
                    (array) config(
                        'secure_uploads.legacy_read_disks',
                        [
                            'public',
                        ]
                    )
                ),
            ])
        );
    }

    private function safeDirectory(
        string $directory
    ): string {
        $directory = trim(
            str_replace(
                '\\',
                '/',
                $directory
            ),
            '/'
        );

        if (
            $directory === ''
            || str_contains($directory, '..')
            || preg_match(
                '#^[A-Za-z0-9/_-]+$#',
                $directory
            ) !== 1
        ) {
            throw new RuntimeException(
                'The secure upload directory is invalid.'
            );
        }

        return $directory;
    }

    private function hasAllowedPrefix(
        string $path,
        array $allowedPrefixes
    ): bool {
        foreach ($allowedPrefixes as $prefix) {
            $prefix = trim(
                str_replace(
                    '\\',
                    '/',
                    (string) $prefix
                ),
                '/'
            );

            if (
                $prefix !== ''
                && (
                    $path === $prefix
                    || str_starts_with(
                        $path,
                        $prefix . '/'
                    )
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function detectMimeType(
        string|false $path
    ): ?string {
        if (
            ! is_string($path)
            || $path === ''
            || ! is_file($path)
        ) {
            return null;
        }

        $finfo = new \finfo(
            FILEINFO_MIME_TYPE
        );

        $mimeType = $finfo->file(
            $path
        );

        if (! is_string($mimeType)) {
            return null;
        }

        $mimeType = Str::lower(
            trim(
                explode(
                    ';',
                    $mimeType,
                    2
                )[0]
            )
        );

        return $mimeType !== ''
            ? $mimeType
            : null;
    }
}

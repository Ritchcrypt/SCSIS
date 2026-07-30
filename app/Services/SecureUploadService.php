<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SecureUploadService
{
    public const LEGACY_MOVED = 'moved';
    public const LEGACY_DEDUPLICATED = 'deduplicated';
    public const LEGACY_MISSING = 'missing';
    public const LEGACY_CONFLICT = 'conflict';
    public const LEGACY_FAILED = 'failed';

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
            ?? $this->privateDisk()
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

        $normalizedPath = $this->normalizePath(
            $path
        );

        if ($normalizedPath === null) {
            Storage::disk($disk)->delete(
                $path
            );

            throw new RuntimeException(
                'The stored upload path is invalid.'
            );
        }

        return $normalizedPath;
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
     * Delete a stored file from the private disk and approved legacy disks.
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

    /**
     * Delete several paths after the related database transaction commits.
     */
    public function deleteMany(
        array $paths,
        array $allowedPrefixes
    ): void {
        foreach (
            array_values(
                array_unique($paths)
            ) as $path
        ) {
            $this->delete(
                $path,
                $allowedPrefixes
            );
        }
    }

    /**
     * Move one legacy sensitive upload from the public disk to the private
     * disk without changing its database path.
     */
    public function migrateLegacyFile(
        mixed $path,
        array $allowedPrefixes
    ): string {
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
            return self::LEGACY_FAILED;
        }

        $public = Storage::disk('public');
        $private = Storage::disk(
            $this->privateDisk()
        );

        if (! $public->exists($path)) {
            return self::LEGACY_MISSING;
        }

        if ($private->exists($path)) {
            try {
                if (
                    $private->size($path)
                    === $public->size($path)
                ) {
                    $public->delete($path);

                    return self::LEGACY_DEDUPLICATED;
                }
            } catch (Throwable) {
                return self::LEGACY_CONFLICT;
            }

            return self::LEGACY_CONFLICT;
        }

        $stream = $public->readStream(
            $path
        );

        if (! is_resource($stream)) {
            return self::LEGACY_FAILED;
        }

        try {
            $written = $private->put(
                $path,
                $stream
            );
        } finally {
            fclose($stream);
        }

        if (! $written) {
            return self::LEGACY_FAILED;
        }

        try {
            $verified = $private->exists($path)
                && $private->size($path)
                    === $public->size($path);
        } catch (Throwable) {
            $verified = false;
        }

        if (! $verified) {
            $private->delete($path);

            return self::LEGACY_FAILED;
        }

        if (! $public->delete($path)) {
            $private->delete($path);

            return self::LEGACY_FAILED;
        }

        return self::LEGACY_MOVED;
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

    public function sensitivePrefixes(): array
    {
        return array_values(
            array_filter(
                array_map(
                    fn (mixed $prefix): string => $this->safePrefix(
                        (string) $prefix
                    ),
                    (array) config(
                        'secure_uploads.sensitive_prefixes',
                        []
                    )
                )
            )
        );
    }

    private function readDisks(): array
    {
        return array_values(
            array_unique([
                $this->privateDisk(),
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

    private function privateDisk(): string
    {
        $disk = trim(
            (string) config(
                'secure_uploads.private_disk',
                'local'
            )
        );

        return $disk !== ''
            ? $disk
            : 'local';
    }

    private function safeDirectory(
        string $directory
    ): string {
        $directory = $this->safePrefix(
            $directory
        );

        if ($directory === '') {
            throw new RuntimeException(
                'The secure upload directory is invalid.'
            );
        }

        return $directory;
    }

    private function safePrefix(
        string $prefix
    ): string {
        $prefix = trim(
            str_replace(
                '\\',
                '/',
                $prefix
            ),
            '/'
        );

        if (
            $prefix === ''
            || str_contains($prefix, '..')
            || preg_match(
                '#^[A-Za-z0-9/_-]+$#',
                $prefix
            ) !== 1
        ) {
            return '';
        }

        return $prefix;
    }

    private function hasAllowedPrefix(
        string $path,
        array $allowedPrefixes
    ): bool {
        foreach ($allowedPrefixes as $prefix) {
            $prefix = $this->safePrefix(
                (string) $prefix
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

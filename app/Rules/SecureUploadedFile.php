<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SecureUploadedFile implements ValidationRule
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => [
            'jpg',
            'jpeg',
        ],
        'image/png' => [
            'png',
        ],
        'image/webp' => [
            'webp',
        ],
        'application/pdf' => [
            'pdf',
        ],
    ];

    private const DANGEROUS_NAME_SEGMENTS = [
        'bat',
        'cmd',
        'com',
        'exe',
        'htm',
        'html',
        'js',
        'jse',
        'mjs',
        'phar',
        'php',
        'php3',
        'php4',
        'php5',
        'phtml',
        'ps1',
        'sh',
        'svg',
        'vbs',
    ];

    public function __construct(
        private readonly string $policy
    ) {
    }

    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute must be a valid uploaded file.');

            return;
        }

        if (! $value->isValid()) {
            $fail('The :attribute upload was not completed successfully.');

            return;
        }

        $configuration = config(
            "secure_uploads.policies.{$this->policy}"
        );

        if (! is_array($configuration)) {
            $fail('The :attribute upload policy is unavailable.');

            return;
        }

        $realPath = $value->getRealPath();

        if (
            ! is_string($realPath)
            || $realPath === ''
            || ! is_file($realPath)
            || ! is_readable($realPath)
        ) {
            $fail('The :attribute file could not be read safely.');

            return;
        }

        $size = $value->getSize();
        $maxKilobytes = max(
            1,
            (int) ($configuration['max_kilobytes'] ?? 1)
        );

        if (
            ! is_int($size)
            || $size < 1
            || $size > ($maxKilobytes * 1024)
        ) {
            $fail(
                "The :attribute must not exceed {$maxKilobytes} KB."
            );

            return;
        }

        $originalName = basename(
            str_replace(
                '\\',
                '/',
                $value->getClientOriginalName()
            )
        );

        if (
            $originalName === ''
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $originalName
            ) === 1
        ) {
            $fail('The :attribute filename is invalid.');

            return;
        }

        $nameSegments = array_map(
            static fn (string $segment): string => Str::lower(
                trim($segment)
            ),
            explode('.', $originalName)
        );

        array_pop($nameSegments);

        if (
            array_intersect(
                $nameSegments,
                self::DANGEROUS_NAME_SEGMENTS
            ) !== []
        ) {
            $fail(
                'The :attribute filename contains a prohibited executable extension.'
            );

            return;
        }

        $extension = Str::lower(
            trim($value->getClientOriginalExtension())
        );

        $allowedExtensions = array_map(
            static fn (mixed $item): string => Str::lower(
                trim((string) $item)
            ),
            (array) ($configuration['allowed_extensions'] ?? [])
        );

        if (
            $extension === ''
            || ! in_array(
                $extension,
                $allowedExtensions,
                true
            )
        ) {
            $fail('The :attribute file extension is not allowed.');

            return;
        }

        $mimeType = $this->detectMimeType($realPath);

        $allowedMimeTypes = array_map(
            static fn (mixed $item): string => Str::lower(
                trim((string) $item)
            ),
            (array) ($configuration['allowed_mime_types'] ?? [])
        );

        if (
            $mimeType === null
            || ! in_array(
                $mimeType,
                $allowedMimeTypes,
                true
            )
        ) {
            $fail('The :attribute file type is not allowed.');

            return;
        }

        $mimeExtensions = self::MIME_EXTENSIONS[$mimeType] ?? [];

        if (
            ! in_array(
                $extension,
                $mimeExtensions,
                true
            )
        ) {
            $fail(
                'The :attribute extension does not match its detected file type.'
            );

            return;
        }

        if (! $this->hasExpectedSignature($realPath, $mimeType)) {
            $fail('The :attribute file signature is invalid.');

            return;
        }

        if (
            str_starts_with(
                $mimeType,
                'image/'
            )
            && ! $this->validateImage(
                $realPath,
                $mimeType,
                $configuration
            )
        ) {
            $fail(
                'The :attribute image is invalid or exceeds the permitted dimensions.'
            );
        }
    }

    private function detectMimeType(
        string $path
    ): ?string {
        $finfo = new \finfo(
            FILEINFO_MIME_TYPE
        );

        $mimeType = $finfo->file($path);

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

    private function hasExpectedSignature(
        string $path,
        string $mimeType
    ): bool {
        $handle = @fopen(
            $path,
            'rb'
        );

        if ($handle === false) {
            return false;
        }

        try {
            $header = fread(
                $handle,
                16
            );
        } finally {
            fclose($handle);
        }

        if (! is_string($header)) {
            return false;
        }

        return match ($mimeType) {
            'image/jpeg' => str_starts_with(
                $header,
                "\xFF\xD8\xFF"
            ),
            'image/png' => str_starts_with(
                $header,
                "\x89PNG\r\n\x1A\n"
            ),
            'image/webp' => strlen($header) >= 12
                && substr($header, 0, 4) === 'RIFF'
                && substr($header, 8, 4) === 'WEBP',
            'application/pdf' => str_starts_with(
                ltrim(
                    $header,
                    "\xEF\xBB\xBF\x00\t\r\n "
                ),
                '%PDF-'
            ),
            default => false,
        };
    }

    private function validateImage(
        string $path,
        string $mimeType,
        array $configuration
    ): bool {
        $imageInfo = @getimagesize(
            $path
        );

        if (
            ! is_array($imageInfo)
            || ! isset(
                $imageInfo[0],
                $imageInfo[1],
                $imageInfo['mime']
            )
        ) {
            return false;
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        $detectedMime = Str::lower(
            trim(
                (string) $imageInfo['mime']
            )
        );

        if (
            $width < 1
            || $height < 1
            || $detectedMime !== $mimeType
        ) {
            return false;
        }

        $maxWidth = max(
            1,
            (int) ($configuration['max_width'] ?? $width)
        );

        $maxHeight = max(
            1,
            (int) ($configuration['max_height'] ?? $height)
        );

        $maxPixels = max(
            1,
            (int) (
                $configuration['max_pixels']
                ?? ($maxWidth * $maxHeight)
            )
        );

        return $width <= $maxWidth
            && $height <= $maxHeight
            && ($width * $height) <= $maxPixels;
    }
}

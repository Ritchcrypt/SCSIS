<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Models\SystemSetting;
use App\Rules\SecureUploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SystemBrandingController extends Controller
{
    use RecordsOperationalActivity;

    public function edit(): View
    {
        Gate::authorize('manageSystemBranding');

        $setting = $this->currentSetting();
        $logoPath = $this->safeLogoPath(
            $setting->system_logo_path
        );

        $logoUrl = $logoPath
            && Storage::disk('public')->exists($logoPath)
            ? route('system-branding.logo')
                . '?v='
                . optional($setting->updated_at)->timestamp
            : null;

        return view('admin.system-branding.edit', [
            'setting' => $setting,
            'logoUrl' => $logoUrl,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manageSystemBranding');

        $validated = $request->validate([
            'system_name' => [
                'required',
                'string',
                'max:100',
                'not_regex:/^\s*$/',
            ],
            'system_subtitle' => [
                'required',
                'string',
                'max:150',
                'not_regex:/^\s*$/',
            ],
            'system_logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=4096,max_height=4096',
                new SecureUploadedFile(
                    'branding_logo'
                ),
            ],
            'remove_logo' => [
                'nullable',
                'boolean',
            ],
        ], [
            'system_name.required' => 'System name is required.',
            'system_name.not_regex' => 'System name is required.',
            'system_subtitle.required' => 'System subtitle is required.',
            'system_subtitle.not_regex' => 'System subtitle is required.',
            'system_logo.image' => 'Logo must be an image file.',
            'system_logo.mimes' => 'Logo must be JPG, JPEG, PNG, or WEBP.',
            'system_logo.max' => 'Logo must not exceed 5MB.',
            'system_logo.dimensions' => 'Logo dimensions must not exceed 4096 × 4096 pixels.',
        ]);

        $setting = $this->currentSetting();

        $previousSystemName = (string) $setting->system_name;
        $previousSystemSubtitle = (string) $setting->system_subtitle;

        $oldLogoPath = $this->safeLogoPath(
            $setting->system_logo_path
        );

        $newLogoPath = null;

        try {
            if ($request->hasFile('system_logo')) {
                $newLogoPath = $this->storeOptimizedLogo(
                    $request->file('system_logo')
                );
            }

            $finalLogoPath = match (true) {
                $newLogoPath !== null => $newLogoPath,
                $request->boolean('remove_logo') => null,
                default => $oldLogoPath,
            };

            DB::transaction(function () use (
                $setting,
                $validated,
                $finalLogoPath
            ): void {
                $lockedSetting = SystemSetting::query()
                    ->lockForUpdate()
                    ->findOrFail($setting->getKey());

                $lockedSetting->update([
                    'system_name' => trim($validated['system_name']),
                    'system_subtitle' => trim(
                        $validated['system_subtitle']
                    ),
                    'system_logo_path' => $finalLogoPath,
                ]);
            });
        } catch (Throwable $exception) {
            if ($newLogoPath) {
                $this->deleteLogo($newLogoPath);
            }

            throw $exception;
        }

        if (
            $oldLogoPath
            && $oldLogoPath !== $newLogoPath
            && (
                $request->boolean('remove_logo')
                || $newLogoPath !== null
            )
        ) {
            $this->deleteLogo($oldLogoPath);
        }

        $setting->refresh();

        $changedFields = [];

        if (
            $previousSystemName
            !== (string) $setting->system_name
        ) {
            $changedFields[] = 'system_name';
        }

        if (
            $previousSystemSubtitle
            !== (string) $setting->system_subtitle
        ) {
            $changedFields[] = 'system_subtitle';
        }

        $logoAction = match (true) {
            $newLogoPath !== null => 'replaced',
            $request->boolean('remove_logo') => 'removed',
            default => 'unchanged',
        };

        if ($logoAction !== 'unchanged') {
            $changedFields[] = 'system_logo';
        }

        $this->recordOperationalActivity(
            event: 'system_branding.updated',
            category: 'configuration',
            description: 'System branding settings were updated.',
            metadata: [
                'system_setting_id' => (int) $setting->id,
                'changed_fields' => $changedFields,
                'logo_action' => $logoAction,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.system-branding.edit')
            ->with(
                'success',
                'System branding updated successfully.'
            );
    }

    public function logo(): BinaryFileResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Public read-only branding asset
        |--------------------------------------------------------------------------
        |
        | Authentication pages need this image before login. This action only
        | reads the existing setting and never creates or updates a DB record.
        |
        */

        $setting = SystemSetting::query()->first();

        if (! $setting) {
            abort(404);
        }

        $logoPath = $this->safeLogoPath(
            $setting->system_logo_path
        );

        if (
            ! $logoPath
            || ! Storage::disk('public')->exists($logoPath)
        ) {
            abort(404);
        }

        $absolutePath = Storage::disk('public')->path($logoPath);

        if (! is_file($absolutePath)) {
            abort(404);
        }

        return response()->file($absolutePath, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function currentSetting(): SystemSetting
    {
        return SystemSetting::query()->firstOrCreate([], [
            'system_name' => 'TabangNow',
            'system_subtitle' => 'Dao, Capiz',
            'system_logo_path' => null,
        ]);
    }

    private function safeLogoPath(mixed $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = str_replace(
            '\\',
            '/',
            trim((string) $path)
        );

        $path = ltrim($path, '/');

        if (
            $path === ''
            || str_contains($path, '..')
            || str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || ! str_starts_with($path, 'system-branding/')
        ) {
            return null;
        }

        return $path;
    }

    private function deleteLogo(?string $logoPath): void
    {
        $logoPath = $this->safeLogoPath($logoPath);

        if (
            $logoPath
            && Storage::disk('public')->exists($logoPath)
        ) {
            Storage::disk('public')->delete($logoPath);
        }
    }

    private function storeOptimizedLogo(
        UploadedFile $file
    ): string {
        if (! extension_loaded('gd')) {
            return $this->storeOriginalLogo($file);
        }

        $imageContents = file_get_contents(
            $file->getRealPath()
        );

        if ($imageContents === false) {
            throw new RuntimeException(
                'The uploaded logo could not be read.'
            );
        }

        $sourceImage = @imagecreatefromstring($imageContents);

        if (! $sourceImage) {
            return $this->storeOriginalLogo($file);
        }

        $newImage = null;
        $path = null;

        try {
            $originalWidth = imagesx($sourceImage);
            $originalHeight = imagesy($sourceImage);

            $maxSize = 512;

            $ratio = min(
                $maxSize / max($originalWidth, 1),
                $maxSize / max($originalHeight, 1),
                1
            );

            $newWidth = max(
                1,
                (int) round($originalWidth * $ratio)
            );

            $newHeight = max(
                1,
                (int) round($originalHeight * $ratio)
            );

            $newImage = imagecreatetruecolor(
                $newWidth,
                $newHeight
            );

            if (! $newImage) {
                throw new RuntimeException(
                    'The logo image could not be prepared.'
                );
            }

            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);

            $transparent = imagecolorallocatealpha(
                $newImage,
                0,
                0,
                0,
                127
            );

            imagefilledrectangle(
                $newImage,
                0,
                0,
                $newWidth,
                $newHeight,
                $transparent
            );

            $resampled = imagecopyresampled(
                $newImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $originalWidth,
                $originalHeight
            );

            if (! $resampled) {
                throw new RuntimeException(
                    'The logo image could not be resized.'
                );
            }

            Storage::disk('public')->makeDirectory(
                'system-branding'
            );

            $extension = function_exists('imagewebp')
                ? 'webp'
                : 'png';

            $path = 'system-branding/logo-'
                . Str::uuid()
                . '.'
                . $extension;

            $fullPath = Storage::disk('public')->path($path);

            $written = $extension === 'webp'
                ? imagewebp($newImage, $fullPath, 85)
                : imagepng($newImage, $fullPath, 6);

            if (! $written || ! is_file($fullPath)) {
                throw new RuntimeException(
                    'The optimized logo could not be saved.'
                );
            }

            return $path;
        } catch (Throwable $exception) {
            if ($path) {
                $this->deleteLogo($path);
            }

            throw $exception;
        } finally {
            imagedestroy($sourceImage);

            if ($newImage) {
                imagedestroy($newImage);
            }
        }
    }

    private function storeOriginalLogo(
        UploadedFile $file
    ): string {
        $extension = strtolower(
            (string) ($file->extension() ?: 'png')
        );

        if (! in_array(
            $extension,
            ['jpg', 'jpeg', 'png', 'webp'],
            true
        )) {
            $extension = 'png';
        }

        $path = $file->storeAs(
            'system-branding',
            'logo-' . Str::uuid() . '.' . $extension,
            'public'
        );

        if (! $path) {
            throw new RuntimeException(
                'The uploaded logo could not be stored.'
            );
        }

        return $path;
    }
}

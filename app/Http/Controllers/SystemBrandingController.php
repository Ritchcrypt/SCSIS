<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemBrandingController extends Controller
{
    public function edit(): View
    {
        $setting = $this->currentSetting();

        $logoUrl = $this->logoExists($setting) && route('system-branding.logo')
            ? route('system-branding.logo') . '?v=' . optional($setting->updated_at)->timestamp
            : null;

        return view('admin.system-branding.edit', [
            'setting' => $setting,
            'logoUrl' => $logoUrl,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = $this->currentSetting();

        $validated = $request->validate([
            'system_name' => ['required', 'string', 'max:100'],
            'system_subtitle' => ['required', 'string', 'max:150'],
            'system_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'system_name.required' => 'System name is required.',
            'system_subtitle.required' => 'System subtitle is required.',
            'system_logo.image' => 'Logo must be an image file.',
            'system_logo.mimes' => 'Logo must be JPG, JPEG, PNG, or WEBP.',
            'system_logo.max' => 'Logo must not exceed 50MB.',
        ]);

        $logoPath = $setting->system_logo_path;

        if ($request->boolean('remove_logo')) {
            $this->deleteLogo($logoPath);
            $logoPath = null;
        }

        if ($request->hasFile('system_logo')) {
            $this->deleteLogo($logoPath);
            $logoPath = $this->storeOptimizedLogo($request->file('system_logo'));
        }

        $setting->update([
            'system_name' => $validated['system_name'],
            'system_subtitle' => $validated['system_subtitle'],
            'system_logo_path' => $logoPath,
        ]);

        return redirect()
            ->route('admin.system-branding.edit')
            ->with('success', 'System branding updated successfully.');
    }

    public function logo(): BinaryFileResponse
    {
        $setting = $this->currentSetting();

        if (! $this->logoExists($setting)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('public')->path($setting->system_logo_path),
            [
                'Cache-Control' => 'public, max-age=31536000',
            ]
        );
    }

    private function currentSetting(): SystemSetting
    {
        return SystemSetting::query()->firstOrCreate([], [
            'system_name' => 'SCSISystem',
            'system_subtitle' => 'Dao, Capiz',
            'system_logo_path' => null,
        ]);
    }

    private function logoExists(SystemSetting $setting): bool
    {
        return $setting->system_logo_path
            && Storage::disk('public')->exists($setting->system_logo_path);
    }

    private function deleteLogo(?string $logoPath): void
    {
        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            Storage::disk('public')->delete($logoPath);
        }
    }

    private function storeOptimizedLogo(UploadedFile $file): string
    {
        if (! extension_loaded('gd')) {
            return $file->store('system-branding', 'public');
        }

        $imageContents = file_get_contents($file->getRealPath());
        $sourceImage = @imagecreatefromstring($imageContents);

        if (! $sourceImage) {
            return $file->store('system-branding', 'public');
        }

        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        $maxSize = 512;

        $ratio = min(
            $maxSize / max($originalWidth, 1),
            $maxSize / max($originalHeight, 1),
            1
        );

        $newWidth = max(1, (int) round($originalWidth * $ratio));
        $newHeight = max(1, (int) round($originalHeight * $ratio));

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);

        imagecopyresampled(
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

        Storage::disk('public')->makeDirectory('system-branding');

        $extension = function_exists('imagewebp') ? 'webp' : 'png';
        $path = 'system-branding/logo-' . Str::uuid() . '.' . $extension;
        $fullPath = Storage::disk('public')->path($path);

        if ($extension === 'webp') {
            imagewebp($newImage, $fullPath, 85);
        } else {
            imagepng($newImage, $fullPath, 6);
        }

        imagedestroy($sourceImage);
        imagedestroy($newImage);

        return $path;
    }
}
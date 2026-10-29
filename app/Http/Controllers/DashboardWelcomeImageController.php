<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Models\SystemSetting;
use App\Rules\SecureUploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** The same welcome image is served to all authenticated dashboard roles. */
class DashboardWelcomeImageController extends Controller
{
    use RecordsOperationalActivity;

    private const DEFAULT_IMAGE = 'images/dashboard/dao-welcome-scene-20261022_173013_735.jpg';

    public static function version(): string
    {
        $path = self::currentPath();

        return substr(hash('sha256', $path ?? 'default-banner'), 0, 20);
    }

    public static function currentPath(): ?string
    {
        $setting = SystemSetting::query()->first();
        $path = self::safePath($setting?->dashboard_welcome_path);

        return $path !== null && Storage::disk('local')->exists($path)
            ? $path
            : null;
    }

    private static function safePath(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        return preg_match('/\Adashboard-welcome\/[a-zA-Z0-9_-]+\.(?:jpe?g|png|webp)\z/', $path) === 1
            ? $path
            : null;
    }

    public function show(): BinaryFileResponse
    {
        $path = self::currentPath();

        if ($path === null) {
            $fallback = public_path(self::DEFAULT_IMAGE);
            $type = 'image/jpeg';
        } else {
            $fallback = Storage::disk('local')->path($path);
            $type = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            };
        }

        return response()->file($fallback, [
            'Content-Type' => $type,
            'Cache-Control' => 'private, max-age=60',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'version' => self::version(),
            'url' => route('dashboard-welcome.image', [
                'v' => self::version(),
            ]),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request): RedirectResponse
    {
        // Route is additionally protected by auth, active.user and role:admin.
        abort_unless($request->user()?->role === 'admin', 403);

        $request->validate([
            'welcome_image' => [
                'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                'dimensions:max_width=4096,max_height=4096',
                new SecureUploadedFile('profile_photo'),
            ],
        ], [
            'welcome_image.required' => 'Choose an image first.',
            'welcome_image.image' => 'Choose a valid image.',
            'welcome_image.mimes' => 'Only JPG, PNG, and WebP images are allowed.',
            'welcome_image.max' => 'The image must not exceed 5 MB.',
            'welcome_image.dimensions' => 'The maximum image dimensions are 4096 by 4096 pixels.',
        ]);

        $uploaded = $request->file('welcome_image');
        $newPath = $uploaded->store('dashboard-welcome', 'local');

        if (! is_string($newPath) || self::safePath($newPath) === null) {
            if (is_string($newPath)) {
                Storage::disk('local')->delete($newPath);
            }
            return back()->withErrors(['welcome_image' => 'Unable to save the image.']);
        }

        $oldPath = null;

        try {
            // The initial settings row already exists in normal installations.
            SystemSetting::query()->firstOrCreate([], [
                'system_name' => 'TabangNow',
                'system_subtitle' => 'Dao, Capiz',
                'system_logo_path' => null,
            ]);

            DB::transaction(function () use ($newPath, &$oldPath): void {
                $setting = SystemSetting::query()->lockForUpdate()->firstOrFail();
                $oldPath = self::safePath($setting->dashboard_welcome_path);
                $setting->dashboard_welcome_path = $newPath;
                $setting->save();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        // Delete only images previously owned by this specific feature.
        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        $this->recordOperationalActivity(
            event: 'dashboard.welcome_image.updated',
            category: 'configuration',
            description: 'An administrator replaced the shared dashboard welcome image.',
            metadata: ['asset' => 'dashboard-welcome'],
            request: $request,
        );

        return redirect()->route('admin.dashboard')
            ->with('welcome_image_success', 'Welcome image updated for all dashboard users.');
    }
}

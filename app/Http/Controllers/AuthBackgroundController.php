<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Models\SystemSetting;
use App\Rules\SecureUploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Admin-managed public auth image, independent from dashboard background and logo. */
final class AuthBackgroundController extends Controller
{
    use RecordsOperationalActivity;

    private static function safePath(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        return preg_match(
            '/\Aauth-background\/[a-f0-9-]{36}\.(?:jpg|jpeg|png|webp)\z/D',
            $path
        ) === 1 ? $path : null;
    }

    private static function currentPath(?SystemSetting $setting = null): ?string
    {
        if (! Schema::hasTable('system_settings') || ! Schema::hasColumn('system_settings', 'auth_background_path')) {
            return null;
        }

        $setting ??= SystemSetting::query()->first();
        $path = self::safePath($setting?->auth_background_path);

        return $path && Storage::disk('local')->exists($path) ? $path : null;
    }

    /** Returns null when the original bundled image should be used. */
    public static function uploadedUrl(?SystemSetting $setting = null): ?string
    {
        $path = self::currentPath($setting);

        return $path ? route('auth-background.image', [
            'v' => substr(hash('sha256', $path), 0, 16),
        ]) : null;
    }

    /** The image is intentionally public because it must render before login. */
    public function show(): BinaryFileResponse|RedirectResponse
    {
        $path = self::currentPath();
        if ($path === null) {
            return redirect()->to(asset('images/auth/dao-auth-photo-20261022_181835_038.jpg'), 302, [
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $type = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $type,
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeEditor(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin' && $request->user()->isActive(), 403);
        abort_unless(Schema::hasColumn('system_settings', 'auth_background_path'), 503, 'Run the authentication-background migration first.');
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeEditor($request);

        $request->validate([
            'auth_background' => [
                'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                new SecureUploadedFile('auth_background'),
            ],
        ], [
            'auth_background.required' => 'Select a background image first.',
            'auth_background.image' => 'The file must be a valid image.',
            'auth_background.mimes' => 'Only JPG, PNG and WebP images are accepted.',
            'auth_background.max' => 'The image must be 5 MB or smaller.',
        ]);

        $file = $request->file('auth_background');
        $extension = strtolower((string) $file->extension());
        abort_unless(in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true), 422);

        $newPath = $file->storeAs(
            'auth-background', (string) Str::uuid().'.'.$extension, 'local'
        );

        if (! is_string($newPath) || self::safePath($newPath) === null) {
            if (is_string($newPath)) {
                Storage::disk('local')->delete($newPath);
            }
            return back()->withErrors(['auth_background' => 'Unable to save this background image.']);
        }

        $oldPath = null;
        try {
            SystemSetting::query()->firstOrCreate([], [
                'system_name' => 'TabangNow',
                'system_subtitle' => 'Dao, Capiz',
                'system_logo_path' => null,
            ]);

            DB::transaction(function () use ($newPath, &$oldPath): void {
                $setting = SystemSetting::query()->lockForUpdate()->firstOrFail();
                $oldPath = self::safePath($setting->auth_background_path);
                $setting->auth_background_path = $newPath;
                $setting->save();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        $this->recordOperationalActivity(
            event: 'system_branding.auth_background_updated',
            category: 'configuration',
            description: 'An administrator updated the public authentication background.',
            metadata: ['asset' => 'auth-background'],
            request: $request,
        );

        return redirect()->route('admin.system-branding.edit')
            ->with('success', 'Login and registration background updated.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->authorizeEditor($request);

        $oldPath = null;
        DB::transaction(function () use (&$oldPath): void {
            $setting = SystemSetting::query()->lockForUpdate()->first();
            if (! $setting) {
                return;
            }
            $oldPath = self::safePath($setting->auth_background_path);
            $setting->auth_background_path = null;
            $setting->save();
        });

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        $this->recordOperationalActivity(
            event: 'system_branding.auth_background_reset',
            category: 'configuration',
            description: 'An administrator restored the default public authentication background.',
            metadata: ['asset' => 'auth-background'],
            request: $request,
        );

        return redirect()->route('admin.system-branding.edit')
            ->with('success', 'Default authentication background restored.');
    }
}

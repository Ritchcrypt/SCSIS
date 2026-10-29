<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardWelcomeImageController as WebsiteWelcomeImage;
use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Models\SystemSetting;
use App\Rules\SecureUploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Mobile bearer-token API for the existing shared website image. */
final class DashboardWelcomePhotoController extends Controller
{
    use RecordsOperationalActivity;

    public function status(): JsonResponse
    {
        if (! Schema::hasColumn('system_settings', 'dashboard_welcome_path')) {
            return response()->json([
                'message' => 'Dashboard photo database migration is pending.',
            ], 503);
        }

        return response()->json([
            'version' => WebsiteWelcomeImage::version(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(WebsiteWelcomeImage $website): BinaryFileResponse
    {
        // Delivers the same private storage image or website fallback.
        return $website->show();
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);

        if (! Schema::hasColumn('system_settings', 'dashboard_welcome_path')) {
            return response()->json([
                'message' => 'Dashboard photo migration is pending. Apply it after a database backup.',
            ], 503);
        }

        $request->validate([
            'welcome_image' => [
                'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                'dimensions:max_width=4096,max_height=4096',
                new SecureUploadedFile('profile_photo'),
            ],
        ]);

        $path = $request->file('welcome_image')->store('dashboard-welcome', 'local');
        if (! is_string($path) || ! self::isOwnedPath($path)) {
            if (is_string($path)) Storage::disk('local')->delete($path);
            return response()->json(['message' => 'Could not store the photo.'], 422);
        }

        $previous = null;
        try {
            SystemSetting::query()->firstOrCreate([], [
                'system_name' => 'TabangNow',
                'system_subtitle' => 'Dao, Capiz',
                'system_logo_path' => null,
            ]);
            DB::transaction(function () use ($path, &$previous): void {
                $setting = SystemSetting::query()->lockForUpdate()->firstOrFail();
                $old = $setting->dashboard_welcome_path;
                $previous = self::isOwnedPath($old) ? $old : null;
                $setting->dashboard_welcome_path = $path;
                $setting->save();
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        if ($previous !== null && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }

        $this->recordOperationalActivity(
            event: 'dashboard.welcome_image.updated',
            category: 'configuration',
            description: 'An administrator replaced the shared dashboard welcome image.',
            metadata: ['asset' => 'dashboard-welcome', 'surface' => 'mobile'],
            request: $request,
        );

        return response()->json([
            'message' => 'Dashboard photo updated for all roles.',
            'version' => WebsiteWelcomeImage::version(),
        ]);
    }

    private static function isOwnedPath(mixed $path): bool
    {
        return is_string($path)
            && preg_match('/\Adashboard-welcome\/[a-zA-Z0-9_-]+\.(?:jpe?g|png|webp)\z/', $path) === 1;
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class PublicDownloadController extends Controller
{
    public function index(): View
    {
        $sizeBytes = (int) config('tabangnow.mobile.apk_size_bytes', 0);

        return view('download', [
            'version' => (string) config('tabangnow.mobile.version', '1.0.0'),
            'sha256' => (string) config('tabangnow.mobile.apk_sha256', ''),
            'sizeLabel' => $sizeBytes > 0
                ? number_format($sizeBytes / 1024 / 1024, 1) . ' MiB'
                : null,
        ]);
    }

    public function apk(): BinaryFileResponse
    {
        $sourceUrl = trim(
            (string) config('tabangnow.mobile.apk_download_url', '')
        );

        if (! $this->isValidHttpsUrl($sourceUrl)) {
            abort(
                503,
                'The TabangNow Android download is temporarily unavailable.'
            );
        }

        $expectedHash = strtoupper(trim(
            (string) config('tabangnow.mobile.apk_sha256', '')
        ));

        if ($expectedHash === '') {
            abort(
                503,
                'The TabangNow Android download is temporarily unavailable.'
            );
        }

        $directory = storage_path('app/tabangnow-distribution');
        $apkPath = $directory . DIRECTORY_SEPARATOR . 'TabangNow.apk';

        if (! $this->isValidCachedApk($apkPath, $expectedHash)) {
            $this->cacheVerifiedApk(
                $sourceUrl,
                $expectedHash,
                $directory,
                $apkPath
            );
        }

        if (! $this->isValidCachedApk($apkPath, $expectedHash)) {
            abort(
                503,
                'The TabangNow Android download is temporarily unavailable.'
            );
        }

        return response()->download(
            $apkPath,
            'TabangNow.apk',
            [
                'Content-Type' => 'application/vnd.android.package-archive',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function cacheVerifiedApk(
        string $sourceUrl,
        string $expectedHash,
        string $directory,
        string $apkPath
    ): void {
        try {
            Cache::lock('tabangnow-apk-distribution-cache', 240)
                ->block(210, function () use (
                    $sourceUrl,
                    $expectedHash,
                    $directory,
                    $apkPath
                ): void {
                    if ($this->isValidCachedApk($apkPath, $expectedHash)) {
                        return;
                    }

                    File::ensureDirectoryExists($directory);

                    $temporaryPath = $apkPath . '.part';

                    File::delete($temporaryPath);

                    try {
                        $response = Http::connectTimeout(20)
                            ->timeout(180)
                            ->retry(
                                2,
                                1000,
                                throw: false
                            )
                            ->withOptions([
                                'allow_redirects' => true,
                                'sink' => $temporaryPath,
                            ])
                            ->get($sourceUrl);
                    } catch (ConnectionException) {
                        File::delete($temporaryPath);

                        abort(
                            503,
                            'The TabangNow Android download is temporarily unavailable.'
                        );
                    }

                    if (
                        ! $response->successful()
                        || ! File::exists($temporaryPath)
                        || strtoupper(hash_file('sha256', $temporaryPath))
                            !== $expectedHash
                    ) {
                        File::delete($temporaryPath);

                        abort(
                            503,
                            'The TabangNow Android download is temporarily unavailable.'
                        );
                    }

                    File::move($temporaryPath, $apkPath);
                });
        } catch (Throwable $exception) {
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $exception;
            }

            abort(
                503,
                'The TabangNow Android download is temporarily unavailable.'
            );
        }
    }

    private function isValidCachedApk(
        string $apkPath,
        string $expectedHash
    ): bool {
        return File::isFile($apkPath)
            && strtoupper(hash_file('sha256', $apkPath)) === $expectedHash;
    }

    private function isValidHttpsUrl(string $url): bool
    {
        return $url !== ''
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && str_starts_with(strtolower($url), 'https://');
    }
}
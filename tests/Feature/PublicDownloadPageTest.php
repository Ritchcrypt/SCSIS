<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicDownloadPageTest extends TestCase
{
    private string $apkDirectory;

    private string $apkPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apkDirectory = storage_path('app/tabangnow-distribution-tests');
        $this->apkPath = $this->apkDirectory . DIRECTORY_SEPARATOR . 'TabangNow.apk';
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->apkDirectory);

        parent::tearDown();
    }

    public function test_public_download_page_is_available(): void
    {
        config()->set(
            'tabangnow.mobile.apk_download_url',
            'https://example.test/TabangNow.apk'
        );

        $response = $this->get('/download');

        $response
            ->assertOk()
            ->assertSee('Download TabangNow')
            ->assertSee('v1.0.0')
            ->assertSee(
                'CAB0693E05D47D9004935E63AB9852FBB354297C25BFF3464ADF05536EDC0509'
            );
    }

    public function test_apk_route_fails_closed_when_download_url_is_missing(): void
    {
        config()->set(
            'tabangnow.mobile.apk_download_url',
            null
        );

        $this->get('/download/apk')
            ->assertStatus(503);
    }

    public function test_apk_route_rejects_non_https_download_url(): void
    {
        config()->set(
            'tabangnow.mobile.apk_download_url',
            'http://example.test/TabangNow.apk'
        );

        $this->get('/download/apk')
            ->assertStatus(503);
    }

    public function test_apk_route_serves_a_verified_cached_apk_as_attachment(): void
    {
        $content = 'tabangnow-test-apk';

        File::ensureDirectoryExists(
            storage_path('app/tabangnow-distribution')
        );

        $productionPath = storage_path(
            'app/tabangnow-distribution/TabangNow.apk'
        );

        File::put($productionPath, $content);

        config()->set(
            'tabangnow.mobile.apk_download_url',
            'https://example.test/TabangNow.apk'
        );

        config()->set(
            'tabangnow.mobile.apk_sha256',
            strtoupper(hash('sha256', $content))
        );

        try {
            $response = $this->get('/download/apk');

            $response
                ->assertOk()
                ->assertHeader(
                    'content-type',
                    'application/vnd.android.package-archive'
                )
                ->assertDownload('TabangNow.apk');
        } finally {
            File::delete($productionPath);
        }
    }
}
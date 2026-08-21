<?php

return [
    'mobile' => [
        /*
        |--------------------------------------------------------------------------
        | Android APK Distribution
        |--------------------------------------------------------------------------
        |
        | Keep the public QR pointed at the stable Laravel /download page.
        |
        | TABANGNOW_APK_DOWNLOAD_URL is the server-side source for the verified
        | APK. Laravel downloads that source into its runtime cache, verifies
        | the SHA-256 below, and then serves the APK to users from TabangNow's
        | own /download/apk route with Android package headers.
        |
        | The runtime cache is intentionally disposable. A Railway redeploy can
        | remove it; the next request will fetch and verify the APK again.
        |
        */

        'apk_download_url' => env('TABANGNOW_APK_DOWNLOAD_URL'),

        'version' => env(
            'TABANGNOW_APK_VERSION',
            '1.0.0'
        ),

        'apk_sha256' => env(
            'TABANGNOW_APK_SHA256',
            'CAB0693E05D47D9004935E63AB9852FBB354297C25BFF3464ADF05536EDC0509'
        ),

        'apk_size_bytes' => (int) env(
            'TABANGNOW_APK_SIZE_BYTES',
            60035318
        ),
    ],
];
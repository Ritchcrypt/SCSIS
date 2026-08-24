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
            '1.0.1'
        ),

        'build_number' => max(
            1,
            (int) env('TABANGNOW_APK_BUILD_NUMBER', 2)
        ),

        'minimum_supported_build_number' => max(
            1,
            (int) env('TABANGNOW_MIN_SUPPORTED_BUILD_NUMBER', 1)
        ),

        'update_message' => env(
            'TABANGNOW_UPDATE_MESSAGE',
            'A newer version of TabangNow is available.'
        ),

        'apk_sha256' => env(
            'TABANGNOW_APK_SHA256',
            'F58685F2345659AE26ED810AACF24D9FD055A81400D46FB975BD26FEDBF5CFD2'
        ),

        'apk_size_bytes' => (int) env(
            'TABANGNOW_APK_SIZE_BYTES',
            60086529
        ),
    ],
];

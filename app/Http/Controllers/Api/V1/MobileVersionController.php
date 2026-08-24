<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MobileVersionController extends Controller
{
    public function show(): JsonResponse
    {
        return response()
            ->json([
                'data' => [
                    'latest_version' => (string) config(
                        'tabangnow.mobile.version',
                        '1.0.0'
                    ),
                    'latest_build_number' => max(
                        1,
                        (int) config(
                            'tabangnow.mobile.build_number',
                            1
                        )
                    ),
                    'minimum_supported_build_number' => max(
                        1,
                        (int) config(
                            'tabangnow.mobile.minimum_supported_build_number',
                            1
                        )
                    ),
                    'message' => (string) config(
                        'tabangnow.mobile.update_message',
                        'A newer version of TabangNow is available.'
                    ),
                    'download_url' => route('download'),
                ],
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
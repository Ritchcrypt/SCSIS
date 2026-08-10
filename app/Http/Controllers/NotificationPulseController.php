<?php

namespace App\Http\Controllers;

use App\Services\NotificationBellService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPulseController extends Controller
{
    public function __invoke(
        Request $request,
        NotificationBellService $notificationBell
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user,
            401,
            'Authentication is required.'
        );

        $payload = $notificationBell->pulseForUser(
            $user
        );

        return response()
            ->json([
                'version' => 1,

                'data' => $payload,

                'server_time' => now()
                    ->toIso8601String(),
            ])
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }
}
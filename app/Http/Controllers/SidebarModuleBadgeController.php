<?php

namespace App\Http\Controllers;

use App\Services\SidebarModuleBadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SidebarModuleBadgeController extends Controller
{
    public function __invoke(Request $request, SidebarModuleBadgeService $badges): JsonResponse
    {
        // Only the authenticated user's role menu, determined server-side.
        // This GET request never marks a module as visited.
        $user = $request->user();
        $items = $badges->menuRoutesForRole($user);

        return response()->json([
            'counts' => $badges->counts($user, $items),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }
}

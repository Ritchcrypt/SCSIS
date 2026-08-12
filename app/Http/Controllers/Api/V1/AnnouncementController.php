<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Announcement::class);

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $canManageAnnouncements = Gate::allows(
            'create',
            Announcement::class
        );

        $query = Announcement::query()
            ->with([
                'poster:id,name,role',
            ]);

        if (! $canManageAnnouncements) {
            if (
                Schema::hasColumn(
                    'announcements',
                    'is_active'
                )
            ) {
                $query->where(
                    'is_active',
                    true
                );
            }

            if (
                Schema::hasColumn(
                    'announcements',
                    'audience'
                )
            ) {
                $query->whereIn(
                    'audience',
                    $this->allowedAudiencesForUser($user)
                );
            }
        }

        $announcements = $query
            ->latest('published_at')
            ->latest()
            ->paginate(10);

        $items = collect($announcements->items())
            ->map(
                fn (Announcement $announcement): array => [
                    'id' => (int) $announcement->id,

                    'title' => (string) $announcement->title,

                    'content' => (string) $announcement->content,

                    'category' => (string) $announcement->category,

                    'category_label' =>
                        $announcement->display_category,

                    'priority' => (string) $announcement->priority,

                    'priority_label' =>
                        $announcement->display_priority,

                    'audience' => (string) $announcement->audience,

                    'audience_label' =>
                        $announcement->display_audience,

                    'is_active' =>
                        (bool) $announcement->is_active,

                    'calamity_mode' =>
                        (bool) $announcement
                            ->activate_calamity_mode,

                    'published_at' =>
                        $announcement
                            ->published_at
                            ?->toIso8601String(),

                    'poster' => $announcement->poster
                        ? [
                            'id' =>
                                (int) $announcement->poster->id,

                            'name' =>
                                (string) $announcement->poster->name,

                            'role' =>
                                (string) $announcement->poster->role,
                        ]
                        : null,
                ]
            )
            ->values();

        return response()->json([
            'data' => $items,

            'pagination' => [
                'current_page' =>
                    $announcements->currentPage(),

                'last_page' =>
                    $announcements->lastPage(),

                'per_page' =>
                    $announcements->perPage(),

                'total' =>
                    $announcements->total(),

                'has_more' =>
                    $announcements->hasMorePages(),
            ],
        ]);
    }

    private function allowedAudiencesForUser(User $user): array
    {
        return match (true) {
            $user->isOfficial() => [
                'everyone',
                'public',
                'all',
                'official',
                'officials',
                'dao',
            ],

            $user->isTanod() => [
                'everyone',
                'public',
                'all',
                'tanod',
            ],

            $user->isResident() => [
                'everyone',
                'public',
                'all',
                'residents',
                'resident',
            ],

            default => [
                'everyone',
                'public',
                'all',
            ],
        };
    }
}
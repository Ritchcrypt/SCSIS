<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    use RecordsOperationalActivity;

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Announcement::class);

        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $canManage = Gate::allows('create', Announcement::class);

        $query = Announcement::query()->with('poster');

        if (! $canManage) {
            if (Schema::hasColumn('announcements', 'is_active')) {
                $query->where('is_active', true);
            }

            if (Schema::hasColumn('announcements', 'audience')) {
                $query->whereIn(
                    'audience',
                    $this->allowedAudiencesForUser($user)
                );
            }
        }

        $announcements = $query
            ->latest('published_at')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return response()
            ->json([
                'data' => $announcements->getCollection()
                    ->map(fn (Announcement $announcement): array =>
                        $this->serializeAnnouncement($announcement))
                    ->values()
                    ->all(),
                'options' => [
                    'categories' => $this->optionList($this->categories()),
                    'priorities' => $this->optionList($this->priorities()),
                    'audiences' => $this->optionList($this->audiences()),
                ],
                'permissions' => [
                    'can_create' => $canManage,
                    'can_toggle' => $canManage,
                    'can_delete' => $canManage,
                ],
                'pagination' => [
                    'current_page' => $announcements->currentPage(),
                    'last_page' => $announcements->lastPage(),
                    'per_page' => $announcements->perPage(),
                    'total' => $announcements->total(),
                    'from' => $announcements->firstItem(),
                    'to' => $announcements->lastItem(),
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Announcement::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'category' => [
                'required',
                Rule::in(array_keys($this->categories())),
            ],
            'priority' => [
                'required',
                Rule::in(array_keys($this->priorities())),
            ],
            'audience' => [
                'required',
                Rule::in($this->allowedAudienceValues()),
            ],
            'activate_calamity_mode' => ['nullable', 'boolean'],
            'show_in_weather_feed' => ['nullable', 'boolean'],
        ]);

        $audience = $this->normalizeAudience($validated['audience']);
        $calamityMode = $request->boolean('activate_calamity_mode');
        $showInWeatherFeed = $request->boolean('show_in_weather_feed');

        if ($calamityMode) {
            $validated['category'] = 'calamity';
            $validated['priority'] = 'emergency';
            $audience = 'everyone';
            $showInWeatherFeed = true;
        }

        $data = [
            'title' => trim($validated['title']),
            'content' => trim($validated['content']),
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'audience' => $audience,
            'is_active' => true,
            'activate_calamity_mode' => $calamityMode,
            'posted_by' => $request->user()->id,
            'published_at' => now(),
        ];

        if (Schema::hasColumn('announcements', 'show_in_weather_feed')) {
            $data['show_in_weather_feed'] = $showInWeatherFeed;
        }

        $announcement = Announcement::create($data);

        $this->notifyTargetUsers($announcement);

        $this->recordOperationalActivity(
            event: 'announcement.created',
            category: 'announcement',
            description: 'An announcement was created.',
            metadata: [
                'announcement_id' => (int) $announcement->id,
                'category' => $announcement->category,
                'priority' => $announcement->priority,
                'audience' => $announcement->audience,
                'is_active' => (bool) $announcement->is_active,
                'calamity_mode' => (bool) $announcement->activate_calamity_mode,
            ],
            request: $request,
        );

        $announcement->load('poster');

        return response()
            ->json([
                'message' =>
                    'Announcement posted successfully and users were notified.',
                'data' => $this->serializeAnnouncement($announcement),
            ], 201)
            ->header('Cache-Control', 'no-store, private');
    }

    public function toggle(
        Request $request,
        Announcement $announcement
    ): JsonResponse {
        Gate::authorize('update', $announcement);

        $previous = (bool) $announcement->is_active;

        DB::transaction(function () use ($announcement): void {
            $locked = Announcement::query()
                ->lockForUpdate()
                ->findOrFail($announcement->getKey());

            $locked->update([
                'is_active' => ! (bool) $locked->is_active,
            ]);
        });

        $announcement->refresh();
        $announcement->load('poster');

        $this->recordOperationalActivity(
            event: 'announcement.toggled',
            category: 'announcement',
            description: 'An announcement active state was changed.',
            metadata: [
                'announcement_id' => (int) $announcement->id,
                'previous_active' => $previous,
                'new_active' => (bool) $announcement->is_active,
                'category' => $announcement->category,
                'priority' => $announcement->priority,
            ],
            request: $request,
        );

        return response()
            ->json([
                'message' => $announcement->is_active
                    ? 'Announcement activated successfully.'
                    : 'Announcement deactivated successfully.',
                'data' => $this->serializeAnnouncement($announcement),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function destroy(
        Request $request,
        Announcement $announcement
    ): JsonResponse {
        Gate::authorize('delete', $announcement);

        $metadata = [
            'announcement_id' => (int) $announcement->id,
            'category' => $announcement->category,
            'priority' => $announcement->priority,
            'audience' => $announcement->audience,
            'was_active' => (bool) $announcement->is_active,
        ];

        DB::transaction(function () use ($announcement): void {
            $locked = Announcement::query()
                ->lockForUpdate()
                ->findOrFail($announcement->getKey());

            $this->deleteAnnouncementNotifications($locked);
            $locked->delete();
        });

        $this->recordOperationalActivity(
            event: 'announcement.deleted',
            category: 'announcement',
            description: 'An announcement was deleted.',
            metadata: $metadata,
            request: $request,
        );

        return response()
            ->json([
                'message' => 'Announcement deleted successfully.',
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    private function serializeAnnouncement(Announcement $announcement): array
    {
        return [
            'id' => (int) $announcement->id,
            'title' => (string) $announcement->title,
            'content' => (string) $announcement->content,
            'category' => (string) $announcement->category,
            'category_label' => $announcement->display_category,
            'priority' => (string) $announcement->priority,
            'priority_label' => $announcement->display_priority,
            'audience' => (string) $announcement->audience,
            'audience_label' => $announcement->display_audience,
            'is_active' => (bool) $announcement->is_active,
            'calamity_mode' =>
                (bool) $announcement->activate_calamity_mode,
            'show_in_weather_feed' =>
                Schema::hasColumn('announcements', 'show_in_weather_feed')
                    ? (bool) $announcement->show_in_weather_feed
                    : false,
            'published_at' =>
                $announcement->published_at?->toIso8601String(),
            'created_at' =>
                $announcement->created_at?->toIso8601String(),
            'poster' => $announcement->poster
                ? [
                    'id' => (int) $announcement->poster->id,
                    'name' => (string) $announcement->poster->name,
                    'role' => (string) $announcement->poster->role,
                ]
                : null,
        ];
    }

    private function notifyTargetUsers(Announcement $announcement): void
    {
        try {
            if (! Schema::hasTable('notifications')) {
                return;
            }

            $audience = strtolower(trim((string) $announcement->audience));

            $usersQuery = User::query()->select(['id', 'role']);

            if (Schema::hasColumn('users', 'is_active')) {
                $usersQuery->where('is_active', true);
            }

            match ($audience) {
                'tanod' => $usersQuery->where('role', 'tanod'),
                'residents', 'resident' =>
                    $usersQuery->where('role', 'resident'),
                'official', 'officials', 'dao' =>
                    $usersQuery->whereIn('role', ['official', 'dao']),
                'admin' => $usersQuery->where('role', 'admin'),
                'everyone', 'public', 'all' =>
                    $usersQuery->whereIn('role', [
                        'admin',
                        'official',
                        'dao',
                        'tanod',
                        'resident',
                    ]),
                default => null,
            };

            if (! in_array(
                $audience,
                $this->allowedAudienceValues(),
                true
            )) {
                return;
            }

            $type = (
                $announcement->category === 'calamity'
                || (bool) $announcement->activate_calamity_mode
            ) ? 'calamity' : 'announcement';

            $usersQuery->chunkById(
                100,
                function ($users) use ($announcement, $type): void {
                    foreach ($users as $user) {
                        $notificationData = [
                            'user_id' => $user->id,
                            'type' => $type,
                            'source_id' => $announcement->id,
                            'title' => mb_substr(
                                (string) $announcement->title,
                                0,
                                150
                            ),
                            'message' => (string) $announcement->content,
                            'is_read' => false,
                            'read_at' => null,
                        ];

                        if (Schema::hasColumn(
                            'notifications',
                            'acknowledged_by'
                        )) {
                            $notificationData['acknowledged_by'] = null;
                        }

                        if (Schema::hasColumn(
                            'notifications',
                            'acknowledged_at'
                        )) {
                            $notificationData['acknowledged_at'] = null;
                        }

                        UserNotification::updateOrCreate(
                            [
                                'user_id' => $user->id,
                                'type' => $type,
                                'source_id' => $announcement->id,
                            ],
                            $notificationData
                        );
                    }
                }
            );
        } catch (\Throwable $exception) {
            Log::warning(
                'Announcement notification creation failed.',
                [
                    'announcement_id' => $announcement->id,
                    'error' => $exception->getMessage(),
                ]
            );
        }
    }

    private function deleteAnnouncementNotifications(
        Announcement $announcement
    ): void {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        UserNotification::query()
            ->where('source_id', $announcement->id)
            ->whereIn('type', ['announcement', 'calamity'])
            ->delete();
    }

    private function allowedAudiencesForUser(User $user): array
    {
        return match (true) {
            $user->isOfficial() => [
                'everyone', 'public', 'all',
                'official', 'officials', 'dao',
            ],
            $user->isTanod() => [
                'everyone', 'public', 'all', 'tanod',
            ],
            $user->isResident() => [
                'everyone', 'public', 'all',
                'residents', 'resident',
            ],
            default => ['everyone', 'public', 'all'],
        };
    }

    private function normalizeAudience(string $audience): string
    {
        return match (strtolower($audience)) {
            'public', 'all' => 'everyone',
            'resident' => 'residents',
            'officials', 'dao' => 'official',
            default => strtolower($audience),
        };
    }

    private function optionList(array $options): array
    {
        return collect($options)
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    private function categories(): array
    {
        return [
            'advisory' => 'Advisory',
            'emergency' => 'Emergency',
            'calamity' => 'Calamity',
            'community' => 'Community',
            'health' => 'Health',
            'general' => 'General',
        ];
    }

    private function priorities(): array
    {
        return [
            'normal' => 'Normal',
            'important' => 'Important',
            'urgent' => 'Urgent',
            'emergency' => 'Emergency',
        ];
    }

    private function audiences(): array
    {
        return [
            'everyone' => 'Public / Everyone',
            'tanod' => 'Tanod Only',
            'residents' => 'Residents Only',
            'official' => 'Officials Only',
            'admin' => 'Admin Only',
        ];
    }

    private function allowedAudienceValues(): array
    {
        return [
            'everyone', 'public', 'all',
            'tanod',
            'residents', 'resident',
            'official', 'officials', 'dao',
            'admin',
        ];
    }
}

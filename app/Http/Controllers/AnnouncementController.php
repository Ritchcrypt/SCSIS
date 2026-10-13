<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Models\Announcement;
use App\Models\Barangay;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\DaoBarangays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    use RecordsOperationalActivity;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Announcement::class);

        $user = $request->user();

        $canManageAnnouncements = Gate::allows(
            'create',
            Announcement::class
        );

        $barangayTargetingReady = Schema::hasTable(
            'announcement_barangay'
        )
            && Schema::hasTable('barangays')
            && Schema::hasColumn('users', 'barangay_id');

        $query = Announcement::query()
            ->with('poster');

        if ($barangayTargetingReady) {
            $query->with([
                'targetBarangays:id,barangay_name',
            ]);
        }

        $query->when(
            ! $canManageAnnouncements,
            function ($query) use (
                $user,
                $barangayTargetingReady
            ): void {
                $allowedAudiences =
                    $this->allowedAudiencesForUser($user);

                if (
                    Schema::hasColumn(
                        'announcements',
                        'is_active'
                    )
                ) {
                    $query->where('is_active', true);
                }

                if (
                    Schema::hasColumn(
                        'announcements',
                        'audience'
                    )
                ) {
                    $query->whereIn(
                        'audience',
                        $allowedAudiences
                    );
                }

                if (! $barangayTargetingReady) {
                    return;
                }

                if (empty($user->barangay_id)) {
                    $query->whereDoesntHave(
                        'targetBarangays'
                    );

                    return;
                }

                $query->where(
                    function ($visibilityQuery) use ($user): void {
                        $visibilityQuery
                            ->whereDoesntHave(
                                'targetBarangays'
                            )
                            ->orWhereHas(
                                'targetBarangays',
                                function ($barangayQuery) use ($user): void {
                                    $barangayQuery->where(
                                        'barangays.id',
                                        (int) $user->barangay_id
                                    );
                                }
                            );
                    }
                );
            }
        );

        $announcements = $query
            ->latest('published_at')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('announcements.index', [
            'announcements' => $announcements,
            'categories' => $this->categories(),
            'priorities' => $this->priorities(),
            'audiences' => $this->audiences(),
            'barangays' => $this->officialBarangays(),
            'barangayTargetingReady' =>
                $barangayTargetingReady,
            'canManageAnnouncements' =>
                $canManageAnnouncements,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Announcement::class);

        if (
            ! Schema::hasTable('announcement_barangay')
            || ! Schema::hasColumn('users', 'barangay_id')
        ) {
            throw ValidationException::withMessages([
                'barangay_ids' =>
                    'Barangay targeting is not ready. Run the announcement barangay-targeting migration first.',
            ]);
        }

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
            'barangay_ids' => [
                'required',
                'array',
                'min:1',
                'max:' . count(DaoBarangays::records()),
            ],
            'barangay_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('barangays', 'id'),
            ],
            'activate_calamity_mode' => [
                'nullable',
                'boolean',
            ],
            'show_in_weather_feed' => [
                'nullable',
                'boolean',
            ],
        ], [
            'barangay_ids.required' =>
                'Select at least one Dao barangay.',
            'barangay_ids.min' =>
                'Select at least one Dao barangay.',
        ]);

        $targetBarangayIds =
            $this->validatedTargetBarangayIds(
                $validated['barangay_ids']
            );

        $audience = $this->normalizeAudience(
            $validated['audience']
        );

        $calamityMode = $request->boolean(
            'activate_calamity_mode'
        );

        $showInWeatherFeed = $request->boolean(
            'show_in_weather_feed'
        );

        if ($calamityMode) {
            $validated['category'] = 'calamity';
            $validated['priority'] = 'emergency';
            $audience = 'everyone';
            $showInWeatherFeed = true;
        }

        $announcementData = [
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

        if (
            Schema::hasColumn(
                'announcements',
                'show_in_weather_feed'
            )
        ) {
            $announcementData['show_in_weather_feed'] =
                $showInWeatherFeed;
        }

        $announcement = DB::transaction(
            function () use (
                $announcementData,
                $targetBarangayIds
            ): Announcement {
                $announcement = Announcement::create(
                    $announcementData
                );

                $announcement
                    ->targetBarangays()
                    ->sync($targetBarangayIds);

                return $announcement;
            }
        );

        $announcement->load(
            'targetBarangays:id,barangay_name'
        );

        $this->notifyTargetUsers($announcement);

        $this->recordOperationalActivity(
            event: 'announcement.created',
            category: 'announcement',
            description:
                'A barangay-targeted announcement was created.',
            metadata: [
                'announcement_id' =>
                    (int) $announcement->id,
                'category' => $announcement->category,
                'priority' => $announcement->priority,
                'audience' => $announcement->audience,
                'target_barangay_ids' =>
                    $targetBarangayIds,
                'target_barangays' =>
                    $announcement
                        ->targetBarangays
                        ->pluck('barangay_name')
                        ->values()
                        ->all(),
                'is_active' =>
                    (bool) $announcement->is_active,
                'calamity_mode' =>
                    (bool) $announcement
                        ->activate_calamity_mode,
            ],
            request: $request,
        );

        return redirect()
            ->to(
                $this->announcementIndexUrl(
                    $request->user()
                )
            )
            ->with(
                'success',
                'Announcement posted to the selected barangays and matching users were notified.'
            );
    }

    public function toggle(
        Request $request,
        Announcement $announcement
    ): RedirectResponse {
        Gate::authorize('update', $announcement);

        $previousActiveState = (bool) $announcement->is_active;

        DB::transaction(function () use ($announcement): void {
            $lockedAnnouncement = Announcement::query()
                ->lockForUpdate()
                ->findOrFail($announcement->getKey());

            $lockedAnnouncement->update([
                'is_active' => ! (bool) $lockedAnnouncement->is_active,
            ]);
        });

        $announcement->refresh();

        $this->recordOperationalActivity(
            event: 'announcement.toggled',
            category: 'announcement',
            description: 'An announcement active state was changed.',
            metadata: [
                'announcement_id' => (int) $announcement->id,
                'previous_active' => $previousActiveState,
                'new_active' => (bool) $announcement->is_active,
                'category' => $announcement->category,
                'priority' => $announcement->priority,
            ],
            request: $request,
        );

        return redirect()
            ->to($this->announcementIndexUrl($request->user()))
            ->with(
                'success',
                $announcement->is_active
                    ? 'Announcement activated successfully.'
                    : 'Announcement deactivated successfully.'
            );
    }

    public function destroy(
        Request $request,
        Announcement $announcement
    ): RedirectResponse {
        Gate::authorize('delete', $announcement);

        $auditMetadata = [
            'announcement_id' => (int) $announcement->id,
            'category' => $announcement->category,
            'priority' => $announcement->priority,
            'audience' => $announcement->audience,
            'was_active' => (bool) $announcement->is_active,
        ];

        DB::transaction(function () use ($announcement): void {
            $lockedAnnouncement = Announcement::query()
                ->lockForUpdate()
                ->findOrFail($announcement->getKey());

            $this->deleteAnnouncementNotifications(
                $lockedAnnouncement
            );

            $lockedAnnouncement->delete();
        });

        $this->recordOperationalActivity(
            event: 'announcement.deleted',
            category: 'announcement',
            description: 'An announcement was deleted.',
            metadata: $auditMetadata,
            request: $request,
        );

        return redirect()
            ->to($this->announcementIndexUrl($request->user()))
            ->with(
                'success',
                'Announcement deleted successfully.'
            );
    }

    private function notifyTargetUsers(
        Announcement $announcement
    ): void {
        try {
            if (
                ! Schema::hasTable('notifications')
                || ! Schema::hasTable('announcement_barangay')
                || ! Schema::hasColumn('users', 'barangay_id')
            ) {
                return;
            }

            $targetBarangayIds = $announcement
                ->targetBarangays()
                ->pluck('barangays.id')
                ->map(
                    fn ($id): int => (int) $id
                )
                ->unique()
                ->values()
                ->all();

            if ($targetBarangayIds === []) {
                return;
            }

            $audience = strtolower(
                trim((string) $announcement->audience)
            );

            if (
                ! in_array(
                    $audience,
                    $this->allowedAudienceValues(),
                    true
                )
            ) {
                return;
            }

            $usersQuery = User::query()
                ->select([
                    'id',
                    'role',
                    'barangay_id',
                ])
                ->whereIn(
                    'barangay_id',
                    $targetBarangayIds
                );

            if (
                Schema::hasColumn(
                    'users',
                    'is_active'
                )
            ) {
                $usersQuery->where('is_active', true);
            }

            match ($audience) {
                'tanod' =>
                    $usersQuery->where(
                        'role',
                        'tanod'
                    ),

                'residents',
                'resident' =>
                    $usersQuery->where(
                        'role',
                        'resident'
                    ),

                'official',
                'officials',
                'dao' =>
                    $usersQuery->whereIn(
                        'role',
                        ['official', 'dao']
                    ),

                'admin' =>
                    $usersQuery->where(
                        'role',
                        'admin'
                    ),

                'everyone',
                'public',
                'all' =>
                    $usersQuery->whereIn(
                        'role',
                        [
                            'admin',
                            'official',
                            'dao',
                            'tanod',
                            'resident',
                        ]
                    ),

                default => null,
            };

            $notificationType = (
                $announcement->category === 'calamity'
                || (bool) $announcement
                    ->activate_calamity_mode
            )
                ? 'calamity'
                : 'announcement';

            $usersQuery->chunkById(
                100,
                function ($users) use (
                    $announcement,
                    $notificationType
                ): void {
                    foreach ($users as $user) {
                        $notificationData = [
                            'user_id' => $user->id,
                            'type' => $notificationType,
                            'source_id' =>
                                $announcement->id,
                            'title' => mb_substr(
                                (string) $announcement->title,
                                0,
                                150
                            ),
                            'message' =>
                                (string) $announcement->content,
                            'is_read' => false,
                            'read_at' => null,
                        ];

                        if (
                            Schema::hasColumn(
                                'notifications',
                                'acknowledged_by'
                            )
                        ) {
                            $notificationData[
                                'acknowledged_by'
                            ] = null;
                        }

                        if (
                            Schema::hasColumn(
                                'notifications',
                                'acknowledged_at'
                            )
                        ) {
                            $notificationData[
                                'acknowledged_at'
                            ] = null;
                        }

                        UserNotification::updateOrCreate(
                            [
                                'user_id' => $user->id,
                                'type' => $notificationType,
                                'source_id' =>
                                    $announcement->id,
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
                    'announcement_id' =>
                        $announcement->id,
                    'error' =>
                        $exception->getMessage(),
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
            ->whereIn('type', [
                'announcement',
                'calamity',
            ])
            ->delete();
    }

    private function officialBarangays()
    {
        if (! Schema::hasTable('barangays')) {
            return collect();
        }

        return Barangay::query()
            ->whereIn(
                'barangay_name',
                DaoBarangays::names()
            )
            ->orderBy('barangay_name')
            ->get([
                'id',
                'barangay_name',
            ]);
    }

    private function validatedTargetBarangayIds(
        array $barangayIds
    ): array {
        $requestedIds = collect($barangayIds)
            ->map(
                fn ($id): int => (int) $id
            )
            ->filter(
                fn (int $id): bool => $id > 0
            )
            ->unique()
            ->values();

        $validIds = Barangay::query()
            ->whereIn(
                'barangay_name',
                DaoBarangays::names()
            )
            ->whereIn(
                'id',
                $requestedIds->all()
            )
            ->pluck('id')
            ->map(
                fn ($id): int => (int) $id
            )
            ->unique()
            ->values();

        if (
            $requestedIds->isEmpty()
            || $validIds->count()
                !== $requestedIds->count()
        ) {
            throw ValidationException::withMessages([
                'barangay_ids' =>
                    'One or more selected barangays are not part of the official Dao, Capiz barangay list.',
            ]);
        }

        return $validIds->all();
    }

    private function allowedAudiencesForUser(User $user): array    {
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

    private function announcementIndexUrl(?User $user): string
    {
        $routeName = match (strtolower((string) $user?->role)) {
            'admin' => 'admin.announcements.index',
            'official',
            'dao' => 'official.announcements.index',
            'tanod' => 'tanod.announcements.index',
            'resident' => 'resident.announcements.index',
            default => null,
        };

        return $routeName && Route::has($routeName)
            ? route($routeName)
            : route('dashboard');
    }

    private function normalizeAudience(string $audience): string
    {
        return match (strtolower($audience)) {
            'public',
            'all' => 'everyone',
            'resident' => 'residents',
            'officials',
            'dao' => 'official',
            default => strtolower($audience),
        };
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
            'everyone',
            'public',
            'all',
            'tanod',
            'residents',
            'resident',
            'official',
            'officials',
            'dao',
            'admin',
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $announcements = Announcement::query()
            ->with('poster')
            ->when(! $this->canManageAnnouncements($user), function ($query) use ($user) {
                $role = strtolower((string) ($user?->role ?? ''));

                $allowedAudiences = match ($role) {
                    'tanod' => ['everyone', 'public', 'all', 'tanod'],
                    'resident' => ['everyone', 'public', 'all', 'residents', 'resident'],
                    default => ['everyone', 'public', 'all'],
                };

                if (Schema::hasColumn('announcements', 'is_active')) {
                    $query->where('is_active', true);
                }

                if (Schema::hasColumn('announcements', 'audience')) {
                    $query->whereIn('audience', $allowedAudiences);
                }
            })
            ->latest('published_at')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('announcements.index', [
            'announcements' => $announcements,
            'categories' => $this->categories(),
            'priorities' => $this->priorities(),
            'audiences' => $this->audiences(),
            'canManageAnnouncements' => $this->canManageAnnouncements($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->canManageAnnouncements($user)) {
            abort(403, 'Only admin or official can post announcements.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::in(array_keys($this->categories()))],
            'priority' => ['required', Rule::in(array_keys($this->priorities()))],
            'audience' => ['required', Rule::in($this->allowedAudienceValues())],
            'activate_calamity_mode' => ['nullable', 'boolean'],
            'show_in_weather_feed' => ['nullable', 'boolean'],
        ]);

        $validated['audience'] = $this->normalizeAudience($validated['audience']);

        $calamityMode = (bool) ($validated['activate_calamity_mode'] ?? false);
        $showInWeatherFeed = $request->boolean('show_in_weather_feed');

        if ($calamityMode) {
            $validated['category'] = 'calamity';
            $validated['priority'] = 'emergency';
            $validated['audience'] = 'everyone';

            $showInWeatherFeed = true;
        }

        $announcementData = [
            'title' => $validated['title'],
            'content' => $validated['content'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'audience' => $validated['audience'],
            'is_active' => true,
            'activate_calamity_mode' => $calamityMode,
            'posted_by' => Auth::id(),
            'published_at' => now(),
        ];

        if (Schema::hasColumn('announcements', 'show_in_weather_feed')) {
            $announcementData['show_in_weather_feed'] = $showInWeatherFeed;
        }

        $announcement = Announcement::create($announcementData);

        $this->notifyTargetUsers($announcement);

        return redirect()
            ->to($this->announcementIndexUrl($user))
            ->with('success', 'Announcement posted successfully and users were notified.');
    }

    public function toggle(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();

        if (! $this->canManageAnnouncements($user)) {
            abort(403, 'Only admin or official can update announcements.');
        }

        $announcement->update([
            'is_active' => ! $announcement->is_active,
        ]);

        return redirect()
            ->to($this->announcementIndexUrl($user))
            ->with('success', $announcement->is_active
                ? 'Announcement activated successfully.'
                : 'Announcement deactivated successfully.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();

        if (! $this->canManageAnnouncements($user)) {
            abort(403, 'Only admin or official can delete announcements.');
        }

        $this->deleteAnnouncementNotifications($announcement);

        $announcement->delete();

        return redirect()
            ->to($this->announcementIndexUrl($user))
            ->with('success', 'Announcement deleted successfully.');
    }

    private function notifyTargetUsers(Announcement $announcement): void
    {
        try {
            if (! Schema::hasTable('notifications')) {
                return;
            }

            $audience = strtolower(trim((string) $announcement->audience));

            $usersQuery = User::query()
                ->select(['id', 'role']);

            if (Schema::hasColumn('users', 'is_active')) {
                $usersQuery->where('is_active', true);
            }

            switch ($audience) {
                case 'tanod':
                    $usersQuery->where('role', 'tanod');
                    break;

                case 'residents':
                case 'resident':
                    $usersQuery->where('role', 'resident');
                    break;

                case 'official':
                case 'officials':
                case 'dao':
                    $usersQuery->whereIn('role', ['official', 'dao']);
                    break;

                case 'admin':
                    $usersQuery->where('role', 'admin');
                    break;

                case 'everyone':
                case 'public':
                case 'all':
                    $usersQuery->whereIn('role', [
                        'admin',
                        'official',
                        'dao',
                        'tanod',
                        'resident',
                    ]);
                    break;

                default:
                    return;
            }

            $usersQuery->chunkById(100, function ($users) use ($announcement): void {
                foreach ($users as $user) {
                    $notificationData = [
                        'user_id' => $user->id,
                        'type' => 'announcement',
                        'source_id' => $announcement->id,
                        'title' => mb_substr((string) $announcement->title, 0, 150),
                        'message' => (string) $announcement->content,
                        'is_read' => false,
                        'read_at' => null,
                    ];

                    if (Schema::hasColumn('notifications', 'acknowledged_by')) {
                        $notificationData['acknowledged_by'] = null;
                    }

                    if (Schema::hasColumn('notifications', 'acknowledged_at')) {
                        $notificationData['acknowledged_at'] = null;
                    }

                    UserNotification::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'type' => 'announcement',
                            'source_id' => $announcement->id,
                        ],
                        $notificationData
                    );
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Announcement notification creation failed.', [
                'announcement_id' => $announcement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function deleteAnnouncementNotifications(Announcement $announcement): void
    {
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

    private function canManageAnnouncements(?User $user): bool
    {
        return $user && in_array(strtolower((string) $user->role), [
            'admin',
            'official',
            'dao',
        ], true);
    }

    private function announcementIndexUrl(?User $user): string
    {
        $role = strtolower((string) ($user?->role ?? ''));

        $routeName = match ($role) {
            'admin' => Route::has('admin.announcements.index') ? 'admin.announcements.index' : null,
            'official', 'dao' => Route::has('official.announcements.index') ? 'official.announcements.index' : null,
            'tanod' => Route::has('tanod.announcements.index') ? 'tanod.announcements.index' : null,
            'resident' => Route::has('resident.announcements.index') ? 'resident.announcements.index' : null,
            default => null,
        };

        return $routeName ? route($routeName) : route('dashboard');
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
<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $announcements = Announcement::query()
            ->with('poster')
            ->latest('published_at')
            ->latest()
            ->paginate(10);

        return view('announcements.index', [
            'announcements' => $announcements,
            'categories' => $this->categories(),
            'priorities' => $this->priorities(),
            'audiences' => $this->audiences(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::in(array_keys($this->categories()))],
            'priority' => ['required', Rule::in(array_keys($this->priorities()))],
            'audience' => ['required', Rule::in(array_keys($this->audiences()))],
            'activate_calamity_mode' => ['nullable', 'boolean'],
        ]);

        $calamityMode = (bool) ($validated['activate_calamity_mode'] ?? false);

        if ($calamityMode) {
            $validated['category'] = 'calamity';
            $validated['priority'] = 'emergency';
            $validated['audience'] = 'everyone';
        }

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'audience' => $validated['audience'],
            'is_active' => true,
            'activate_calamity_mode' => $calamityMode,
            'posted_by' => Auth::id(),
            'published_at' => now(),
        ]);

        $this->notifyTargetUsers($announcement);

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement posted successfully.');
    }

    public function toggle(Announcement $announcement): RedirectResponse
    {
        $announcement->update([
            'is_active' => ! $announcement->is_active,
        ]);

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', $announcement->is_active
                ? 'Announcement activated successfully.'
                : 'Announcement deactivated successfully.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement deleted successfully.');
    }

    private function notifyTargetUsers(Announcement $announcement): void
    {
        try {
            $notificationType = $this->notificationType($announcement);

            $usersQuery = User::query()
                ->select('id', 'role');

            match ($announcement->audience) {
                'tanod' => $usersQuery->where('role', 'tanod'),
                'residents' => $usersQuery->where('role', 'resident'),
                'admin' => $usersQuery->where('role', 'admin'),
                default => null,
            };

            $usersQuery->chunkById(100, function ($users) use ($announcement, $notificationType) {
                foreach ($users as $user) {
                    UserNotification::create([
                        'user_id' => $user->id,
                        'type' => $notificationType,
                        'source_id' => $announcement->id,
                        'title' => $announcement->title,
                        'message' => $announcement->content,
                        'is_read' => false,
                        'read_at' => null,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Announcement notification creation failed.', [
                'announcement_id' => $announcement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notificationType(Announcement $announcement): string
    {
        if ($announcement->activate_calamity_mode) {
            return 'calamity';
        }

        if ($announcement->category === 'calamity') {
            return 'calamity';
        }

        if ($announcement->category === 'emergency' || $announcement->priority === 'emergency') {
            return 'emergency';
        }

        return 'announcement';
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
            'everyone' => 'Everyone',
            'tanod' => 'Tanod Only',
            'residents' => 'Residents Only',
            'admin' => 'Admin Only',
        ];
    }
}
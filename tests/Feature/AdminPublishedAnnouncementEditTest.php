<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPublishedAnnouncementEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_published_announcement_without_new_delivery_or_resetting_read_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $resident = User::factory()->create(['role' => 'resident']);
        $announcement = $this->publishedAnnouncement($admin);
        $target = Barangay::query()->where('barangay_name', 'Agtambi')->firstOrFail();
        $announcement->targetBarangays()->sync([$target->id]);
        $publishedAt = $announcement->published_at?->toDateTimeString();
        $readAt = now()->subMinutes(3)->toDateTimeString();

        $noticeId = DB::table('notifications')->insertGetId([
            'user_id' => $resident->id,
            'type' => 'announcement',
            'source_id' => $announcement->id,
            'title' => 'Original Title',
            'message' => 'Original message.',
            'is_read' => true,
            'read_at' => $readAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.announcements.update', $announcement), [
                'title' => 'Corrected Public Title',
                'content' => 'Corrected announcement message.',
                'category' => 'advisory',
                'priority' => 'important',
                // Tampering must never change previously delivered recipients.
                'audience' => 'everyone',
                'barangay_ids' => [],
                'activate_calamity_mode' => 1,
                'published_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('success');

        $fresh = $announcement->fresh();
        $this->assertSame('Corrected Public Title', $fresh->title);
        $this->assertSame('Corrected announcement message.', $fresh->content);
        $this->assertSame('advisory', $fresh->category);
        $this->assertSame('important', $fresh->priority);
        $this->assertSame('residents', $fresh->audience);
        $this->assertFalse((bool) $fresh->activate_calamity_mode);
        $this->assertSame($publishedAt, $fresh->published_at?->toDateTimeString());
        $this->assertSame([$target->id], $fresh->targetBarangays()->pluck('barangays.id')->map(fn ($id) => (int) $id)->all());

        $notice = DB::table('notifications')->where('id', $noticeId)->first();
        $this->assertSame('Corrected Public Title', $notice->title);
        $this->assertSame('Corrected announcement message.', $notice->message);
        $this->assertSame(1, (int) $notice->is_read);
        $this->assertSame($readAt, $notice->read_at);
        $this->assertSame('announcement', $notice->type);
        $this->assertSame(1, DB::table('notifications')->where('source_id', $announcement->id)
            ->whereIn('type', ['announcement', 'calamity'])->count());

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'announcement.updated',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_unread_notice_stays_unread_after_admin_edits(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $resident = User::factory()->create(['role' => 'resident']);
        $announcement = $this->publishedAnnouncement($admin);

        $id = DB::table('notifications')->insertGetId([
            'user_id' => $resident->id,
            'type' => 'announcement',
            'source_id' => $announcement->id,
            'title' => $announcement->title,
            'message' => $announcement->content,
            'is_read' => false,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.announcements.update', $announcement), [
                'title' => 'Updated Headline',
                'content' => 'Updated text.',
                'category' => 'general',
                'priority' => 'normal',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'id' => $id,
            'title' => 'Updated Headline',
            'message' => 'Updated text.',
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    public function test_only_admin_can_open_and_submit_the_published_edit_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $announcement = $this->publishedAnnouncement($admin);

        $this->actingAs($admin)
            ->get(route('admin.announcements.edit', $announcement))
            ->assertOk()
            ->assertSee('Edit Published Announcement')
            ->assertSee('Save Changes');

        foreach (['official', 'dao', 'tanod', 'resident'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)
                ->get(route('admin.announcements.edit', $announcement))
                ->assertForbidden();

            $this->actingAs($user)
                ->patch(route('admin.announcements.update', $announcement), [
                    'title' => 'Unauthorized change',
                    'content' => 'No',
                    'category' => 'general',
                    'priority' => 'normal',
                ])
                ->assertForbidden();
        }

        $this->assertSame('Original Title', $announcement->fresh()->title);
    }

    public function test_admin_edit_button_is_visible_only_to_admin_in_announcement_listing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $announcement = $this->publishedAnnouncement($admin);

        $this->actingAs($admin)
            ->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertSee('aria-label="Edit published announcement"', false);

        $official = User::factory()->create(['role' => 'official']);
        $this->actingAs($official)
            ->get(route('official.announcements.index'))
            ->assertOk()
            ->assertDontSee('aria-label="Edit published announcement"', false);
    }

    public function test_edit_cannot_turn_an_ordinary_notice_into_calamity_mode(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $announcement = $this->publishedAnnouncement($admin);

        $this->actingAs($admin)
            ->from(route('admin.announcements.edit', $announcement))
            ->patch(route('admin.announcements.update', $announcement), [
                'title' => 'Changed',
                'content' => 'Changed body.',
                'category' => 'calamity',
                'priority' => 'emergency',
            ])
            ->assertRedirect(route('admin.announcements.edit', $announcement))
            ->assertSessionHasErrors('category');

        $this->assertSame('Original Title', $announcement->fresh()->title);
    }

    public function test_invalid_edit_does_not_change_published_announcement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $announcement = $this->publishedAnnouncement($admin);

        $this->actingAs($admin)
            ->from(route('admin.announcements.edit', $announcement))
            ->patch(route('admin.announcements.update', $announcement), [
                'title' => '',
                'content' => '',
                'category' => 'nonsense',
                'priority' => 'invalid',
            ])
            ->assertSessionHasErrors(['title', 'content', 'category', 'priority']);

        $this->assertSame('Original Title', $announcement->fresh()->title);
    }

    private function publishedAnnouncement(User $admin): Announcement
    {
        return Announcement::query()->create([
            'title' => 'Original Title',
            'content' => 'Original message.',
            'category' => 'general',
            'priority' => 'normal',
            'audience' => 'residents',
            'is_active' => true,
            'activate_calamity_mode' => false,
            'posted_by' => $admin->id,
            'published_at' => now()->subDay(),
        ]);
    }
}

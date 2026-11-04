<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementAdminRecipientDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_everyone_announcement_notifies_unassigned_admin_but_not_other_barangay_resident(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'barangay_id' => null]);
        $agtambi = Barangay::query()->where('barangay_name', 'Agtambi')->firstOrFail();
        $duyoc = Barangay::query()->where('barangay_name', 'Duyoc')->firstOrFail();
        $targetResident = User::factory()->create(['role' => 'resident', 'barangay_id' => $agtambi->id]);
        $otherResident = User::factory()->create(['role' => 'resident', 'barangay_id' => $duyoc->id]);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'barangay_id' => $duyoc->id]);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Selected Barangay Announcement',
            'content' => 'A notice for residents in the selected barangay.',
            'category' => 'general',
            'priority' => 'normal',
            'audience' => 'everyone',
            'barangay_ids' => [$agtambi->id],
        ])->assertRedirect();

        $announcement = Announcement::query()->where('title', 'Selected Barangay Announcement')->firstOrFail();

        foreach ([$admin, $otherAdmin, $targetResident] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $recipient->id,
                'type' => 'announcement',
                'source_id' => $announcement->id,
            ]);
        }
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $otherResident->id,
            'type' => 'announcement',
            'source_id' => $announcement->id,
        ]);
    }

    public function test_resident_only_announcement_does_not_notify_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'barangay_id' => null]);
        $agtambi = Barangay::query()->where('barangay_name', 'Agtambi')->firstOrFail();
        $resident = User::factory()->create(['role' => 'resident', 'barangay_id' => $agtambi->id]);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Residents Only Notice',
            'content' => 'This is restricted to the selected residents.',
            'category' => 'general',
            'priority' => 'normal',
            'audience' => 'residents',
            'barangay_ids' => [$agtambi->id],
        ])->assertRedirect();

        $announcement = Announcement::query()->where('title', 'Residents Only Notice')->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $resident->id,
            'type' => 'announcement',
            'source_id' => $announcement->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $admin->id,
            'type' => 'announcement',
            'source_id' => $announcement->id,
        ]);
    }

    public function test_admin_audience_notifies_unassigned_admin_without_notifying_residents(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'barangay_id' => null]);
        $agtambi = Barangay::query()->where('barangay_name', 'Agtambi')->firstOrFail();
        $resident = User::factory()->create(['role' => 'resident', 'barangay_id' => $agtambi->id]);

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Admin Only Notice',
            'content' => 'Administrative notice.',
            'category' => 'general',
            'priority' => 'normal',
            'audience' => 'admin',
            'barangay_ids' => [$agtambi->id],
        ])->assertRedirect();

        $announcement = Announcement::query()->where('title', 'Admin Only Notice')->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'announcement',
            'source_id' => $announcement->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $resident->id,
            'type' => 'announcement',
            'source_id' => $announcement->id,
        ]);
    }
}
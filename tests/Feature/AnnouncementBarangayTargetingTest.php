<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnnouncementBarangayTargetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dao_reference_contains_the_official_twenty_barangays(): void
    {
        $this->assertSame(
            20,
            Barangay::query()
                ->whereIn(
                    'barangay_name',
                    \App\Support\DaoBarangays::names()
                )
                ->count()
        );
    }

    public function test_admin_announcement_notifies_only_selected_barangay_residents(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $agtambi = Barangay::query()
            ->where('barangay_name', 'Agtambi')
            ->firstOrFail();

        $duyoc = Barangay::query()
            ->where('barangay_name', 'Duyoc')
            ->firstOrFail();

        $targetResident = User::factory()->create([
            'role' => 'resident',
            'barangay_id' => $agtambi->id,
        ]);

        $otherResident = User::factory()->create([
            'role' => 'resident',
            'barangay_id' => $duyoc->id,
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.announcements.store'),
            [
                'title' => 'Agtambi Water Interruption',
                'content' => 'Water service will be interrupted this afternoon.',
                'category' => 'advisory',
                'priority' => 'important',
                'audience' => 'residents',
                'barangay_ids' => [$agtambi->id],
            ]
        );

        $response->assertRedirect();

        $announcement = Announcement::query()
            ->where('title', 'Agtambi Water Interruption')
            ->firstOrFail();

        $this->assertDatabaseHas('announcement_barangay', [
            'announcement_id' => $announcement->id,
            'barangay_id' => $agtambi->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $targetResident->id,
            'type' => 'announcement',
            'source_id' => $announcement->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $otherResident->id,
            'type' => 'announcement',
            'source_id' => $announcement->id,
        ]);
    }

    public function test_resident_listing_is_limited_to_their_barangay(): void
    {
        $agtambi = Barangay::query()
            ->where('barangay_name', 'Agtambi')
            ->firstOrFail();

        $duyoc = Barangay::query()
            ->where('barangay_name', 'Duyoc')
            ->firstOrFail();

        $resident = User::factory()->create([
            'role' => 'resident',
            'barangay_id' => $agtambi->id,
        ]);

        $agtambiAnnouncement = Announcement::query()->create([
            'title' => 'Agtambi Notice',
            'content' => 'For Agtambi residents.',
            'category' => 'general',
            'priority' => 'normal',
            'audience' => 'residents',
            'is_active' => true,
            'activate_calamity_mode' => false,
            'posted_by' => $resident->id,
            'published_at' => now(),
        ]);

        $duyocAnnouncement = Announcement::query()->create([
            'title' => 'Duyoc Notice',
            'content' => 'For Duyoc residents.',
            'category' => 'general',
            'priority' => 'normal',
            'audience' => 'residents',
            'is_active' => true,
            'activate_calamity_mode' => false,
            'posted_by' => $resident->id,
            'published_at' => now(),
        ]);

        $agtambiAnnouncement->targetBarangays()->attach($agtambi->id);
        $duyocAnnouncement->targetBarangays()->attach($duyoc->id);

        $this->actingAs($resident)
            ->get(route('resident.announcements.index'))
            ->assertOk()
            ->assertSee('Agtambi Notice')
            ->assertDontSee('Duyoc Notice');
    }

    public function test_official_can_post_barangay_targeted_announcement(): void
    {
        $agtambi = Barangay::query()
            ->where('barangay_name', 'Agtambi')
            ->firstOrFail();

        $official = User::factory()->create([
            'role' => 'official',
            'barangay_id' => $agtambi->id,
        ]);

        $response = $this->actingAs($official)->post(
            route('official.announcements.store'),
            [
                'title' => 'Official Barangay Notice',
                'content' => 'Barangay-scoped official announcement.',
                'category' => 'community',
                'priority' => 'normal',
                'audience' => 'residents',
                'barangay_ids' => [$agtambi->id],
            ]
        );

        $response->assertRedirect();

        $announcementId = (int) DB::table('announcements')
            ->where('title', 'Official Barangay Notice')
            ->value('id');

        $this->assertGreaterThan(0, $announcementId);

        $this->assertDatabaseHas('announcement_barangay', [
            'announcement_id' => $announcementId,
            'barangay_id' => $agtambi->id,
        ]);
    }

    public function test_targeted_announcement_policy_rejects_a_resident_from_another_barangay(): void
    {
        $agtambi = Barangay::query()
            ->where('barangay_name', 'Agtambi')
            ->firstOrFail();

        $duyoc = Barangay::query()
            ->where('barangay_name', 'Duyoc')
            ->firstOrFail();

        $resident = User::factory()->create([
            'role' => 'resident',
            'barangay_id' => $duyoc->id,
        ]);

        $announcement = Announcement::query()->create([
            'title' => 'Agtambi Only',
            'content' => 'A targeted notice.',
            'category' => 'general',
            'priority' => 'normal',
            'audience' => 'residents',
            'is_active' => true,
            'activate_calamity_mode' => false,
            'posted_by' => $resident->id,
            'published_at' => now(),
        ]);

        $announcement->targetBarangays()->attach($agtambi->id);

        $this->assertFalse(
            $resident->can('view', $announcement)
        );
    }

    public function test_legacy_unscoped_announcement_keeps_old_visibility_rules(): void
    {
        $resident = User::factory()->create([
            'role' => 'resident',
            'barangay_id' => null,
        ]);

        $legacy = Announcement::query()->create([
            'title' => 'Legacy Announcement',
            'content' => 'Existing announcement from before barangay targeting.',
            'category' => 'general',
            'priority' => 'normal',
            'audience' => 'residents',
            'is_active' => true,
            'activate_calamity_mode' => false,
            'posted_by' => $resident->id,
            'published_at' => now(),
        ]);

        $this->assertTrue(
            $resident->can('view', $legacy)
        );
    }

    public function test_announcement_requires_at_least_one_target_barangay(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.announcements.index'))
            ->post(route('admin.announcements.store'), [
                'title' => 'Untargeted Notice',
                'content' => 'This should be rejected.',
                'category' => 'general',
                'priority' => 'normal',
                'audience' => 'residents',
            ])
            ->assertSessionHasErrors('barangay_ids');

        $this->assertDatabaseMissing('announcements', [
            'title' => 'Untargeted Notice',
        ]);
    }
}

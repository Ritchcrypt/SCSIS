<?php

namespace Tests\Feature\Security;

use App\Models\Evidence;
use App\Models\ResidentComplaint;
use App\Models\User;
use App\Services\SecureUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateFileLifecycleSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_private_disk_serving_routes_are_disabled(): void
    {
        $this->assertFalse(
            (bool) config(
                'filesystems.disks.local.serve'
            )
        );

        $this->assertFalse(
            Route::has('storage.local')
        );

        $this->assertFalse(
            Route::has('storage.local.upload')
        );
    }

    public function test_legacy_migration_dry_run_does_not_move_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        Storage::disk('public')->put(
            'profile-photos/legacy.jpg',
            'legacy-profile'
        );

        $this->artisan(
            'secure-uploads:migrate-legacy',
            [
                '--dry-run' => true,
            ]
        )->assertExitCode(0);

        Storage::disk('public')->assertExists(
            'profile-photos/legacy.jpg'
        );

        Storage::disk('local')->assertMissing(
            'profile-photos/legacy.jpg'
        );
    }

    public function test_legacy_migration_moves_only_sensitive_directories(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $sensitiveFiles = [
            'profile-photos/legacy.jpg',
            'incidents/evidence/report.pdf',
            'resident-complaints/evidence.jpg',
            'resident-complaints/proofs/proof.jpg',
        ];

        foreach ($sensitiveFiles as $path) {
            Storage::disk('public')->put(
                $path,
                'legacy-sensitive-file'
            );
        }

        Storage::disk('public')->put(
            'system-branding/logo.png',
            'public-branding-file'
        );

        $this->artisan(
            'secure-uploads:migrate-legacy',
            [
                '--force' => true,
            ]
        )->assertExitCode(0);

        foreach ($sensitiveFiles as $path) {
            Storage::disk('local')->assertExists(
                $path
            );

            Storage::disk('public')->assertMissing(
                $path
            );
        }

        Storage::disk('public')->assertExists(
            'system-branding/logo.png'
        );

        Storage::disk('local')->assertMissing(
            'system-branding/logo.png'
        );
    }

    public function test_evidence_url_uses_the_authorised_controller_route(): void
    {
        $evidence = new Evidence([
            'file_path' =>
                'incidents/evidence/private.pdf',
        ]);

        $evidence->forceFill([
            'id' => 321,
        ]);

        $evidence->exists = true;

        $url = $evidence->file_url;

        $this->assertNotNull(
            $url
        );

        $this->assertStringContainsString(
            '/incident-evidence/321/file',
            $url
        );

        $this->assertStringNotContainsString(
            '/storage/',
            $url
        );
    }

    public function test_complaint_evidence_response_is_private_and_not_stored_by_caches(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        /** @var User $resident */
        $resident = $this->activeResident();

        $path = app(
            SecureUploadService::class
        )->store(
            UploadedFile::fake()->image(
                'complaint.jpg',
                600,
                600
            ),
            'complaint_evidence'
        );

        $complaint = $this->complaint(
            $resident,
            $path
        );

        $response = $this
            ->actingAs($resident)
            ->get(
                route(
                    'resident.resident-complaints.evidence',
                    $complaint
                )
            );

        $response
            ->assertOk()
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );

        $cacheControl = (string) $response
            ->headers
            ->get('Cache-Control');

        $this->assertStringContainsString(
            'private',
            $cacheControl
        );

        $this->assertStringContainsString(
            'no-store',
            $cacheControl
        );

        $this->assertStringNotContainsString(
            'public',
            $cacheControl
        );
    }

    public function test_complaint_deletion_removes_the_file_after_database_deletion(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        /** @var User $admin */
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ]);

        /** @var User $resident */
        $resident = $this->activeResident();

        $path = app(
            SecureUploadService::class
        )->store(
            UploadedFile::fake()->image(
                'delete-evidence.jpg',
                500,
                500
            ),
            'complaint_evidence'
        );

        $complaint = $this->complaint(
            $resident,
            $path
        );

        Storage::disk('local')->assertExists(
            $path
        );

        $response = $this
            ->actingAs($admin)
            ->delete(
                route(
                    'admin.resident-complaints.destroy',
                    $complaint
                )
            );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseMissing(
            'resident_complaints',
            [
                'id' => $complaint->id,
            ]
        );

        Storage::disk('local')->assertMissing(
            $path
        );
    }

    public function test_self_account_deletion_removes_the_private_profile_photo(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $password = 'DeletePrivate#2026';

        /** @var User $resident */
        $resident = $this->activeResident([
            'password' => Hash::make(
                $password
            ),
        ]);

        $path = app(
            SecureUploadService::class
        )->store(
            UploadedFile::fake()->image(
                'private-profile.jpg',
                400,
                400
            ),
            'profile_photo'
        );

        $resident->forceFill([
            'profile_photo_path' => $path,
        ])->save();

        Storage::disk('local')->assertExists(
            $path
        );

        $response = $this
            ->actingAs($resident)
            ->delete(
                route('profile.self-delete'),
                [
                    'password' => $password,
                ]
            );

        $response->assertRedirect('/');

        Storage::disk('local')->assertMissing(
            $path
        );
    }

    private function activeResident(
        array $attributes = []
    ): User {
        /** @var User $user */
        $user = User::factory()->create(
            array_merge([
                'role' => 'resident',
                'is_active' => true,
                'status' => true,
            ], $attributes)
        );

        return $user;
    }

    private function complaint(
        User $resident,
        string $evidencePath
    ): ResidentComplaint {
        return ResidentComplaint::query()->create([
            'resident_id' => $resident->id,
            'complainant_name' => $resident->name,
            'contact_number' => '09123456789',
            'complaint_description' =>
                'Private-file lifecycle test complaint.',
            'complaint_address' => 'Dao, Capiz',
            'evidence_path' => $evidencePath,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }
}

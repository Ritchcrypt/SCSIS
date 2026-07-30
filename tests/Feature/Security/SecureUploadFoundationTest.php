<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Rules\SecureUploadedFile;
use App\Services\SecureUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SecureUploadFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_photo_is_stored_on_the_private_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        /** @var User $user */
        $user = $this->activeResident();

        $photo = UploadedFile::fake()->image(
            'resident-photo.jpg',
            600,
            600
        )->size(500);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'contact_number' => $user->contact_number,
                'address' => $user->address,
                'profile_photo' => $photo,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNotNull(
            $user->profile_photo_path
        );

        $this->assertStringStartsWith(
            'profile-photos/',
            $user->profile_photo_path
        );

        Storage::disk('local')->assertExists(
            $user->profile_photo_path
        );

        Storage::disk('public')->assertMissing(
            $user->profile_photo_path
        );
    }

    public function test_authorised_profile_photo_route_serves_private_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        /** @var User $user */
        $user = $this->activeResident();

        $path = app(
            SecureUploadService::class
        )->store(
            UploadedFile::fake()->image(
                'profile.png',
                300,
                300
            ),
            'profile_photo'
        );

        $user->forceFill([
            'profile_photo_path' => $path,
        ])->save();

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    'users.profile-photo',
                    $user
                )
            );

        $response
            ->assertOk()
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );

        $cacheControl = (string) $response->headers->get(
            'Cache-Control'
        );

        /*
        |--------------------------------------------------------------------------
        | Cache-Control directive order is implementation-defined
        |--------------------------------------------------------------------------
        |
        | Symfony may serialize the directives as "max-age=3600, private".
        | Validate their security meaning instead of requiring one text order.
        |
        */

        $this->assertStringContainsString(
            'private',
            $cacheControl
        );

        $this->assertStringContainsString(
            'max-age=3600',
            $cacheControl
        );

        $this->assertStringNotContainsString(
            'public',
            $cacheControl
        );
    }

    public function test_other_residents_cannot_view_another_users_profile_photo(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        /** @var User $owner */
        $owner = $this->activeResident();

        /** @var User $otherResident */
        $otherResident = $this->activeResident();

        $path = app(
            SecureUploadService::class
        )->store(
            UploadedFile::fake()->image(
                'private-profile.jpg',
                300,
                300
            ),
            'profile_photo'
        );

        $owner->forceFill([
            'profile_photo_path' => $path,
        ])->save();

        $response = $this
            ->actingAs($otherResident)
            ->get(
                route(
                    'users.profile-photo',
                    $owner
                )
            );

        $response->assertForbidden();
    }

    public function test_disguised_executable_is_rejected_as_an_image(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'avatar.php.jpg',
            '<?php echo "malicious"; ?>'
        );

        $validator = Validator::make(
            [
                'profile_photo' => $file,
            ],
            [
                'profile_photo' => [
                    'required',
                    new SecureUploadedFile(
                        'profile_photo'
                    ),
                ],
            ]
        );

        $this->assertTrue(
            $validator->fails()
        );
    }

    public function test_valid_pdf_evidence_passes_signature_validation(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'incident-report.pdf',
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"
        );

        $validator = Validator::make(
            [
                'evidence' => $file,
            ],
            [
                'evidence' => [
                    'required',
                    new SecureUploadedFile(
                        'incident_evidence'
                    ),
                ],
            ]
        );

        $this->assertFalse(
            $validator->fails(),
            implode(
                ' ',
                $validator->errors()->all()
            )
        );
    }

    public function test_legacy_public_profile_photo_remains_available_through_authorised_route(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        /** @var User $user */
        $user = $this->activeResident();

        $legacyFile = UploadedFile::fake()->image(
            'legacy.jpg',
            200,
            200
        );

        $legacyPath = Storage::disk('public')->putFileAs(
            'profile-photos',
            $legacyFile,
            'legacy.jpg'
        );

        $user->forceFill([
            'profile_photo_path' => $legacyPath,
        ])->save();

        Storage::disk('local')->assertMissing(
            $legacyPath
        );

        Storage::disk('public')->assertExists(
            $legacyPath
        );

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    'users.profile-photo',
                    $user
                )
            );

        $response->assertOk();
    }

    public function test_unsafe_storage_paths_are_never_resolved(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $resolved = app(
            SecureUploadService::class
        )->resolve(
            '../private-secret.txt',
            [
                'profile-photos',
            ],
            [
                'image/jpeg',
            ]
        );

        $this->assertNull(
            $resolved
        );
    }

    private function activeResident(): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
            'contact_number' => '09123456789',
            'address' => 'Dao, Capiz',
        ]);

        return $user;
    }
}

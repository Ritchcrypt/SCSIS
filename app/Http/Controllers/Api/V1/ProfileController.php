<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\SecureUploadedFile;
use App\Services\ActivityLogger;
use App\Services\SecureUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProfileController extends Controller
{
    /* TABANGNOW_MOBILE_SELF_PROFILE_V2 */

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly SecureUploadService $secureUploads
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $user->refresh();
        $role = strtolower(trim((string) $user->role));
        $selfService = in_array($role, ['official', 'dao', 'tanod', 'resident'], true);

        return response()->json([
            'data' => $this->serialize($user),
            'permissions' => [
                'can_sign_out_other_devices' => true,
                'can_change_password' => $selfService,
                'can_self_delete' => $selfService,
            ],
            'password_policy' => [
                'minimum_length' => 12,
                'mixed_case' => true,
                'numbers' => true,
                'symbols' => true,
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request): JsonResponse
    {
        $actor = $this->authenticatedUser($request);

        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($actor->id),
            ],
            'contact_number' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^[0-9+()\-\s]*$/',
            ],
            'address' => ['nullable', 'string', 'max:1000'],
            'profile_photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                new SecureUploadedFile('profile_photo'),
            ],
        ]);

        $actor->refresh();

        $original = [
            'name' => (string) $actor->name,
            'email' => (string) $actor->email,
            'contact_number' => (string) ($actor->contact_number ?? ''),
            'address' => (string) ($actor->address ?? ''),
        ];

        $normalizedEmail = strtolower(trim((string) $validated['email']));
        $emailChanged = strcasecmp((string) $actor->email, $normalizedEmail) !== 0;
        $oldEmail = (string) $actor->email;
        $oldPhoto = $this->normalizeProfilePhotoPath($actor->profile_photo_path ?? null);
        $newPhoto = $this->storeProfilePhoto($request);

        try {
            $updated = DB::transaction(function () use (
                $actor,
                $validated,
                $normalizedEmail,
                $emailChanged,
                $newPhoto
            ): User {
                $user = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $user->name = trim((string) $validated['name']);
                $user->email = $normalizedEmail;

                if ($emailChanged && Schema::hasColumn('users', 'email_verified_at')) {
                    $user->email_verified_at = null;
                }

                if (Schema::hasColumn('users', 'contact_number')) {
                    $user->contact_number = $this->normalizeContactNumber(
                        $validated['contact_number'] ?? null
                    );
                }

                if (Schema::hasColumn('users', 'address')) {
                    $address = $validated['address'] ?? null;
                    $user->address = is_string($address) && trim($address) !== ''
                        ? trim($address)
                        : null;
                }

                if ($newPhoto && Schema::hasColumn('users', 'profile_photo_path')) {
                    $user->profile_photo_path = $newPhoto;
                }

                $user->save();
                $user->refresh();
                return $user;
            });
        } catch (Throwable $exception) {
            if ($newPhoto) {
                $this->secureUploads->delete($newPhoto, ['profile-photos']);
            }
            throw $exception;
        }

        if ($emailChanged) {
            $this->deletePasswordResetTokens($oldEmail);
        }

        if ($newPhoto && $oldPhoto && $oldPhoto !== $newPhoto) {
            $this->secureUploads->delete($oldPhoto, ['profile-photos']);
        }

        $changedFields = [];
        if ($original['name'] !== (string) $updated->name) $changedFields[] = 'name';
        if (strcasecmp($original['email'], (string) $updated->email) !== 0) $changedFields[] = 'email';
        if ($original['contact_number'] !== (string) ($updated->contact_number ?? '')) $changedFields[] = 'contact_number';
        if ($original['address'] !== (string) ($updated->address ?? '')) $changedFields[] = 'address';
        if ($newPhoto) $changedFields[] = 'profile_photo';

        if ($changedFields !== []) {
            $this->activityLogger->record(
                event: 'account.profile_updated',
                category: 'account',
                description: 'User updated their profile information.',
                actor: $updated,
                target: $updated,
                metadata: [
                    'changed_fields' => array_values(array_unique($changedFields)),
                    'source' => 'mobile_api',
                ],
                request: $request,
            );
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => $this->serialize($updated),
            'email_verification_required' => $emailChanged,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function photo(Request $request): BinaryFileResponse
    {
        $user = $this->authenticatedUser($request);
        $user->refresh();
        $path = $this->normalizeProfilePhotoPath($user->profile_photo_path ?? null);

        if (!$path) abort(404, 'Profile photo not found.');

        $storedFile = $this->secureUploads->resolve(
            $path,
            ['profile-photos'],
            (array) config('secure_uploads.policies.profile_photo.allowed_mime_types', [])
        );

        if (!$storedFile) abort(404, 'Profile photo not found.');

        $fileName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($storedFile['path']));
        $response = response()->file($storedFile['absolute_path'], [
            'Content-Type' => $storedFile['mime_type'],
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
        $response->setPrivate();
        $response->setMaxAge(0);
        return $response;
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $role = strtolower(trim((string) $user->role));

        abort_unless(in_array($role, ['admin', 'official', 'dao', 'tanod', 'resident'], true), 403);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                PasswordRule::defaults(),
                'confirmed',
                'different:current_password',
            ],
        ]);

        $this->verifyPassword($user, (string) $validated['current_password'], 'current_password');
        $currentTokenId = $this->currentTokenId($user);

        DB::transaction(function () use ($user, $validated, $currentTokenId): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'password' => Hash::make((string) $validated['password']),
                'remember_token' => Str::random(60),
            ])->save();

            $this->revokeOtherAuthenticationArtifacts($locked, $currentTokenId, true);
        });

        $user->refresh();
        $this->activityLogger->record(
            event: 'auth.password_changed',
            category: 'authentication',
            description: 'User changed their account password.',
            actor: $user,
            target: $user,
            metadata: [
                'other_sessions_revoked' => true,
                'remember_token_rotated' => true,
                'current_mobile_token_preserved' => $currentTokenId !== null,
                'source' => 'mobile_api',
            ],
            request: $request,
        );

        return response()->json([
            'message' => 'Password updated successfully. Other active sessions were signed out.',
        ])->header('Cache-Control', 'no-store, private');
    }

    public function destroyOtherSessions(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $this->verifyPassword($user, (string) $validated['password'], 'password');
        $currentTokenId = $this->currentTokenId($user);

        DB::transaction(function () use ($user, $currentTokenId): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (Schema::hasColumn('users', 'remember_token')) {
                $locked->remember_token = Str::random(60);
                $locked->save();
            }

            $this->revokeOtherAuthenticationArtifacts($locked, $currentTokenId, false);
        });

        $user->refresh();
        $this->activityLogger->record(
            event: 'auth.other_sessions_revoked',
            category: 'authentication',
            description: 'User signed out other browsers and devices.',
            actor: $user,
            target: $user,
            metadata: [
                'current_mobile_token_preserved' => $currentTokenId !== null,
                'remember_token_rotated' => true,
                'personal_access_tokens_revoked' => true,
                'source' => 'mobile_api',
            ],
            request: $request,
        );

        return response()->json([
            'message' => 'Other browser and device sessions were signed out successfully. This phone remains signed in.',
        ])->header('Cache-Control', 'no-store, private');
    }

    public function destroyOwnAccount(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $role = strtolower(trim((string) $user->role));
        abort_unless(in_array($role, ['official', 'dao', 'tanod', 'resident'], true), 403);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $this->verifyPassword($user, (string) $validated['password'], 'password');
        $photoPath = $this->normalizeProfilePhotoPath($user->profile_photo_path ?? null);

        DB::transaction(function () use ($request, $user, $role): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->revokeAllAuthenticationArtifacts($locked);
            $deleted = $locked->delete();

            if (!$deleted) {
                throw new \RuntimeException('The user account could not be deleted.');
            }

            $this->activityLogger->record(
                event: 'account.self_deleted',
                category: 'account',
                description: 'User permanently deleted their own account.',
                actor: $locked,
                target: $locked,
                metadata: ['role' => $role, 'source' => 'mobile_api'],
                request: $request,
            );
        });

        if ($photoPath) {
            $this->secureUploads->delete($photoPath, ['profile-photos']);
        }

        return response()->json([
            'message' => 'Your account was permanently deleted.',
            'account_deleted' => true,
        ])->header('Cache-Control', 'no-store, private');
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Authentication is required.');
        return $user;
    }

    private function serialize(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'username' => $user->username,
            'email' => (string) $user->email,
            'contact_number' => Schema::hasColumn('users', 'contact_number') ? $user->contact_number : null,
            'address' => Schema::hasColumn('users', 'address') ? $user->address : null,
            'role' => (string) $user->role,
            'role_label' => ucfirst(strtolower((string) $user->role)),
            'status' => $user->isActive() ? 'active' : 'inactive',
            'status_label' => $user->isActive() ? 'Active' : 'Inactive',
            'barangay_id' => Schema::hasColumn('users', 'barangay_id') ? $user->barangay_id : null,
            'has_profile_photo' => Schema::hasColumn('users', 'profile_photo_path') && !empty($user->profile_photo_path),
            'profile_photo_version' => optional($user->updated_at)->toIso8601String(),
            'email_verified' => !Schema::hasColumn('users', 'email_verified_at') || $user->email_verified_at !== null,
        ];
    }

    private function verifyPassword(User $user, string $password, string $field): void
    {
        if (!Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                $field => ['The current password is incorrect.'],
            ]);
        }
    }

    private function currentTokenId(User $user): ?int
    {
        $token = $user->currentAccessToken();
        return $token instanceof PersonalAccessToken ? (int) $token->getKey() : null;
    }

    private function revokeOtherAuthenticationArtifacts(User $user, ?int $currentTokenId, bool $deleteResetTokens): void
    {
        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        if (Schema::hasTable('personal_access_tokens')
            && Schema::hasColumn('personal_access_tokens', 'tokenable_id')
            && Schema::hasColumn('personal_access_tokens', 'tokenable_type')) {
            $tokens = DB::table('personal_access_tokens')
                ->where('tokenable_id', $user->id)
                ->where('tokenable_type', User::class);

            if ($currentTokenId !== null) {
                $tokens->where('id', '!=', $currentTokenId);
            }

            $tokens->delete();
        }

        if ($deleteResetTokens) {
            $this->deletePasswordResetTokens((string) $user->email);
        }
    }

    private function revokeAllAuthenticationArtifacts(User $user): void
    {
        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        if (Schema::hasTable('personal_access_tokens')
            && Schema::hasColumn('personal_access_tokens', 'tokenable_id')
            && Schema::hasColumn('personal_access_tokens', 'tokenable_type')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $user->id)
                ->where('tokenable_type', User::class)
                ->delete();
        }

        $this->deletePasswordResetTokens((string) $user->email);
    }

    private function storeProfilePhoto(Request $request): ?string
    {
        if (!Schema::hasColumn('users', 'profile_photo_path')) return null;
        if (!$request->hasFile('profile_photo')) return null;

        return $this->normalizeProfilePhotoPath(
            $this->secureUploads->store($request->file('profile_photo'), 'profile_photo')
        );
    }

    private function normalizeProfilePhotoPath(mixed $path): ?string
    {
        if ($path === null) return null;
        $path = str_replace('\\', '/', trim((string) $path));
        $path = preg_replace('#^/?storage/#', '', $path);
        $path = preg_replace('#^/?public/#', '', (string) $path);
        $path = preg_replace('#^/?private/#', '', (string) $path);
        $path = ltrim((string) $path, '/');

        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) return null;
        return $path;
    }

    private function normalizeContactNumber(mixed $value): ?string
    {
        if ($value === null) return null;
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function deletePasswordResetTokens(string $email): void
    {
        foreach (['password_reset_tokens', 'password_resets'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'email')) {
                DB::table($table)->where('email', $email)->delete();
            }
        }
    }
}
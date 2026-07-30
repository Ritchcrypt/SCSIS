<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $authUser = $request->user();

        if (! $authUser) {
            abort(403, 'Unauthorized access.');
        }

        $userRecord = User::query()->findOrFail($authUser->id);

        return view('profile.edit', [
            'userRecord' => $userRecord,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $authUser = $request->user();

        if (! $authUser) {
            abort(403, 'Unauthorized access.');
        }

        $userRecord = User::query()->findOrFail($authUser->id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userRecord->id),
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
                'max:51200',
            ],
        ]);

        $oldProfilePhotoPath = $userRecord->profile_photo_path ?? null;
        $newProfilePhotoPath = $this->storeProfilePhoto($request);

        DB::transaction(function () use (
            $validated,
            $userRecord,
            $newProfilePhotoPath
        ): void {
            $userRecord->name = $validated['name'];
            $userRecord->email = $validated['email'];

            if (Schema::hasColumn('users', 'contact_number')) {
                $userRecord->contact_number = $this->normalizeContactNumber(
                    $validated['contact_number'] ?? null
                );
            }

            if (Schema::hasColumn('users', 'address')) {
                $userRecord->address = $validated['address'] ?? null;
            }

            if (
                $newProfilePhotoPath
                && Schema::hasColumn('users', 'profile_photo_path')
            ) {
                $userRecord->profile_photo_path = $newProfilePhotoPath;
            }

            $userRecord->save();
        });

        if ($newProfilePhotoPath && $oldProfilePhotoPath) {
            $oldProfilePhotoPath = $this->normalizeProfilePhotoPath(
                $oldProfilePhotoPath
            );

            if (
                $oldProfilePhotoPath
                && Storage::disk('public')->exists($oldProfilePhotoPath)
            ) {
                Storage::disk('public')->delete($oldProfilePhotoPath);
            }
        }

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user && in_array(
                strtolower((string) $user->role),
                [
                    'admin',
                    'official',
                    'dao',
                    'tanod',
                    'resident',
                ],
                true
            ),
            403
        );

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => [
                'required',
                'string',
                'current_password',
            ],
            'password' => [
                'required',
                'string',
                Password::defaults(),
                'confirmed',
                'different:current_password',
            ],
        ]);

        $currentSessionId = $request->session()->getId();

        DB::transaction(function () use (
            $validated,
            $user,
            $currentSessionId
        ): void {
            $user->forceFill([
                'password' => Hash::make($validated['password']),
                'remember_token' => Str::random(60),
            ])->save();

            /*
            |--------------------------------------------------------------------------
            | Revoke other authentication credentials
            |--------------------------------------------------------------------------
            |
            | Keep this browser session active, but terminate sessions on other
            | devices and invalidate persistent tokens.
            |
            */

            $this->revokeAuthenticationArtifacts(
                $user,
                $currentSessionId
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Rotate current-session security values
        |--------------------------------------------------------------------------
        */

        $request->session()->forget('auth.password_confirmed_at');
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $request->session()->put(
            'security.last_activity_at',
            time()
        );

        return redirect()
            ->route('profile.edit')
            ->with(
                'success',
                'Password updated successfully. Other active sessions were signed out.'
            );
    }

    public function destroyOwnAccount(Request $request): RedirectResponse
{
    $user = $request->user();

    abort_unless(
        $user && in_array(
            strtolower((string) $user->role),
            [
                'official',
                'dao',
                'tanod',
                'resident',
            ],
            true
        ),
        403
    );

    $request->validateWithBag('userDeletion', [
        'password' => [
            'required',
            'current_password',
        ],
    ]);

    /*
    |--------------------------------------------------------------------------
    | Log out before deleting the authenticated model
    |--------------------------------------------------------------------------
    |
    | Laravel rotates the remember token during logout. Logging out after
    | deletion could cause the deleted Eloquent user model to be saved again.
    |
    */

    Auth::guard('web')->logout();

    DB::transaction(function () use ($user): void {
        $this->revokeAuthenticationArtifacts($user);

        $deleted = $user->delete();

        if (! $deleted) {
            throw new \RuntimeException(
                'The user account could not be deleted.'
            );
        }
    });

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
}

    private function revokeAuthenticationArtifacts(
        User $user,
        ?string $exceptSessionId = null
    ): void {
        if (
            Schema::hasTable('sessions')
            && Schema::hasColumn('sessions', 'user_id')
        ) {
            $sessions = DB::table('sessions')
                ->where('user_id', $user->id);

            if (
                $exceptSessionId !== null
                && $exceptSessionId !== ''
                && Schema::hasColumn('sessions', 'id')
            ) {
                $sessions->where('id', '!=', $exceptSessionId);
            }

            $sessions->delete();
        }

        if (
            Schema::hasTable('personal_access_tokens')
            && Schema::hasColumn('personal_access_tokens', 'tokenable_id')
            && Schema::hasColumn('personal_access_tokens', 'tokenable_type')
        ) {
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $user->id)
                ->where('tokenable_type', User::class)
                ->delete();
        }

        foreach (['password_reset_tokens', 'password_resets'] as $table) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn($table, 'email')
            ) {
                DB::table($table)
                    ->where('email', $user->email)
                    ->delete();
            }
        }
    }

    private function storeProfilePhoto(Request $request): ?string
    {
        if (! Schema::hasColumn('users', 'profile_photo_path')) {
            return null;
        }

        if (! $request->hasFile('profile_photo')) {
            return null;
        }

        $path = $request
            ->file('profile_photo')
            ->store('profile-photos', 'public');

        return $this->normalizeProfilePhotoPath($path);
    }

    private function normalizeProfilePhotoPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^/?storage/#', '', $path);
        $path = preg_replace('#^/?public/#', '', $path);
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    private function normalizeContactNumber(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $contactNumber = trim((string) $value);

        return $contactNumber !== '' ? $contactNumber : null;
    }
}

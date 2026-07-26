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
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]*$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
        ]);

        $oldProfilePhotoPath = $userRecord->profile_photo_path ?? null;
        $newProfilePhotoPath = $this->storeProfilePhoto($request);

        DB::transaction(function () use ($validated, $userRecord, $newProfilePhotoPath) {
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

            if ($newProfilePhotoPath && Schema::hasColumn('users', 'profile_photo_path')) {
                $userRecord->profile_photo_path = $newProfilePhotoPath;
            }

            $userRecord->save();
        });

        if ($newProfilePhotoPath && $oldProfilePhotoPath) {
            $oldProfilePhotoPath = $this->normalizeProfilePhotoPath($oldProfilePhotoPath);

            if ($oldProfilePhotoPath && Storage::disk('public')->exists($oldProfilePhotoPath)) {
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
        | Invalidate the user's other database sessions
        |--------------------------------------------------------------------------
        |
        | Keep the current browser session active, but remove sessions from
        | other browsers or devices.
        |
        */

        if (
            Schema::hasTable('sessions')
            && Schema::hasColumn('sessions', 'id')
            && Schema::hasColumn('sessions', 'user_id')
        ) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();
        }
    });

    /*
    |--------------------------------------------------------------------------
    | Rotate current-session security values
    |--------------------------------------------------------------------------
    */

    $request->session()->forget('auth.password_confirmed_at');
    $request->session()->regenerate();
    $request->session()->regenerateToken();

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

        abort_unless($user && in_array(strtolower((string) $user->role), [
            'official',
            'dao',
            'tanod',
            'resident',
        ], true), 403);

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function storeProfilePhoto(Request $request): ?string
    {
        if (! Schema::hasColumn('users', 'profile_photo_path')) {
            return null;
        }

        if (! $request->hasFile('profile_photo')) {
            return null;
        }

        $path = $request->file('profile_photo')->store('profile-photos', 'public');

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
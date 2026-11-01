<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

/**
 * Public mobile completion of a password reset issued by the existing shared broker.
 * Do not change the Laravel website's current browser reset endpoint.
 */
final class MobilePasswordResetController extends Controller
{
    public function reset(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:512'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Same reset-token store, expiry, single-use behavior, and password policy
        // as the Laravel website. No privileged role is required to reset one's
        // OWN password using a valid email token.
        $status = Password::reset($validated, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ]);

            if (Schema::hasColumn('users', 'last_seen_at')) {
                $user->last_seen_at = null;
            }

            $user->save();

            // Match successful web reset revocation. Password reset terminates
            // existing database sessions, remember-me cookies and Sanctum tokens.
            if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
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

            if (
                Schema::hasTable('mobile_push_tokens')
                && Schema::hasColumn('mobile_push_tokens', 'revoked_at')
            ) {
                DB::table('mobile_push_tokens')
                    ->where('user_id', $user->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }

            event(new PasswordReset($user));
        });

        if ($status !== Password::PasswordReset) {
            return response()->json([
                'message' => 'The reset link is invalid or expired. Request a new link.',
            ], 422);
        }

        return response()->json([
            'message' => 'Password reset successfully. Log in with your new password.',
        ]);
    }
}

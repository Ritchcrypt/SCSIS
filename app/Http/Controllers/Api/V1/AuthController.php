<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Normalize mobile login input
        |--------------------------------------------------------------------------
        |
        | Keep this consistent with the existing TabangNow website login.
        |
        */
        $request->merge([
            'email' => Str::lower(
                trim((string) $request->input('email'))
            ),
            'device_name' => trim(
                (string) $request->input('device_name')
            ),
        ]);

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
            ],
            'device_name' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $throttleKey = $this->throttleKey(
            $validated['email'],
            $request->ip()
        );

        /*
        |--------------------------------------------------------------------------
        | Login throttling
        |--------------------------------------------------------------------------
        |
        | Match the website's five-attempt authentication limit.
        |
        */
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            event(new Lockout($request));

            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => __('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => (int) ceil($seconds / 60),
                ]),
                'retry_after' => $seconds,
            ], 429, [
                'Retry-After' => (string) $seconds,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Existing TabangNow account authentication
        |--------------------------------------------------------------------------
        |
        | Auth::once performs authentication without creating a browser session
        | or authentication cookie.
        |
        */
        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_active' => true,
        ];

        if (! Auth::guard('web')->once($credentials)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => __('auth.failed'),
                'errors' => [
                    'email' => [
                        __('auth.failed'),
                    ],
                ],
            ], 422);
        }

        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => __('auth.failed'),
                'errors' => [
                    'email' => [
                        __('auth.failed'),
                    ],
                ],
            ], 422);
        }

        RateLimiter::clear($throttleKey);

        /*
        |--------------------------------------------------------------------------
        | Create the Sanctum mobile token
        |--------------------------------------------------------------------------
        */
        $token = $user->createToken(
            $validated['device_name'],
            ['mobile']
        );

        /*
        |--------------------------------------------------------------------------
        | Preserve TabangNow authentication activity logging
        |--------------------------------------------------------------------------
        */
        event(new Login(
            'sanctum',
            $user,
            false
        ));

        return response()->json([
            'message' => 'Login successful.',
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Revoke only the token used for this request
        |--------------------------------------------------------------------------
        |
        | Do not delete every token belonging to the user.
        |
        */
        $accessToken = $user->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }

        event(new Logout(
            'sanctum',
            $user
        ));

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }

    private function throttleKey(
        string $email,
        ?string $ipAddress
    ): string {
        return Str::transliterate(
            Str::lower(trim($email))
            . '|'
            . ($ipAddress ?? 'unknown')
        );
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'barangay_id' => $user->barangay_id,
        ];
    }
}
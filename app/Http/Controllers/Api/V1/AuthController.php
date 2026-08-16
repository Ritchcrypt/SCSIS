<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('name'));
        $email = Str::lower(trim((string) $request->input('email')));
        $contactNumber = (string) preg_replace(
            '/[\s\-\(\)]+/',
            '',
            trim((string) $request->input('contact_number'))
        );

        if (Str::startsWith($contactNumber, '+63')) {
            $contactNumber = '0'.substr($contactNumber, 3);
        }

        $address = (string) preg_replace(
            '/\s+/',
            ' ',
            trim((string) $request->input('address'))
        );

        $request->merge([
            'name' => $name,
            'email' => $email,
            'contact_number' => $contactNumber,
            'address' => $address,
        ]);

        $throttleKey = 'registration|'.($request->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => 'Too many registration attempts. Try again later.',
                'retry_after' => $seconds,
            ], 429, [
                'Retry-After' => (string) $seconds,
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'contact_number' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
            ],
            'address' => ['required', 'string', 'max:500'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Rules\Password::defaults(),
            ],
        ], [
            'contact_number.required' => 'Contact number is required.',
            'contact_number.regex' => 'Enter a valid Philippine mobile number, such as 09123456789.',
            'address.required' => 'Complete address is required.',
            'address.max' => 'Address must not exceed 500 characters.',
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'contact_number' => $validated['contact_number'],
            'address' => $validated['address'],
            'password' => Hash::make($validated['password']),
            'role' => 'resident',
            'is_active' => false,
            'status' => false,
        ]);

        if (
            Schema::hasTable('notifications')
            && Schema::hasColumn('notifications', 'user_id')
        ) {
            $adminIds = User::query()
                ->where('role', 'admin')
                ->where('is_active', true)
                ->pluck('id');

            foreach ($adminIds as $adminId) {
                UserNotification::query()->create([
                    'user_id' => $adminId,
                    'type' => 'user_registration',
                    'source_id' => $user->id,
                    'title' => 'New resident registration',
                    'message' => "{$user->name} registered a resident account and is awaiting administrator approval.",
                    'is_read' => false,
                ]);
            }
        }

        event(new Registered($user));

        RateLimiter::hit($throttleKey, 600);

        return response()->json([
            'message' => 'Your resident account was created successfully and is awaiting administrator approval.',
            'approval_required' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'device_name' => trim((string) $request->input('device_name')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = $this->throttleKey(
            $validated['email'],
            $request->ip()
        );

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
                    'email' => [__('auth.failed')],
                ],
            ], 422);
        }

        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => __('auth.failed'),
                'errors' => [
                    'email' => [__('auth.failed')],
                ],
            ], 422);
        }

        RateLimiter::clear($throttleKey);

        $token = $user->createToken(
            $validated['device_name'],
            ['mobile']
        );

        event(new Login('sanctum', $user, false));

        return response()->json([
            'message' => 'Login successful.',
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(
                trim((string) $request->input('email'))
            ),
        ]);

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Existing TabangNow password-reset broker
        |--------------------------------------------------------------------------
        |
        | This is the same broker used by the website. The response remains
        | neutral so the API does not disclose whether an account exists.
        |
        */
        Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        return response()->json([
            'message' => 'A reset link will be sent if the account exists.',
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

        $accessToken = $user->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }

        event(new Logout('sanctum', $user));

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
            .'|'
            .($ipAddress ?? 'unknown')
        );
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'contact_number' => Schema::hasColumn('users', 'contact_number')
                ? $user->contact_number
                : null,
            'address' => Schema::hasColumn('users', 'address')
                ? $user->address
                : null,
            'role' => $user->role,
            'barangay_id' => $user->barangay_id,
            'has_profile_photo' => Schema::hasColumn('users', 'profile_photo_path')
                && ! empty($user->profile_photo_path),
            'profile_photo_version' => optional($user->updated_at)
                ->toIso8601String(),
        ];
    }
}

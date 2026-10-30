<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MobilePushToken;
use App\Services\AdminTotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

/** Mobile self-service MFA. The website continues using AdminMfaController. */
final class AccountMfaController extends Controller
{
    private function account(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isActive(), 403);
        abort_unless(Schema::hasColumn('users', 'mfa_secret'), 503, 'MFA database migration is required.');
        return $user;
    }

    private function pendingKey(Request $request, User $user): string
    {
        // Enrollment is scoped to the authenticated device token, not a shared user cache key.
        $token = $user->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken, 401);
        return 'tabangnow:mfa:enroll:'.$user->getKey().':'.$token->getKey();
    }

    private function reply(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status)->header('Cache-Control', 'no-store, private');
    }

    private function requirePassword(User $user, string $password): void
    {
        if (! Hash::check($password, (string) $user->password)) {
            abort(422, 'Incorrect account password.');
        }
    }

    /** Revoke all older device tokens, then issue a new MFA-verified token for this device. */
    private function rotateToken(User $user): string
    {
        $name = $user->currentAccessToken()?->name ?? 'TabangNow Mobile';
        $user->tokens()->delete();
        MobilePushToken::query()->where('user_id', $user->id)->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
        return $user->createToken($name, ['mobile'], now()->addDays(30))->plainTextToken;
    }

    public function status(Request $request): JsonResponse
    {
        $user = $this->account($request);
        $codes = json_decode((string) $user->mfa_recovery_codes, true);
        return $this->reply([
            'enabled' => ! empty($user->mfa_secret),
            'recovery_codes_remaining' => is_array($codes) ? count($codes) : 0,
        ]);
    }

    public function begin(Request $request, AdminTotpService $totp): JsonResponse
    {
        $user = $this->account($request);
        abort_if(! empty($user->mfa_secret), 409, 'MFA already enabled.');
        $data = $request->validate(['password' => ['required', 'string']]);
        $this->requirePassword($user, $data['password']);

        $secret = $totp->newSecret();
        Cache::put($this->pendingKey($request, $user), Crypt::encryptString($secret), now()->addMinutes(10));

        return $this->reply([
            'secret' => $secret,
            'provisioning_uri' => $totp->provisioningUri($user, $secret),
            'expires_in' => 600,
        ]);
    }

    public function confirm(Request $request, AdminTotpService $totp): JsonResponse
    {
        $user = $this->account($request);
        abort_if(! empty($user->mfa_secret), 409, 'MFA already enabled.');
        $data = $request->validate(['code' => ['required', 'regex:/^\d{6}$/D']]);
        $key = $this->pendingKey($request, $user);
        $encrypted = Cache::get($key);
        if (! is_string($encrypted)) {
            return $this->reply(['message' => 'Setup expired. Start again.'], 422);
        }
        try {
            $secret = Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return $this->reply(['message' => 'Setup expired. Start again.'], 422);
        }
        $step = $totp->accepts($secret, $data['code']);
        if ($step === null) {
            return $this->reply(['message' => 'Invalid authenticator code.'], 422);
        }

        $recovery = $totp->createRecoveryCodes();
        DB::transaction(function () use ($user, $secret, $step, $recovery, $totp): void {
            $record = User::query()->lockForUpdate()->findOrFail($user->getKey());
            abort_if(! empty($record->mfa_secret), 409, 'MFA already enabled.');
            $record->forceFill([
                'mfa_secret' => Crypt::encryptString($secret),
                'mfa_last_used_step' => $step,
                'mfa_recovery_codes' => $totp->hashRecoveryCodes($recovery),
                'mfa_enabled_at' => now(),
            ])->save();
        });
        Cache::forget($key);
        $newToken = $this->rotateToken($user);

        return $this->reply([
            'message' => 'Authenticator enabled. Other mobile sessions were signed out.',
            'enabled' => true,
            'access_token' => $newToken,
            'recovery_codes' => $recovery,
        ]);
    }

    public function regenerate(Request $request, AdminTotpService $totp): JsonResponse
    {
        $user = $this->account($request);
        abort_unless(! empty($user->mfa_secret), 409, 'Enable MFA first.');
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:32'],
        ]);
        $this->requirePassword($user, $data['password']);
        if (! $totp->verifyAndConsume($user, $data['code'])) {
            return $this->reply(['message' => 'Invalid or previously used authenticator code.'], 422);
        }
        $recovery = $totp->createRecoveryCodes();
        $user->forceFill(['mfa_recovery_codes' => $totp->hashRecoveryCodes($recovery)])->save();
        return $this->reply([
            'message' => 'New recovery codes generated. Previous recovery codes are invalid.',
            'recovery_codes' => $recovery,
            'recovery_codes_remaining' => count($recovery),
        ]);
    }

    public function disable(Request $request, AdminTotpService $totp): JsonResponse
    {
        $user = $this->account($request);
        abort_unless(! empty($user->mfa_secret), 409, 'MFA is not enabled.');
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:32'],
        ]);
        $this->requirePassword($user, $data['password']);
        if (! $totp->verifyAndConsume($user, $data['code'])) {
            return $this->reply(['message' => 'Invalid or previously used authenticator code.'], 422);
        }
        $user->forceFill([
            'mfa_secret' => null,
            'mfa_last_used_step' => null,
            'mfa_recovery_codes' => null,
            'mfa_enabled_at' => null,
        ])->save();
        $newToken = $this->rotateToken($user);
        return $this->reply([
            'message' => 'Authenticator disabled. Other mobile sessions were signed out.',
            'enabled' => false,
            'access_token' => $newToken,
        ]);
    }
}

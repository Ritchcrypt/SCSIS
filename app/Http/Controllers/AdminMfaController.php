<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MobilePushToken;
use App\Services\AdminTotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

final class AdminMfaController extends Controller
{
    private function account(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isActive(), 403);
        abort_unless(Schema::hasColumn('users', 'mfa_secret'), 503, 'MFA database migration is required.');
        return $user;
    }

    public function show(Request $request, AdminTotpService $totp): Response
    {
        $user = $this->account($request);
        $encryptedSecret = $request->session()->get('security.mfa_setup_secret');
        $secret = null;
        if (is_string($encryptedSecret)
            && (int) $request->session()->get('security.mfa_setup_expires_at', 0) >= time()) {
            try {
                $secret = Crypt::decryptString($encryptedSecret);
            } catch (\Throwable) {
                $secret = null;
            }
        }

        return response()->view('security.admin-mfa', [
            'enabled' => is_string($user->mfa_secret) && $user->mfa_secret !== '',
            'secret' => $secret,
            'uri' => $secret ? $totp->provisioningUri($user, $secret) : null,
            'recovery' => $request->session()->pull('security.mfa_new_recovery'),
            'remaining' => is_array(json_decode((string) $user->mfa_recovery_codes, true))
                ? count(json_decode((string) $user->mfa_recovery_codes, true)) : 0,
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    public function begin(Request $request, AdminTotpService $totp): RedirectResponse
    {
        $user = $this->account($request);
        abort_if(! empty($user->mfa_secret), 409, 'MFA already enabled.');
        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return back()->withErrors(['password' => 'Incorrect account password.']);
        }

        $secret = $totp->newSecret();
        $request->session()->put([
            'security.mfa_setup_secret' => Crypt::encryptString($secret),
            'security.mfa_setup_expires_at' => time() + 600,
        ]);
        return redirect()->route('security.account-mfa.show');
    }

    public function confirm(Request $request, AdminTotpService $totp): RedirectResponse
    {
        $user = $this->account($request);
        abort_if(! empty($user->mfa_secret), 409, 'MFA already enabled.');
        $request->validate(['code' => ['required', 'regex:/^\d{6}$/D']]);

        $encrypted = $request->session()->get('security.mfa_setup_secret');
        if (! is_string($encrypted)
            || (int) $request->session()->get('security.mfa_setup_expires_at', 0) < time()) {
            return back()->withErrors(['code' => 'Setup expired. Start again.']);
        }
        try {
            $secret = Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return back()->withErrors(['code' => 'Setup expired. Start again.']);
        }
        $acceptedStep = $totp->accepts($secret, (string) $request->input('code'));
        if ($acceptedStep === null) {
            return back()->withErrors(['code' => 'Invalid authenticator code.']);
        }

        $recovery = $totp->createRecoveryCodes();
        $user->forceFill([
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_last_used_step' => $acceptedStep,
            'mfa_recovery_codes' => $totp->hashRecoveryCodes($recovery),
            'mfa_enabled_at' => now(),
        ])->save();
        // Pre-MFA mobile tokens must no longer bypass second-factor verification.
        $user->tokens()->delete();
        // Revoked devices must not keep receiving account push notifications.
        MobilePushToken::query()->where('user_id', $user->id)->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $request->session()->forget(['security.mfa_setup_secret', 'security.mfa_setup_expires_at']);
        $request->session()->flash('security.mfa_new_recovery', $recovery);
        return redirect()->route('security.account-mfa.show')->with('status', 'Authenticator enabled. Save your recovery codes now. Other mobile sessions were signed out.');
    }

    public function regenerate(Request $request, AdminTotpService $totp): RedirectResponse
    {
        $user = $this->account($request);
        abort_unless(! empty($user->mfa_secret), 409, 'Enable MFA first.');
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:32'],
        ]);
        if (! Hash::check($validated['password'], (string) $user->password)
            || ! $totp->verifyAndConsume($user, $validated['code'])) {
            return back()->withErrors(['code' => 'Password or authenticator code is incorrect.']);
        }
        $recovery = $totp->createRecoveryCodes();
        $user->forceFill(['mfa_recovery_codes' => $totp->hashRecoveryCodes($recovery)])->save();
        $request->session()->flash('security.mfa_new_recovery', $recovery);
        return redirect()->route('security.account-mfa.show')
            ->with('status', 'New recovery codes generated. All previous recovery codes are invalid.');
    }

    public function disable(Request $request, AdminTotpService $totp): RedirectResponse
    {
        $user = $this->account($request);
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:32'],
        ]);
        if (! Hash::check($validated['password'], (string) $user->password)
            || ! $totp->verifyAndConsume($user, $validated['code'])) {
            return back()->withErrors(['code' => 'Password or authenticator code is incorrect.']);
        }

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_last_used_step' => null,
            'mfa_recovery_codes' => null,
            'mfa_enabled_at' => null,
        ])->save();
        $user->tokens()->delete();
        // Revoked devices must not keep receiving account push notifications.
        MobilePushToken::query()->where('user_id', $user->id)->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
        return redirect()->route('security.account-mfa.show')->with('status', 'Authenticator disabled.');
    }
}

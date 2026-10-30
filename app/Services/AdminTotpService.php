<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** RFC 6238 SHA-1 TOTP. Secrets are encrypted in the database. */
final class AdminTotpService
{
    public function newSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $label = 'TabangNow:'.(string) $user->email;

        return 'otpauth://totp/'.rawurlencode($label)
            .'?secret='.rawurlencode($secret)
            .'&issuer=TabangNow&algorithm=SHA1&digits=6&period=30';
    }

    /** Public for standards-vector regression checks. */
    public function codeAt(string $secret, int $unixSeconds): string
    {
        return $this->codeAtStep($secret, intdiv($unixSeconds, 30));
    }

    public function accepts(string $secret, string $code, ?int $lastStep = null): ?int
    {
        $code = trim($code);
        if (! preg_match('/^\d{6}$/D', $code)) {
            return null;
        }

        $currentStep = intdiv(time(), 30);
        // Permit small clock drift, reject replay of any previously used step.
        for ($step = $currentStep - 1; $step <= $currentStep + 1; $step++) {
            if ($step >= 0 && ($lastStep === null || $step > $lastStep)
                && hash_equals($this->codeAtStep($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /** Verify atomically so simultaneous requests cannot reuse the same OTP or backup code. */
    public function verifyAndConsume(User $user, string $input): bool
    {
        $input = strtoupper(preg_replace('/[\s-]+/', '', trim($input)) ?? '');
        if ($input === '' || strlen($input) > 32) {
            return false;
        }

        return DB::transaction(function () use ($user, $input): bool {
            $record = DB::table('users')->where('id', $user->id)->lockForUpdate()
                ->first(['mfa_secret', 'mfa_last_used_step', 'mfa_recovery_codes']);
            if (! $record || ! is_string($record->mfa_secret) || $record->mfa_secret === '') {
                return false;
            }

            try {
                $secret = Crypt::decryptString($record->mfa_secret);
            } catch (\Throwable) {
                return false; // Fail closed on missing or altered encryption keys.
            }

            $lastStep = $record->mfa_last_used_step === null
                ? null : (int) $record->mfa_last_used_step;
            $step = $this->accepts($secret, $input, $lastStep);
            if ($step !== null) {
                DB::table('users')->where('id', $user->id)
                    ->update(['mfa_last_used_step' => $step]);
                return true;
            }

            $hashed = json_decode((string) ($record->mfa_recovery_codes ?? ''), true);
            if (! is_array($hashed) || ! preg_match('/^[A-F0-9]{16}$/D', $input)) {
                return false;
            }

            foreach ($hashed as $index => $hash) {
                if (is_string($hash) && Hash::check($input, $hash)) {
                    unset($hashed[$index]);
                    DB::table('users')->where('id', $user->id)
                        ->update(['mfa_recovery_codes' => json_encode(array_values($hashed))]);
                    return true;
                }
            }

            return false;
        }, 3);
    }

    public function createRecoveryCodes(): array
    {
        return array_map(fn (): string => strtoupper(bin2hex(random_bytes(8))), range(1, 8));
    }

    public function hashRecoveryCodes(array $codes): string
    {
        return json_encode(array_map(fn (string $code): string => Hash::make($code), $codes), JSON_THROW_ON_ERROR);
    }

    private function codeAtStep(string $secret, int $step): string
    {
        $key = $this->base32Decode($secret);
        if ($key === '') {
            throw new RuntimeException('Invalid authenticator secret.');
        }

        $message = pack('N2', intdiv($step, 4294967296), $step % 4294967296);
        $hash = hash_hmac('sha1', $message, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = (ord($hash[$offset]) & 0x7f) << 24
            | (ord($hash[$offset + 1]) & 0xff) << 16
            | (ord($hash[$offset + 2]) & 0xff) << 8
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $raw): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($raw) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split($bits, 5) as $part) {
            $result .= $alphabet[bindec(str_pad($part, 5, '0', STR_PAD_RIGHT))];
        }
        return $result;
    }

    private function base32Decode(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = strtoupper(rtrim($encoded, '='));
        if ($encoded === '' || strspn($encoded, $alphabet) !== strlen($encoded)) {
            return '';
        }
        $bits = '';
        foreach (str_split($encoded) as $char) {
            $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split($bits, 8) as $part) {
            if (strlen($part) === 8) {
                $result .= chr(bindec($part));
            }
        }
        return $result;
    }
}

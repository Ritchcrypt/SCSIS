<?php

namespace App\Services;

use App\Models\MobilePushToken;
use App\Models\UserNotification;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class FirebasePushService
{
    private const MESSAGING_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        return (bool) config('services.firebase.push_enabled', false)
            && trim((string) config('services.firebase.project_id')) !== ''
            && trim((string) config('services.firebase.service_account_json_base64')) !== '';
    }

    public function sendForNotification(UserNotification $notification): void
    {
        if (! $this->isConfigured() || empty($notification->user_id)) {
            return;
        }

        $tokens = MobilePushToken::query()
            ->active()
            ->where('user_id', (int) $notification->user_id)
            ->orderByDesc('last_seen_at')
            ->get();

        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $credentials = $this->credentials();
            $projectId = trim((string) config('services.firebase.project_id'));
            $accessToken = $this->accessToken($credentials);
        } catch (Throwable $exception) {
            Log::warning('Firebase push authentication failed.', [
                'notification_id' => (int) $notification->id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($tokens as $pushToken) {
            try {
                $response = $this->sendToToken(
                    $projectId,
                    $accessToken,
                    $pushToken,
                    $notification
                );

                if ($response->status() === 401) {
                    $this->forgetAccessToken($credentials);
                    $accessToken = $this->accessToken($credentials);
                    $response = $this->sendToToken(
                        $projectId,
                        $accessToken,
                        $pushToken,
                        $notification
                    );
                }

                if ($response->successful()) {
                    $pushToken->forceFill(['last_seen_at' => now()])->save();
                    continue;
                }

                if ($this->isUnregisteredResponse($response)) {
                    $pushToken->forceFill(['revoked_at' => now()])->save();
                    continue;
                }

                Log::warning('Firebase push delivery failed.', [
                    'notification_id' => (int) $notification->id,
                    'push_token_id' => (int) $pushToken->id,
                    'status' => $response->status(),
                    'firebase_status' => data_get($response->json(), 'error.status'),
                ]);
            } catch (Throwable $exception) {
                Log::warning('Firebase push delivery raised an exception.', [
                    'notification_id' => (int) $notification->id,
                    'push_token_id' => (int) $pushToken->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function sendToToken(
        string $projectId,
        string $accessToken,
        MobilePushToken $pushToken,
        UserNotification $notification
    ): Response {
        $type = strtolower(trim((string) ($notification->type ?? 'notification')));
        $urgent = $type === 'mobile_emergency' || $type === 'emergency';

        $highPriority = $urgent || in_array($type, [
            'incident',
            'incident_reported',
            'incident_update',
            'incident_message',
            'incident_updated',
            'incident_status_update',
            'status_update',
            'assigned_incident',
            'incident_assigned',
            'new_assigned_incident',
            'dispatch',
            'escalation',
            'resolved',
            'mobile_emergency',
            'mobile_emergency_received',
            'mobile_emergency_acknowledged',
            'mobile_emergency_resolved',
            'tanod_alert',
            'tanod_task',
            'tanod_task_assigned',
            'tanod_task_update',
            'task_assigned',
            'task_update',
            'case_created',
            'case_updated',
            'case_deleted',
            'announcement',
            'calamity',
            'resident_complaint',
            'resident_complaint_update',
            'resident_complaint_status_update',
            'resident_complaint_proof',
            'account_activated',
            'account_deactivated',
        ], true);

        $title = trim((string) ($notification->title ?: 'TabangNow notification'));
        $body = trim((string) ($notification->message ?: $notification->title ?: 'You have a new notification.'));

        return Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(12)
            ->post(
                'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send',
                [
                    'message' => [
                        'token' => (string) $pushToken->fcm_token,
                        'notification' => [
                            'title' => Str::limit($title, 120, ''),
                            'body' => Str::limit($body, 900, ''),
                        ],
                        'data' => [
                            'notification_id' => (string) $notification->id,
                            'type' => $type,
                            'source_id' => $notification->source_id !== null
                                ? (string) $notification->source_id
                                : '',
                        ],
                        'android' => [
                            'priority' => $highPriority ? 'high' : 'normal',
                            'ttl' => $urgent ? '900s' : '86400s',
                            'notification' => [
                                'channel_id' => 'tabangnow_notifications_v3',
                                'sound' => 'tabangnow_notification',
                            ],
                        ],
                    ],
                ]
            );
    }

    private function isUnregisteredResponse(Response $response): bool
    {
        $details = data_get($response->json(), 'error.details', []);

        if (! is_array($details)) {
            return false;
        }

        foreach ($details as $detail) {
            if (
                is_array($detail)
                && strtoupper((string) ($detail['errorCode'] ?? '')) === 'UNREGISTERED'
            ) {
                return true;
            }
        }

        return false;
    }

    private function credentials(): array
    {
        $encoded = trim((string) config('services.firebase.service_account_json_base64'));
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || trim($decoded) === '') {
            throw new RuntimeException('Firebase service account Base64 is invalid.');
        }

        try {
            $credentials = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Firebase service account JSON is invalid.', 0, $exception);
        }

        if (! is_array($credentials)) {
            throw new RuntimeException('Firebase service account JSON must decode to an object.');
        }

        foreach (['client_email', 'private_key'] as $requiredKey) {
            if (trim((string) ($credentials[$requiredKey] ?? '')) === '') {
                throw new RuntimeException("Firebase service account is missing {$requiredKey}.");
            }
        }

        return $credentials;
    }

    private function accessToken(array $credentials): string
    {
        $cacheKey = $this->accessTokenCacheKey($credentials);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(50),
            fn (): string => $this->fetchAccessToken($credentials)
        );
    }

    private function forgetAccessToken(array $credentials): void
    {
        Cache::forget($this->accessTokenCacheKey($credentials));
    }

    private function accessTokenCacheKey(array $credentials): string
    {
        return 'firebase:fcm:oauth:' . sha1((string) $credentials['client_email']);
    }

    private function fetchAccessToken(array $credentials): string
    {
        $tokenUri = trim((string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token'));
        $now = time();

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => (string) $credentials['client_email'],
            'scope' => self::MESSAGING_SCOPE,
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $unsignedJwt = $header . '.' . $claims;
        $signature = '';
        $privateKey = openssl_pkey_get_private((string) $credentials['private_key']);

        if ($privateKey === false) {
            throw new RuntimeException('Firebase private key could not be loaded.');
        }

        if (! openssl_sign($unsignedJwt, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Firebase OAuth JWT signing failed.');
        }

        $assertion = $unsignedJwt . '.' . $this->base64UrlEncode($signature);

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(12)
            ->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Firebase OAuth token request failed with HTTP ' . $response->status() . '.'
            );
        }

        $accessToken = trim((string) data_get($response->json(), 'access_token'));

        if ($accessToken === '') {
            throw new RuntimeException('Firebase OAuth response did not include an access token.');
        }

        return $accessToken;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

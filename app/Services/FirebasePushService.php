<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Incident;
use App\Models\MobilePushToken;
use App\Models\TanodTask;
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

        $alertTier = $this->resolveAlertTier($notification);

        foreach ($tokens as $pushToken) {
            try {
                $response = $this->sendToToken(
                    $projectId,
                    $accessToken,
                    $pushToken,
                    $notification,
                    $alertTier
                );

                if ($response->status() === 401) {
                    $this->forgetAccessToken($credentials);
                    $accessToken = $this->accessToken($credentials);
                    $response = $this->sendToToken(
                        $projectId,
                        $accessToken,
                        $pushToken,
                        $notification,
                        $alertTier
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
        UserNotification $notification,
        string $alertTier
    ): Response {
        $type = strtolower(trim((string) ($notification->type ?? 'notification')));
        $highPriority = in_array($alertTier, ['critical', 'moderate'], true);

        $title = trim((string) ($notification->title ?: 'TabangNow notification'));
        $body = trim((string) ($notification->message ?: $notification->title ?: 'You have a new notification.'));

        $ttl = match ($alertTier) {
            'critical' => '900s',
            'moderate' => '3600s',
            default => '86400s',
        };

        return Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(12)
            ->post(
                'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send',
                [
                    'message' => [
                        'token' => (string) $pushToken->fcm_token,
                        'data' => [
                            'notification_id' => (string) $notification->id,
                            'type' => $type,
                            'source_id' => $notification->source_id !== null
                                ? (string) $notification->source_id
                                : '',
                            'alert_tier' => $alertTier,
                            'title' => Str::limit($title, 120, ''),
                            'body' => Str::limit($body, 900, ''),
                        ],
                        'android' => [
                            'priority' => $highPriority ? 'high' : 'normal',
                            'ttl' => $ttl,
                        ],
                    ],
                ]
            );
    }

    private function resolveAlertTier(UserNotification $notification): string
    {
        $type = strtolower(trim((string) ($notification->type ?? 'notification')));
        $sourceId = $notification->source_id !== null
            ? (int) $notification->source_id
            : null;

        if (in_array($type, [
            'mobile_emergency',
            'emergency',
            'calamity',
        ], true)) {
            return 'critical';
        }

        if (in_array($type, [
            'case_created',
            'case_updated',
            'case_deleted',
            'system',
        ], true)) {
            return 'silent';
        }

        if ($type === 'announcement') {
            return $this->announcementAlertTier($sourceId);
        }

        if (in_array($type, [
            'incident',
            'incident_reported',
        ], true)) {
            return $this->reportedIncidentAlertTier($sourceId);
        }

        if (in_array($type, [
            'assigned_incident',
            'incident_assigned',
            'new_assigned_incident',
            'dispatch',
            'escalation',
        ], true)) {
            return $this->incidentActionAlertTier($sourceId);
        }

        if (in_array($type, [
            'tanod_task',
            'tanod_task_assigned',
            'task_assigned',
        ], true)) {
            return 'critical';
        }

        // Incident conversation messages use the existing Flutter Code Red
        // channel. Keep the API alert_tier aligned with that established policy.
        if ($type === 'incident_message') {
            return 'critical';
        }

        if (in_array($type, [
            'user_registration',
            'resident_complaint',
            'incident_message',
            'tanod_alert',
            'community_problem',
            'account_deactivated',
            'mobile_emergency_acknowledged',
        ], true)) {
            return 'moderate';
        }

        if (in_array($type, [
            'incident_update',
            'incident_updated',
            'incident_status_update',
            'status_update',
            'resolved',
            'mobile_emergency_received',
            'mobile_emergency_resolved',
            'resident_complaint_update',
            'resident_complaint_status_update',
            'resident_complaint_proof',
            'resident_complaint_deleted',
            'account_activated',
            'tanod_task_update',
            'tanod_task_response',
            'task_update',
            'community',
        ], true)) {
            return 'low';
        }

        /*
         * Unknown future notification types default to LOW instead of CRITICAL.
         * This prevents a new informational workflow from accidentally
         * triggering the persistent Code Red alarm.
         */
        return 'low';
    }

    private function reportedIncidentAlertTier(?int $sourceId): string
    {
        $priority = $this->incidentPriority($sourceId);

        return match ($priority) {
            'critical', 'urgent', 'emergency' => 'critical',
            'low' => 'low',
            default => 'moderate',
        };
    }

    private function incidentActionAlertTier(?int $sourceId): string
    {
        $priority = $this->incidentPriority($sourceId);

        return in_array($priority, ['critical', 'urgent', 'emergency'], true)
            ? 'critical'
            : 'moderate';
    }

    private function incidentPriority(?int $sourceId): string
    {
        if (! $sourceId) {
            return '';
        }

        try {
            return strtolower(trim((string) Incident::query()
                ->whereKey($sourceId)
                ->value('priority')));
        } catch (Throwable $exception) {
            Log::debug('Notification incident tier lookup failed.', [
                'source_id' => $sourceId,
                'error' => $exception->getMessage(),
            ]);

            return '';
        }
    }

    private function tanodTaskAlertTier(?int $sourceId): string
    {
        if (! $sourceId) {
            return 'moderate';
        }

        try {
            $task = TanodTask::query()
                ->whereKey($sourceId)
                ->first(['priority', 'incident_id']);

            if (! $task) {
                return 'moderate';
            }

            if (
                $task->incident_id
                && in_array(
                    $this->incidentPriority((int) $task->incident_id),
                    ['critical', 'urgent', 'emergency'],
                    true
                )
            ) {
                return 'critical';
            }

            return strtolower(trim((string) $task->priority)) === 'low'
                ? 'low'
                : 'moderate';
        } catch (Throwable $exception) {
            Log::debug('Notification tanod task tier lookup failed.', [
                'source_id' => $sourceId,
                'error' => $exception->getMessage(),
            ]);

            return 'moderate';
        }
    }

    private function announcementAlertTier(?int $sourceId): string
    {
        if (! $sourceId) {
            return 'low';
        }

        try {
            $announcement = Announcement::query()
                ->whereKey($sourceId)
                ->first([
                    'category',
                    'priority',
                    'activate_calamity_mode',
                ]);

            if (! $announcement) {
                return 'low';
            }

            $category = strtolower(trim((string) $announcement->category));
            $priority = strtolower(trim((string) $announcement->priority));

            if (
                (bool) $announcement->activate_calamity_mode
                || in_array($category, ['emergency', 'calamity'], true)
                || $priority === 'emergency'
            ) {
                return 'critical';
            }

            if (in_array($priority, ['important', 'urgent'], true)) {
                return 'moderate';
            }

            return 'low';
        } catch (Throwable $exception) {
            Log::debug('Notification announcement tier lookup failed.', [
                'source_id' => $sourceId,
                'error' => $exception->getMessage(),
            ]);

            return 'low';
        }
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

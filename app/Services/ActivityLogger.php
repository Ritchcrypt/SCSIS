<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ActivityLogger
{
    private const MAX_METADATA_DEPTH = 4;
    private const MAX_METADATA_STRING_LENGTH = 1000;

    /**
     * Write one security or system activity record.
     *
     * Logging failures are reported but must not break authentication or
     * another primary application operation.
     */
    public function record(
        string $event,
        string $category,
        string $description,
        ?User $actor = null,
        ?User $target = null,
        array $metadata = [],
        ?Request $request = null
    ): ?ActivityLog {
        try {
            if (! Schema::hasTable('activity_logs')) {
                return null;
            }

            $request ??= $this->currentRequest();

            return ActivityLog::query()->create([
                'actor_id' => $actor?->id,
                'actor_name' => $this->snapshotName($actor),
                'actor_role' => $this->snapshotRole($actor),
                'target_user_id' => $target?->id,
                'target_name' => $this->snapshotName($target),
                'target_role' => $this->snapshotRole($target),
                'event' => Str::limit(
                    trim($event),
                    100,
                    ''
                ),
                'category' => Str::limit(
                    trim($category),
                    50,
                    ''
                ),
                'description' => Str::limit(
                    trim($description),
                    500,
                    ''
                ),
                'route_name' => $this->routeName($request),
                'request_method' => $request
                    ? Str::limit($request->method(), 10, '')
                    : null,
                'ip_address' => $request
                    ? Str::limit((string) $request->ip(), 45, '')
                    : null,
                'user_agent' => $request
                    ? Str::limit(
                        (string) $request->userAgent(),
                        500,
                        ''
                    )
                    : null,
                'metadata' => $this->sanitizeMetadata($metadata),
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Convert an identifier into a non-reversible audit correlation value.
     */
    public function hashIdentifier(mixed $identifier): ?string
    {
        if (! is_scalar($identifier)) {
            return null;
        }

        $identifier = Str::lower(
            trim((string) $identifier)
        );

        if ($identifier === '') {
            return null;
        }

        return hash('sha256', $identifier);
    }

    private function currentRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        return $request instanceof Request
            ? $request
            : null;
    }

    private function routeName(?Request $request): ?string
    {
        if (! $request) {
            return null;
        }

        $route = $request->route();

        if (! is_object($route) || ! method_exists($route, 'getName')) {
            return null;
        }

        $name = $route->getName();

        return is_string($name)
            ? Str::limit($name, 150, '')
            : null;
    }

    private function snapshotName(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return Str::limit(
            trim((string) $user->name),
            255,
            ''
        );
    }

    private function snapshotRole(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return Str::limit(
            trim((string) $user->role),
            50,
            ''
        );
    }

    private function sanitizeMetadata(
        array $metadata,
        int $depth = 0
    ): array {
        if ($depth >= self::MAX_METADATA_DEPTH) {
            return [
                'truncated' => true,
            ];
        }

        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $safeKey = is_string($key)
                ? Str::limit($key, 100, '')
                : $key;

            if (
                is_string($safeKey)
                && $this->isSensitiveKey($safeKey)
            ) {
                $sanitized[$safeKey] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$safeKey] = $this->sanitizeMetadata(
                    $value,
                    $depth + 1
                );

                continue;
            }

            if (is_bool($value) || $value === null) {
                $sanitized[$safeKey] = $value;

                continue;
            }

            if (is_int($value) || is_float($value)) {
                $sanitized[$safeKey] = $value;

                continue;
            }

            if (is_scalar($value)) {
                $sanitized[$safeKey] = Str::limit(
                    (string) $value,
                    self::MAX_METADATA_STRING_LENGTH,
                    ''
                );

                continue;
            }

            $sanitized[$safeKey] = '[UNSUPPORTED VALUE]';
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = Str::lower(
            str_replace(
                [
                    '-',
                    '.',
                    ' ',
                ],
                '_',
                $key
            )
        );

        return in_array(
            $normalized,
            [
                'password',
                'password_confirmation',
                'current_password',
                'new_password',
                'token',
                'reset_token',
                'remember_token',
                'authorization',
                'cookie',
                'cookies',
                'secret',
                'api_key',
                'private_key',
            ],
            true
        );
    }
}

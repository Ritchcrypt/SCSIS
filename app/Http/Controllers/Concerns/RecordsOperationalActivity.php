<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

trait RecordsOperationalActivity
{
    /**
     * Record one successful operational action without interrupting the
     * controller operation when audit storage is temporarily unavailable.
     */
    protected function recordOperationalActivity(
        string $event,
        string $category,
        string $description,
        array $metadata = [],
        ?Request $request = null
    ): void {
        $request ??= $this->currentOperationalRequest();

        $actor = $request?->user();

        app(ActivityLogger::class)->record(
            event: $event,
            category: $category,
            description: $description,
            actor: $actor instanceof User ? $actor : null,
            metadata: $metadata,
            request: $request,
        );
    }

    private function currentOperationalRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        return $request instanceof Request
            ? $request
            : null;
    }
}

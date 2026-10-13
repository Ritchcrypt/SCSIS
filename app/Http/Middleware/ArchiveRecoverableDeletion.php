<?php

namespace App\Http\Middleware;

use App\Services\DeletedDataArchiveService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ArchiveRecoverableDeletion
{
    public function __construct(
        private readonly DeletedDataArchiveService $archives
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $archiveIds = $this->archives->captureForRequest($request);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->archives->discardIfOriginalStillExists($archiveIds);

            throw $exception;
        }

        /*
         * Always reconcile provisional archives after a completed request.
         * A real deletion leaves the original missing, so its archive remains.
         * A successful no-op or rejected deletion leaves the original present,
         * so the provisional archive is discarded instead of becoming bogus
         * recovery data.
         */
        $this->archives->discardIfOriginalStillExists($archiveIds);

        return $response;
    }
}

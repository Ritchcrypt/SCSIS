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

        if ($response->getStatusCode() >= 400) {
            $this->archives->discardIfOriginalStillExists($archiveIds);
        }

        return $response;
    }
}

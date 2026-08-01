<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventAuthenticatedPageCaching
{
    /**
     * Prevent authenticated HTML pages from remaining available through
     * browser Back/Forward history after logout or session expiration.
     *
     * Private files, images, PDFs, downloads, JSON responses, and streamed
     * responses retain their own deliberately configured cache headers.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $response = $next($request);

        $contentType = strtolower(
            (string) $response->headers->get(
                'Content-Type',
                ''
            )
        );

        $isAuthenticatedHtmlPage =
            $request->user() !== null
            && str_contains(
                $contentType,
                'text/html'
            );

        if (! $isAuthenticatedHtmlPage) {
            return $response;
        }

        $response->headers->set(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, private, max-age=0'
        );

        $response->headers->set(
            'Pragma',
            'no-cache'
        );

        $response->headers->set(
            'Expires',
            'Fri, 01 Jan 1990 00:00:00 GMT'
        );

        $response->headers->remove('ETag');
        $response->headers->remove('Last-Modified');

        return $response;
    }
}
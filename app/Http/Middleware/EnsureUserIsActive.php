<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (
            $user
            && Schema::hasColumn('users', 'is_active')
            && ! (bool) $user->is_active
        ) {
            /*
            |--------------------------------------------------------------------------
            | Preserve the security reason for the logout audit event
            |--------------------------------------------------------------------------
            */

            $request->session()->put(
                'security.logout_reason',
                'account_inactive'
            );

            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Your account is inactive. Please contact the administrator.',
                ]);
        }

        return $next($request);
    }
}

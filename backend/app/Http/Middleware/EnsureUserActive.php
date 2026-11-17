<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended or deactivated user is refused even with a valid token (Section 6).
 * Pending users pass so they can change their temporary password.
 */
class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if (! $user->status->canSignIn()) {
                throw ApiException::accountDisabled();
            }
            if ($user->isLocked()) {
                throw ApiException::accountLocked($user->locked_until->toIso8601String());
            }
        }

        return $next($request);
    }
}

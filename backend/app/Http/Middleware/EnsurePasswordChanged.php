<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users with a temporary password may only reach /auth/me, /auth/logout and
 * /auth/change-password until they set their own password.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            throw ApiException::passwordChangeRequired();
        }

        return $next($request);
    }
}

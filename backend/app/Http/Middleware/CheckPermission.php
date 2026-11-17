<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\Access\PermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: `->middleware('permission:user.view')`.
 * Every endpoint is protected by exactly one permission code (Section 4).
 * Row-level scope rules are applied afterwards in services / policies.
 */
class CheckPermission
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new ApiException('Unauthenticated.', 'UNAUTHENTICATED', 401);
        }

        // "a|b" = any one of these codes (used by list endpoints shared by
        // view_own / view_department / view_all style permission families).
        foreach (explode('|', $permission) as $code) {
            $decision = $this->resolver->explain($user, $code);
            if ($decision['allowed']) {
                return $next($request);
            }
        }

        if (! $decision['allowed']) {
            $message = match ($decision['reason']) {
                'module_disabled' => "The module for '{$permission}' is switched off.",
                'permission_disabled' => "The '{$permission}' permission is switched off for everyone.",
                'denied_by_override' => "The '{$permission}' permission has been turned off for your account.",
                default => "You do not have the '{$permission}' permission.",
            };

            throw new ApiException($message, 'PERMISSION_DENIED', 403);
        }

        return $next($request);
    }
}

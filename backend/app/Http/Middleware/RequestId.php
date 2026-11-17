<?php

namespace App\Http\Middleware;

use App\Support\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns a ULID to each request (or accepts a valid incoming X-Request-Id)
 * and echoes it back so logs, audit rows and responses can be correlated.
 */
class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Request-Id');
        $id = is_string($incoming) && Str::isUlid($incoming) ? strtoupper($incoming) : (string) Str::ulid();

        RequestContext::set($id);
        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}

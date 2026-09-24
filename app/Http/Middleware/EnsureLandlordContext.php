<?php

namespace App\Http\Middleware;

use App\Models\Institution;
use App\Responser\JsonResponser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Restricts a route to NHEF administrators: rejects any request running inside an institution's context. */
class EnsureLandlordContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Institution::checkCurrent()) {
            return JsonResponser::send(true, 'This action is restricted to NHEF administrators.', null, 403);
        }

        return $next($request);
    }
}

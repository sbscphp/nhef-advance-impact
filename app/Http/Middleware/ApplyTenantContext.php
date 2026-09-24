<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\Institution;
use App\Responser\JsonResponser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the authenticated admin's institution the current tenant so tenant-scoped models
 * are restricted to it. NHEF admins (no institution) and customers run with no tenant.
 * The tenant comes only from the authenticated admin, never from a client-supplied header.
 */
class ApplyTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->bearerToken() !== null ? $request->user('sanctum') : null;

        if (! $actor instanceof Admin || $actor->institution_id === null) {
            return $next($request);
        }

        $institution = Institution::query()->find($actor->institution_id);

        if (! $institution instanceof Institution || ! $institution->is_active) {
            return JsonResponser::send(true, 'Access for this institution has been revoked.', null, 403);
        }

        $institution->makeCurrent();

        try {
            return $next($request);
        } finally {
            Institution::forgetCurrent();
        }
    }
}

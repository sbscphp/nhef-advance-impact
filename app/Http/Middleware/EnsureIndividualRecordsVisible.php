<?php

namespace App\Http\Middleware;

use App\Responser\JsonResponser;
use App\Support\ViewerVisibility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks screens that list individual donors, alumni or transactions for viewers limited to institution-level summaries. */
class EnsureIndividualRecordsVisible
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ViewerVisibility::canSeeIndividualRecords()) {
            return JsonResponser::send(true, 'Individual-level records are not available to your account.', null, 403);
        }

        return $next($request);
    }
}

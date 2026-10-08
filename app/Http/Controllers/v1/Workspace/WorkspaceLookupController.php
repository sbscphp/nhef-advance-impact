<?php

namespace App\Http\Controllers\v1\Workspace;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Responser\JsonResponser;

class WorkspaceLookupController extends Controller
{
    /** Public: branding an institution's login/landing page needs before anyone signs in. */
    public function show(string $slug)
    {
        try {
            $institution = Institution::query()
                ->with('theme')
                ->where('slug', strtolower($slug))
                ->where('is_active', true)
                ->first();

            if (! $institution instanceof Institution) {
                return JsonResponser::send(true, 'Workspace not found.', null, 404);
            }

            return JsonResponser::send(false, 'Workspace retrieved.', $institution->workspaceData());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Workspace\WorkspaceLookupController@show');
        }
    }
}

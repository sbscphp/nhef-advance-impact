<?php

namespace App\Http\Controllers\v1\Admin\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Institutions\CreateInstitutionRequest;
use App\Http\Requests\Admin\Institutions\InstitutionListRequest;
use App\Http\Resources\Admin\InstitutionResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Fundraising\InstitutionService;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    public function __construct(private readonly InstitutionService $institutionService) {}

    /**
     * List institutions
     *
     * Returns every institution, for populating the "Institution" dropdown on the National
     * Giving Day campaign wizard. Pass `active_only=false` to include inactive ones.
     *
     * @group Institutions
     *
     * @queryParam active_only boolean Whether to only return active institutions. Defaults to true. Example: true
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Institutions retrieved.",
     *   "data": [
     *     {"uuid": "0f2a1e2e-2c3b-4a3e-9c3d-6e3a3f1b2c4d", "name": "University of Lagos", "is_active": true}
     *   ]
     * }
     */
    public function index(InstitutionListRequest $request)
    {
        try {
            $activeOnly = $request->boolean('active_only', true);
            $institutions = $this->institutionService->list($activeOnly);

            return JsonResponser::send(false, 'Institutions retrieved.', InstitutionResource::collection($institutions)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\InstitutionController@index');
        }
    }

    /**
     * Add an institution
     *
     * Adds a new institution to the shared lookup list, so it becomes selectable on the
     * National Giving Day campaign wizard.
     *
     * @group Institutions
     *
     * @bodyParam name string required The institution's name. Must be unique. Example: University of Abuja
     *
     * @response 200 {
     *   "error": false,
     *   "message": "Institution added.",
     *   "data": {"uuid": "0f2a1e2e-2c3b-4a3e-9c3d-6e3a3f1b2c4d", "name": "University of Abuja", "is_active": true}
     * }
     */
    public function store(CreateInstitutionRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $institution = $this->institutionService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Institution added.', InstitutionResource::make($institution)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\InstitutionController@store');
        }
    }

    private function requireAdmin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            abort(403, 'Forbidden.');
        }

        return $admin;
    }
}

<?php

namespace App\Http\Controllers\v1\Admin\ConstituentManagement;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConstituentManagement\ConstituentDonationListRequest;
use App\Http\Requests\Admin\ConstituentManagement\ConstituentListRequest;
use App\Http\Requests\Admin\ConstituentManagement\InviteConstituentRequest;
use App\Http\Requests\Admin\ConstituentManagement\UpdateConstituentRequest;
use App\Http\Requests\Admin\DateRangeStatsRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Http\Resources\Admin\ConstituentManagement\ConstituentAdminResource;
use App\Http\Resources\Admin\ConstituentManagement\ConstituentDetailResource;
use App\Http\Resources\Fundraising\DonationResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\ConstituentManagement\AdminConstituentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ConstituentController extends Controller
{
    public function __construct(private readonly AdminConstituentService $constituentService) {}

    public function store(InviteConstituentRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $user = $this->constituentService->invite($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Constituent invited successfully.', ConstituentDetailResource::make($user)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@store');
        }
    }

    public function index(ConstituentListRequest $request)
    {
        try {
            $paginator = $this->constituentService->paginateForAdmin($request->validated());

            return JsonResponser::send(false, 'Constituents retrieved.', $this->paginatedPayload($paginator, ConstituentAdminResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@index');
        }
    }

    public function overview(DateRangeStatsRequest $request)
    {
        try {
            $window = ListingFilterRules::resolveDateWindow($request->validated());
            $overview = $this->constituentService->overview($window['start'], $window['end']);

            return JsonResponser::send(false, 'Constituent overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@overview');
        }
    }

    public function show(string $uuid)
    {
        try {
            $user = $this->constituentService->findForAdmin($uuid);

            return JsonResponser::send(false, 'Constituent retrieved.', ConstituentDetailResource::make($user)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@show');
        }
    }

    public function update(UpdateConstituentRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $user = $this->constituentService->update($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Constituent updated.', ConstituentDetailResource::make($user)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@update');
        }
    }

    public function revoke(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $user = $this->constituentService->revokeAccess($uuid, $admin, $request);

            return JsonResponser::send(false, 'Constituent access revoked.', ConstituentDetailResource::make($user)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@revoke');
        }
    }

    public function reactivate(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $user = $this->constituentService->reactivateAccess($uuid, $admin, $request);

            return JsonResponser::send(false, 'Constituent access reactivated.', ConstituentDetailResource::make($user)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@reactivate');
        }
    }

    public function resendInvite(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $user = $this->constituentService->resendInvite($uuid, $admin, $request);

            return JsonResponser::send(false, 'Onboarding invite resent.', ConstituentDetailResource::make($user)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@resendInvite');
        }
    }

    public function donations(ConstituentDonationListRequest $request, string $uuid)
    {
        try {
            $user = $this->constituentService->findForAdmin($uuid);
            $paginator = $this->constituentService->paginateDonations($user, $request->validated());

            return JsonResponser::send(false, 'Constituent donations retrieved.', $this->paginatedPayload($paginator, DonationResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@donations');
        }
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        $payload = $paginator->toArray();
        /** @var AnonymousResourceCollection $resource */
        $resource = $resourceClass::collection($paginator);
        $payload['data'] = $resource->resolve();

        return $payload;
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

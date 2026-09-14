<?php

namespace App\Http\Controllers\v1\Admin\ConstituentManagement;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConstituentManagement\InstitutionAlumniListRequest;
use App\Http\Requests\Admin\ConstituentManagement\InstitutionCampaignListRequest;
use App\Http\Requests\Admin\ConstituentManagement\InstitutionListRequest;
use App\Http\Requests\Admin\ConstituentManagement\InviteInstitutionRequest;
use App\Http\Requests\Admin\ConstituentManagement\UpdateInstitutionRequest;
use App\Http\Requests\Admin\DateRangeStatsRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Http\Resources\Admin\ConstituentManagement\InstitutionAdminResource;
use App\Http\Resources\Admin\ConstituentManagement\InstitutionAlumniResource;
use App\Http\Resources\Admin\ConstituentManagement\InstitutionCampaignResource;
use App\Http\Resources\Admin\ConstituentManagement\InstitutionDetailResource;
use App\Models\Admin;
use App\Models\Institution;
use App\Responser\JsonResponser;
use App\Services\ConstituentManagement\AdminInstitutionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstitutionController extends Controller
{
    public function __construct(private readonly AdminInstitutionService $institutionService) {}

    public function store(InviteInstitutionRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $institution = $this->institutionService->invite($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Institution invited successfully.', InstitutionDetailResource::make($institution)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\InstitutionController@store');
        }
    }

    public function index(InstitutionListRequest $request)
    {
        try {
            $paginator = $this->institutionService->paginateForAdmin($request->validated());

            return JsonResponser::send(false, 'Institutions retrieved.', $this->paginatedPayload($paginator, InstitutionAdminResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\InstitutionController@index');
        }
    }

    public function overview(DateRangeStatsRequest $request)
    {
        try {
            $window = ListingFilterRules::resolveDateWindow($request->validated());
            $overview = $this->institutionService->overview($window['start'], $window['end']);

            return JsonResponser::send(false, 'Institution overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\InstitutionController@overview');
        }
    }

    public function show(string $uuid)
    {
        try {
            $institution = $this->institutionService->findForAdmin($uuid);

            return JsonResponser::send(false, 'Institution retrieved.', InstitutionDetailResource::make($institution)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\InstitutionController@show');
        }
    }

    public function update(UpdateInstitutionRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $institution = $this->institutionService->update($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Institution updated.', InstitutionDetailResource::make($institution)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\InstitutionController@update');
        }
    }

    public function revoke(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $institution = $this->institutionService->revokeAccess($uuid, $admin, $request);

            return JsonResponser::send(false, 'Institution access revoked.', InstitutionDetailResource::make($institution)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\InstitutionController@revoke');
        }
    }

    public function reactivate(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $institution = $this->institutionService->reactivateAccess($uuid, $admin, $request);

            return JsonResponser::send(false, 'Institution access reactivated.', InstitutionDetailResource::make($institution)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\InstitutionController@reactivate');
        }
    }

    public function resendInvite(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $institution = $this->institutionService->resendInvite($uuid, $admin, $request);

            return JsonResponser::send(false, 'Onboarding invite resent.', InstitutionDetailResource::make($institution)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\InstitutionController@resendInvite');
        }
    }

    public function alumni(InstitutionAlumniListRequest $request, string $uuid)
    {
        try {
            $institution = $this->institutionService->findForAdmin($uuid);

            if ($request->validated('export') === 'csv') {
                return $this->respondAlumniCsv($institution, $request->validated());
            }

            $paginator = $this->institutionService->paginateAlumni($institution, $request->validated());

            return JsonResponser::send(false, 'Institution alumni retrieved.', $this->paginatedPayload($paginator, InstitutionAlumniResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\InstitutionController@alumni');
        }
    }

    public function campaigns(InstitutionCampaignListRequest $request, string $uuid)
    {
        try {
            $institution = $this->institutionService->findForAdmin($uuid);
            $paginator = $this->institutionService->paginateCampaigns($institution, $request->validated());

            return JsonResponser::send(false, 'Institution campaigns retrieved.', $this->paginatedPayload($paginator, InstitutionCampaignResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\InstitutionController@campaigns');
        }
    }

    private function respondAlumniCsv(Institution $institution, array $filters): StreamedResponse
    {
        $filters['per_page'] = 5000;
        $paginator = $this->institutionService->paginateAlumni($institution, $filters);
        $filename = 'alumni-'.Str::slug($institution->name).'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($paginator): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Alumni Name', 'Email Address', 'No. of Donations', 'Department', 'Status', 'Last Active']);

            foreach ($paginator->items() as $user) {
                fputcsv($out, [
                    'NHEF-AD-'.strtoupper(substr($user->uuid, 0, 6)),
                    trim($user->firstname.' '.$user->lastname),
                    $user->email,
                    (int) ($user->donation_count ?? 0),
                    $user->department,
                    $user->is_active ? 'active' : 'inactive',
                    $user->last_active_at?->toIso8601String(),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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

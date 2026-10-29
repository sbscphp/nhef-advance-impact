<?php

namespace App\Http\Controllers\v1\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\CampaignDonorListRequest;
use App\Http\Requests\Campaigns\CampaignListRequest;
use App\Http\Resources\Fundraising\CampaignDonorResource;
use App\Http\Resources\Fundraising\CampaignResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Fundraising\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Public: campaigns are browsed by both logged-in alumni and guest donors, so nothing
 * here needs an account. When a donor is signed in (routes/public.php's auth.attempt
 * middleware resolves them without requiring auth), the listing scopes to their own institution.
 */
class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaignService) {}

    public function index(CampaignListRequest $request)
    {
        try {
            $viewer = $request->user() instanceof User ? $request->user() : null;
            $paginator = $this->campaignService->paginate($request->validated(), $viewer);

            return JsonResponser::send(false, 'Campaigns retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Fundraising\CampaignController@index');
        }
    }

    public function show(Request $request, string $uuid)
    {
        try {
            $campaign = $this->campaignService->findActiveByUuid($uuid);

            return JsonResponser::send(false, 'Campaign retrieved.', CampaignResource::make($campaign), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Fundraising\CampaignController@show');
        }
    }

    public function donors(CampaignDonorListRequest $request, string $uuid)
    {
        try {
            $campaign = $this->campaignService->findActiveByUuid($uuid);
            $perPage = (int) ($request->validated('per_page') ?? 10);
            $paginator = $this->campaignService->recentDonors($campaign, $perPage);

            return JsonResponser::send(false, 'Recent donors retrieved.', $this->donorsPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Fundraising\CampaignController@donors');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = CampaignResource::collection($paginator)->resolve();

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function donorsPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = CampaignDonorResource::collection($paginator)->resolve();

        return $payload;
    }
}

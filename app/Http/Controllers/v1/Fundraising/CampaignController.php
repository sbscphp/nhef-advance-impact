<?php

namespace App\Http\Controllers\v1\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\CampaignListRequest;
use App\Http\Resources\Fundraising\CampaignResource;
use App\Responser\JsonResponser;
use App\Services\Fundraising\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Public: campaigns are browsed by both logged-in alumni and guest donors, so nothing
 * here needs an account.
 */
class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaignService) {}

    public function index(CampaignListRequest $request)
    {
        try {
            $paginator = $this->campaignService->paginate($request->validated());

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

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = CampaignResource::collection($paginator)->resolve();

        return $payload;
    }
}

<?php

namespace App\Http\Controllers\v1\Fundraising;

use App\Enums\CampaignCategoryEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\CampaignListRequest;
use App\Http\Resources\Fundraising\CampaignResource;
use App\Responser\JsonResponser;
use App\Services\Fundraising\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * Public: campaigns are browsed by both logged-in alumni and guest donors (see
 * App\Http\Controllers\v1\Guest\Fundraising\GuestPledgeController), so there's nothing here
 * that needs an account.
 */
#[Group('Fundraising / Campaigns', 'Browse active fundraising campaigns available for donations and pledges (BRD FEM-02, FEM-03). Public — no account required.')]
class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaignService) {}

    /**
     * List campaigns
     *
     * Returns active campaigns open for donations, optionally filtered by category or title search.
     */
    #[Endpoint('List campaigns')]
    #[Unauthenticated]
    #[QueryParam('category', 'string', 'Filter by campaign category.', required: false, example: 'scholarship', enum: CampaignCategoryEnum::class)]
    #[QueryParam('search', 'string', 'Filter by title (partial match).', required: false, example: 'Coding Education')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Campaigns retrieved.',
        'data' => [
            'current_page' => 1,
            'data' => [],
            'per_page' => 15,
            'total' => 0,
        ],
    ], description: 'Paginated list of active campaigns.')]
    public function index(CampaignListRequest $request)
    {
        try {
            $paginator = $this->campaignService->paginate($request->validated());

            return JsonResponser::send(false, 'Campaigns retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Fundraising\CampaignController@index');
        }
    }

    /**
     * View campaign
     *
     * Returns a single active campaign's details, including funding progress.
     */
    #[Endpoint('View campaign')]
    #[Unauthenticated]
    #[UrlParam('uuid', 'string', 'Campaign UUID.', required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Campaign retrieved.',
        'data' => ['uuid' => 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7', 'title' => 'Stellar Tech - Coding Education', 'progress_percentage' => 60],
    ], description: 'Campaign found.')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Campaign not found.',
        'data' => null,
    ], description: 'No active campaign matches the given UUID.')]
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

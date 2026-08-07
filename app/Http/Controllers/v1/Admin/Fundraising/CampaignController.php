<?php

namespace App\Http\Controllers\v1\Admin\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaigns\CampaignDonationListRequest;
use App\Http\Requests\Admin\Campaigns\CampaignListRequest;
use App\Http\Requests\Admin\Campaigns\CampaignPledgeListRequest;
use App\Http\Requests\Admin\Campaigns\CreateCampaignRequest;
use App\Http\Requests\Admin\Campaigns\UpdateCampaignRequest;
use App\Http\Requests\Admin\DateRangeStatsRequest;
use App\Http\Resources\Admin\CampaignAdminResource;
use App\Http\Resources\Admin\CampaignDetailResource;
use App\Http\Resources\Admin\CampaignDonationResource;
use App\Http\Resources\Admin\CampaignPledgeResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Fundraising\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('Admin / Fundraising / Campaigns', 'Create fundraising campaigns from the admin "Add New Campaign" wizard (BRD FEM-03). Requires the `campaigns.create` permission.')]
class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaignService) {}

    #[Endpoint('Create a campaign', 'Backs the 3-step "Add New Campaign" wizard (Label/cover, Campaign Details, Bank), submitted as one multipart request once all steps are filled. Created campaigns go live immediately (`status: active`); there is no separate publish step from this screen.')]
    #[Authenticated]
    #[BodyParam('title', 'string', 'Campaign title.', required: true, example: "Fund a child's education today")]
    #[BodyParam('description', 'string', 'The campaign story; use a clear breakdown of how funds will be spent.', required: true, example: 'Fund coding bootcamp scholarships for underserved alumni and their dependents.')]
    #[BodyParam('goal_amount', 'number', 'Fundraising goal.', required: true, example: 1000000)]
    #[BodyParam('currency', 'string', 'Campaign currency.', required: true, example: 'NGN')]
    #[BodyParam('allocated_admin_id', 'string', 'Officer UUID this campaign is allocated to, from `GET /admin-users/dropdown`.', required: true, example: 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7')]
    #[BodyParam('bank_account_id', 'string', 'Remittance bank account UUID, from `GET /banks/accounts` or `POST /banks/accounts`.', required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[BodyParam('starts_at', 'string', 'Campaign start date.', required: true, example: '2026-08-01')]
    #[BodyParam('ends_at', 'string', 'Campaign end date.', required: false, example: '2027-02-01')]
    #[BodyParam('cover', 'file', 'Cover image (JPG, PNG, GIF, or WEBP; max 10MB).', required: true)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Campaign created successfully.',
        'data' => [
            'uuid' => 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7',
            'title' => "Fund a child's education today",
            'slug' => 'fund-a-childs-education-today',
            'status' => 'active',
            'share_url' => 'https://nhef.org/campaigns/fund-a-childs-education-today',
        ],
    ], description: 'Campaign created and live.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'The selected bank account does not exist.',
        'data' => null,
    ], description: 'Validation error.')]
    public function store(CreateCampaignRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaign = $this->campaignService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Campaign created successfully.', CampaignAdminResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@store');
        }
    }

    #[Endpoint('List campaigns', 'Paginated, searchable, sortable campaign listing for the "Campaign Management" screen; unlike the public listing this includes every status (draft/active/paused/completed/deactivated). Requires the `campaigns.read` permission.')]
    #[Authenticated]
    #[QueryParam('search', 'string', 'Matches campaign title.', required: false, example: 'Feed a Child')]
    #[QueryParam('filters[status]', 'string', 'Filter by status.', required: false, example: 'active')]
    #[QueryParam('sort_by', 'string', 'One of: name (title), value (goal_amount).', required: false, example: 'value')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'integer', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Rows per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Campaigns retrieved.',
        'data' => [
            'current_page' => 1,
            'data' => [],
            'per_page' => 15,
            'total' => 0,
        ],
    ], description: 'Standard Laravel paginator shape.')]
    public function index(CampaignListRequest $request)
    {
        try {
            $paginator = $this->campaignService->paginateForAdmin($request->validated());

            return JsonResponser::send(false, 'Campaigns retrieved.', $this->paginatedPayload($paginator, CampaignAdminResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@index');
        }
    }

    #[Endpoint('View a campaign', 'Loads the admin "View Campaign" screen (any status, unlike the public single-campaign endpoint which is active-only). Requires the `campaigns.read` permission.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', "The campaign's UUID.", required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Campaign retrieved.',
        'data' => [
            'uuid' => 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7',
            'title' => 'Feed a Child',
            'status' => 'active',
            'goal_amount' => '3500.00',
            'raised_amount' => '2000.00',
            'progress_percentage' => 60,
            'donors_count' => 128,
            'days_remaining' => 21,
            'creator' => ['admin_id' => 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7', 'name' => 'Adeola Craig'],
        ],
    ], description: 'Full campaign detail.')]
    public function show(string $uuid)
    {
        try {
            $campaign = $this->campaignService->findForAdmin($uuid);
            $campaign->setAttribute('donors_count', $this->campaignService->donorsCount($campaign));
            $campaign->setAttribute('days_remaining', $this->campaignService->daysRemaining($campaign));

            return JsonResponser::send(false, 'Campaign retrieved.', CampaignDetailResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@show');
        }
    }

    #[Endpoint('Edit a campaign', 'Backs "Edit Campaign" from the "Take Action" menu. Only send fields that changed. The slug (and therefore the share link) never changes, even if the title does. Requires the `campaigns.update` permission.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', "The campaign's UUID.", required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[BodyParam('title', 'string', 'Campaign title.', required: false, example: "Fund a child's education today")]
    #[BodyParam('description', 'string', 'The campaign story.', required: false, example: 'Fund coding bootcamp scholarships for underserved alumni and their dependents.')]
    #[BodyParam('goal_amount', 'number', 'Fundraising goal.', required: false, example: 1000000)]
    #[BodyParam('currency', 'string', 'Campaign currency.', required: false, example: 'NGN')]
    #[BodyParam('allocated_admin_id', 'string', 'Officer UUID this campaign is allocated to.', required: false, example: 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7')]
    #[BodyParam('bank_account_id', 'string', 'Remittance bank account UUID.', required: false, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[BodyParam('starts_at', 'string', 'Campaign start date.', required: false, example: '2026-08-01')]
    #[BodyParam('ends_at', 'string', 'Campaign end date.', required: false, example: '2027-02-01')]
    #[BodyParam('cover', 'file', 'New cover image (JPG, PNG, GIF, or WEBP; max 10MB).', required: false)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Campaign updated.',
        'data' => ['uuid' => 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7', 'title' => "Fund a child's education today", 'status' => 'active'],
    ], description: 'Updated campaign detail.')]
    public function update(UpdateCampaignRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaign = $this->campaignService->update($uuid, $request->validated(), $admin, $request);
            $campaign->setAttribute('donors_count', $this->campaignService->donorsCount($campaign));
            $campaign->setAttribute('days_remaining', $this->campaignService->daysRemaining($campaign));

            return JsonResponser::send(false, 'Campaign updated.', CampaignDetailResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@update');
        }
    }

    #[Endpoint('Pause a campaign', 'Backs "Pause Campaign" on the "Take Action" menu, after the "Are you sure?" confirmation. The campaign stops accepting donations until resumed. Requires the `campaigns.update` permission.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', "The campaign's UUID.", required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Campaign paused.',
        'data' => ['uuid' => 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7', 'title' => 'Feed a Child', 'status' => 'paused'],
    ], description: 'Campaign paused.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Only an active campaign can be paused.',
        'data' => null,
    ], description: 'Campaign is not currently active.')]
    public function pause(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaign = $this->campaignService->pause($uuid, $admin, $request);

            return JsonResponser::send(false, 'Campaign paused.', CampaignAdminResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@pause');
        }
    }

    #[Endpoint('Resume a campaign', 'Reactivates a paused campaign so it accepts donations again. Requires the `campaigns.update` permission.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', "The campaign's UUID.", required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Campaign resumed.',
        'data' => ['uuid' => 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7', 'title' => 'Feed a Child', 'status' => 'active'],
    ], description: 'Campaign resumed.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Only a paused campaign can be resumed.',
        'data' => null,
    ], description: 'Campaign is not currently paused.')]
    public function resume(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaign = $this->campaignService->resume($uuid, $admin, $request);

            return JsonResponser::send(false, 'Campaign resumed.', CampaignAdminResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@resume');
        }
    }

    #[Endpoint('List campaign donations', 'Paginated, searchable list of individual donation payments backing the "Donation" tab. Requires the `campaigns.read` permission.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', "The campaign's UUID.", required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[QueryParam('search', 'string', 'Matches donor name or email.', required: false, example: 'George')]
    #[QueryParam('status', 'string', 'Filter by payment status.', required: false, example: 'successful')]
    #[QueryParam('period', 'string', 'Relative date window; use `custom` with `start_date`/`end_date` for an explicit range.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Range start (required if `period=custom`).', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Range end (required if `period=custom`).', required: false, example: '2026-01-30')]
    #[QueryParam('sort_by', 'string', 'One of: value (amount).', required: false, example: 'value')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'integer', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Rows per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Campaign donations retrieved.',
        'data' => [
            'current_page' => 1,
            'data' => [
                ['donation_payment_id' => '36b95033-af1a-49a4-91b1-9f62b3cfd601', 'donor_name' => 'George Yahaya', 'amount' => '3000.00', 'method' => 'card', 'status' => 'successful'],
            ],
            'per_page' => 15,
            'total' => 1,
        ],
    ], description: 'Standard Laravel paginator shape.')]
    public function donations(CampaignDonationListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->campaignService->listDonations($uuid, $request->validated());

            return JsonResponser::send(false, 'Campaign donations retrieved.', $this->paginatedPayload($paginator, CampaignDonationResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@donations');
        }
    }

    #[Endpoint('List campaign pledges', 'Paginated, searchable list of pledge commitments backing the "Pledges" tab (BRD FEM-04: total pledged, received, outstanding, next instalment). Requires the `campaigns.read` permission.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', "The campaign's UUID.", required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[QueryParam('search', 'string', 'Matches donor name or email.', required: false, example: 'George')]
    #[QueryParam('status', 'string', 'Filter by pledge status.', required: false, example: 'on_track')]
    #[QueryParam('period', 'string', 'Relative date window; use `custom` with `start_date`/`end_date` for an explicit range.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Range start (required if `period=custom`).', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Range end (required if `period=custom`).', required: false, example: '2026-01-30')]
    #[QueryParam('sort_by', 'string', 'One of: name (donor name), value (total pledged).', required: false, example: 'value')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'integer', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Rows per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Campaign pledges retrieved.',
        'data' => [
            'current_page' => 1,
            'data' => [
                ['pledge_id' => '36b95033-af1a-49a4-91b1-9f62b3cfd601', 'donor_name' => 'George Yahaya', 'total_pledge' => '3000.00', 'received' => '2000.00', 'outstanding' => '1000.00', 'status' => 'on_track'],
            ],
            'per_page' => 15,
            'total' => 1,
        ],
    ], description: 'Standard Laravel paginator shape.')]
    public function pledges(CampaignPledgeListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->campaignService->listPledges($uuid, $request->validated());

            return JsonResponser::send(false, 'Campaign pledges retrieved.', $this->paginatedPayload($paginator, CampaignPledgeResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@pledges');
        }
    }

    #[Endpoint('Campaign donation overview', 'Total Donation Target / Total Donation Received cards backing the "Donation" tab\'s Overview panel, above the donations table. The target is the campaign\'s fixed goal; the received total is scoped to the given date window (all-time if none given). Requires the `campaigns.read` permission.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', "The campaign's UUID.", required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[QueryParam('period', 'string', 'Relative date window; use `custom` with `start_date`/`end_date` for an explicit range.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Range start (required if `period=custom`).', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Range end (required if `period=custom`).', required: false, example: '2026-01-30')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Campaign donation overview retrieved.',
        'data' => [
            'period' => 'custom',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-30',
            'target_amount' => '89000000.00',
            'target_amount_formatted' => 'NGN 89,000,000.00',
            'received_amount' => '39293943.00',
            'received_amount_formatted' => 'NGN 39,293,943.00',
        ],
    ], description: 'Target is the campaign\'s fixed goal; received is scoped to the requested date window (all-time if no period/dates given).')]
    public function donationsOverview(DateRangeStatsRequest $request, string $uuid)
    {
        try {
            $overview = $this->campaignService->donationsOverview($uuid, $request->validated());

            return JsonResponser::send(false, 'Campaign donation overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@donationsOverview');
        }
    }

    #[Endpoint('Campaign donor breakdown', 'Donor counts per recognition tier (BRD REC-01/REC-02 tiers), backing the "Donor Breakdown" chart on the Overview tab. The date window decides which of this campaign\'s donors are counted; each counted donor\'s tier is still their lifetime, cross-campaign giving total, matching the public Recognition Wall. Requires the `campaigns.read` permission.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', "The campaign's UUID.", required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[QueryParam('period', 'string', 'Relative date window; use `custom` with `start_date`/`end_date` for an explicit range.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Range start (required if `period=custom`).', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Range end (required if `period=custom`).', required: false, example: '2026-01-30')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Campaign donor breakdown retrieved.',
        'data' => [
            'period' => '30days',
            'start_date' => '2026-06-29',
            'end_date' => '2026-07-29',
            'tiers' => [
                ['tier' => 'Bronze Benefactor', 'donors' => 12],
                ['tier' => 'Silver Supporter', 'donors' => 8],
                ['tier' => 'Gold Sponsor', 'donors' => 3],
            ],
        ],
    ], description: 'Donor counts scoped to the requested date window (all-time if no period/dates given), one entry per tier ordered ascending by threshold.')]
    public function donorBreakdown(DateRangeStatsRequest $request, string $uuid)
    {
        try {
            $breakdown = $this->campaignService->donorBreakdown($uuid, $request->validated());

            return JsonResponser::send(false, 'Campaign donor breakdown retrieved.', $breakdown);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@donorBreakdown');
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

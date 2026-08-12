<?php

namespace App\Http\Controllers\v1\Admin\Fundraising;

use App\Enums\CampaignTypeEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaigns\AddCampaignInstitutionRequest;
use App\Http\Requests\Admin\Campaigns\CampaignDonationListRequest;
use App\Http\Requests\Admin\Campaigns\CampaignInstitutionListRequest;
use App\Http\Requests\Admin\Campaigns\CampaignListRequest;
use App\Http\Requests\Admin\Campaigns\CampaignPledgeListRequest;
use App\Http\Requests\Admin\Campaigns\CreateCampaignRequest;
use App\Http\Requests\Admin\Campaigns\CreateNationalGivingDayCampaignRequest;
use App\Http\Requests\Admin\Campaigns\SyncCampaignInstitutionsRequest;
use App\Http\Requests\Admin\Campaigns\UpdateCampaignInstitutionRequest;
use App\Http\Requests\Admin\Campaigns\UpdateCampaignRequest;
use App\Http\Requests\Admin\DateRangeStatsRequest;
use App\Http\Resources\Admin\CampaignAdminResource;
use App\Http\Resources\Admin\CampaignDetailResource;
use App\Http\Resources\Admin\CampaignDonationResource;
use App\Http\Resources\Admin\CampaignInstitutionResource;
use App\Http\Resources\Admin\CampaignPledgeResource;
use App\Models\Admin;
use App\Models\Campaign;
use App\Responser\JsonResponser;
use App\Services\Fundraising\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaignService) {}

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

    /**
     * Create a National Giving Day campaign
     *
     * Creates a multi-institution campaign: unlike a standard campaign, there is no single
     * top-level goal/currency/bank account - each targeted institution carries its own via the
     * `institutions` array. The campaign is created directly `active`.
     *
     * Because `cover` is a file, this request must be sent as `multipart/form-data`. Send each
     * institution as its own set of indexed fields, e.g. `institutions[0][institution_id]`,
     * `institutions[0][goal_amount]`, `institutions[0][currency]`, `institutions[0][bank_account_id]`,
     * then `institutions[1][institution_id]`, etc. for additional institutions - PHP parses this
     * into a nested array natively, no JSON encoding needed. A single JSON-encoded string in the
     * `institutions` field is also accepted as a fallback for programmatic clients.
     *
     * @group National Giving Day Campaigns
     *
     * @bodyParam title string required Campaign title. Example: Feed a Child
     * @bodyParam starts_at date required Campaign start date. Example: 2026-09-01
     * @bodyParam ends_at date Campaign end date, on/after starts_at. Example: 2026-09-30
     * @bodyParam description string required Campaign description/story.
     * @bodyParam allocated_admin_id string required UUID of the admin officer this campaign is allocated to.
     * @bodyParam cover file required Cover image (JPG/PNG/GIF/WEBP, max 10MB).
     * @bodyParam institutions object[] required List of institution allocations. Send as indexed form-data fields, e.g. institutions[0][institution_id].
     * @bodyParam institutions[].institution_id string required UUID of the targeted institution. Example: 0f2a1e2e-2c3b-4a3e-9c3d-6e3a3f1b2c4d
     * @bodyParam institutions[].goal_amount number required Fundraising goal for this institution. Example: 100000
     * @bodyParam institutions[].currency string required Currency code for this institution's goal. Example: NGN
     * @bodyParam institutions[].bank_account_id string required UUID of the bank account this institution's donations settle to. Example: b1a2c3d4-5e6f-4a3e-9c3d-6e3a3f1b2c4d
     */
    public function storeNationalGivingDay(CreateNationalGivingDayCampaignRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaign = $this->campaignService->createNationalGivingDay($request->validated(), $admin, $request);
            $this->applyNationalGivingDayTotals($campaign);

            return JsonResponser::send(false, 'National Giving Day campaign created successfully.', CampaignAdminResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@storeNationalGivingDay');
        }
    }

    public function index(CampaignListRequest $request)
    {
        try {
            $paginator = $this->campaignService->paginateForAdmin($request->validated());
            foreach ($paginator->items() as $campaign) {
                $this->applyNationalGivingDayTotals($campaign);
            }

            return JsonResponser::send(false, 'Campaigns retrieved.', $this->paginatedPayload($paginator, CampaignAdminResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@index');
        }
    }

    public function show(string $uuid)
    {
        try {
            $campaign = $this->campaignService->findForAdmin($uuid);
            $campaign->setAttribute('donors_count', $this->campaignService->donorsCount($campaign));
            $campaign->setAttribute('days_remaining', $this->campaignService->daysRemaining($campaign));
            $this->applyNationalGivingDayTotals($campaign);

            return JsonResponser::send(false, 'Campaign retrieved.', CampaignDetailResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@show');
        }
    }

    public function update(UpdateCampaignRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaign = $this->campaignService->update($uuid, $request->validated(), $admin, $request);
            $campaign->setAttribute('donors_count', $this->campaignService->donorsCount($campaign));
            $campaign->setAttribute('days_remaining', $this->campaignService->daysRemaining($campaign));
            $this->applyNationalGivingDayTotals($campaign);

            return JsonResponser::send(false, 'Campaign updated.', CampaignDetailResource::make($campaign)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@update');
        }
    }

    /**
     * A national_giving_day campaign has no top-level goal/raised/currency of its own (each
     * institution carries its own); overlay the live NGN-aggregate totals so the "View Campaign"
     * header still has something meaningful to show.
     */
    private function applyNationalGivingDayTotals(Campaign $campaign): void
    {
        if ($campaign->type !== CampaignTypeEnum::NATIONAL_GIVING_DAY->value) {
            return;
        }

        $totals = $this->campaignService->nationalGivingDayTotals($campaign);
        $campaign->setAttribute('goal_amount', $totals['goal_amount']);
        $campaign->setAttribute('raised_amount', $totals['raised_amount']);
        $campaign->setAttribute('currency', $totals['currency']);
    }

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

    /**
     * List a campaign's institutions
     *
     * Backs the "Institutions" tab of a National Giving Day campaign. Each row's `raised_amount`
     * is computed live from donations/pledges made by donors whose profile university matches
     * that institution.
     *
     * @group National Giving Day Campaigns
     */
    public function institutions(CampaignInstitutionListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->campaignService->listInstitutions($uuid, $request->validated());

            return JsonResponser::send(false, 'Campaign institutions retrieved.', $this->paginatedPayload($paginator, CampaignInstitutionResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@institutions');
        }
    }

    /**
     * Add an institution to a campaign
     *
     * Only valid on a `national_giving_day` campaign.
     *
     * @group National Giving Day Campaigns
     *
     * @bodyParam institution_id string required UUID of the institution.
     * @bodyParam goal_amount number required This institution's fundraising goal.
     * @bodyParam currency string required Currency code (NGN, USD, GBP, EUR).
     * @bodyParam bank_account_id string required UUID of the recipient bank account.
     */
    public function addInstitution(AddCampaignInstitutionRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaignInstitution = $this->campaignService->addInstitution($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Institution added to campaign.', CampaignInstitutionResource::make($campaignInstitution)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@addInstitution');
        }
    }

    /**
     * Replace a campaign's full institution allocation list
     *
     * Send the complete desired list of institutions for this campaign in one request:
     * existing allocations are updated, ones missing from the list are removed, and new
     * ones are added, all in a single transaction. Only valid on a `national_giving_day`
     * campaign. Use this when the interface lets an admin edit multiple institutions
     * together (a screen that shows the whole list at once); use POST/PATCH/DELETE on
     * `/institutions/{institutionUuid}` instead for single-row actions.
     *
     * @group National Giving Day Campaigns
     *
     * @bodyParam institutions object[] required Full list of institution allocations for this campaign.
     * @bodyParam institutions[].institution_id string required UUID of the targeted institution. Example: 0f2a1e2e-2c3b-4a3e-9c3d-6e3a3f1b2c4d
     * @bodyParam institutions[].goal_amount number required Fundraising goal for this institution. Example: 100000
     * @bodyParam institutions[].currency string required Currency code for this institution's goal. Example: NGN
     * @bodyParam institutions[].bank_account_id string required UUID of the bank account this institution's donations settle to. Example: b1a2c3d4-5e6f-4a3e-9c3d-6e3a3f1b2c4d
     */
    public function syncInstitutions(SyncCampaignInstitutionsRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaignInstitutions = $this->campaignService->syncInstitutions($uuid, $request->validated()['institutions'], $admin, $request);

            return JsonResponser::send(false, 'Campaign institutions updated.', CampaignInstitutionResource::collection($campaignInstitutions)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@syncInstitutions');
        }
    }

    /**
     * Update a single institution's allocation
     *
     * For editing one institution's allocation in isolation. If the interface edits multiple
     * institutions together, use `PUT /{uuid}/institutions` instead to submit the whole list
     * at once.
     *
     * @group National Giving Day Campaigns
     *
     * @bodyParam goal_amount number This institution's fundraising goal.
     * @bodyParam currency string Currency code (NGN, USD, GBP, EUR).
     * @bodyParam bank_account_id string UUID of the recipient bank account.
     */
    public function updateInstitution(UpdateCampaignInstitutionRequest $request, string $uuid, string $institutionUuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $campaignInstitution = $this->campaignService->updateInstitution($uuid, $institutionUuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Institution allocation updated.', CampaignInstitutionResource::make($campaignInstitution)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@updateInstitution');
        }
    }

    /**
     * Remove an institution from a campaign
     *
     * @group National Giving Day Campaigns
     */
    public function removeInstitution(Request $request, string $uuid, string $institutionUuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $this->campaignService->removeInstitution($uuid, $institutionUuid, $admin, $request);

            return JsonResponser::send(false, 'Institution removed from campaign.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@removeInstitution');
        }
    }

    public function donations(CampaignDonationListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->campaignService->listDonations($uuid, $request->validated());

            return JsonResponser::send(false, 'Campaign donations retrieved.', $this->paginatedPayload($paginator, CampaignDonationResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@donations');
        }
    }

    public function pledges(CampaignPledgeListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->campaignService->listPledges($uuid, $request->validated());

            return JsonResponser::send(false, 'Campaign pledges retrieved.', $this->paginatedPayload($paginator, CampaignPledgeResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@pledges');
        }
    }

    public function donationsOverview(DateRangeStatsRequest $request, string $uuid)
    {
        try {
            $overview = $this->campaignService->donationsOverview($uuid, $request->validated());

            return JsonResponser::send(false, 'Campaign donation overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Fundraising\CampaignController@donationsOverview');
        }
    }

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

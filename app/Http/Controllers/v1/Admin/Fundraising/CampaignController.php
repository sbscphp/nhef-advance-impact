<?php

namespace App\Http\Controllers\v1\Admin\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaigns\CreateCampaignRequest;
use App\Http\Resources\Admin\CampaignAdminResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Fundraising\CampaignService;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;

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

    private function requireAdmin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            abort(403, 'Forbidden.');
        }

        return $admin;
    }
}

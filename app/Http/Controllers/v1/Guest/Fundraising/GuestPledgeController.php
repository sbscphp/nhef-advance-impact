<?php

namespace App\Http\Controllers\v1\Guest\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\Pledges\GuestMakePledgeRequest;
use App\Http\Resources\Fundraising\PledgeResource;
use App\Responser\JsonResponser;
use App\Services\Fundraising\PledgeService;

/**
 * Guest donor flow (BRD ENT-04): pledge without an account, identity from `full_name`/`email`
 * instead of a token. No "list my pledges" here; use "Verify payment" and the emailed receipt.
 */
class GuestPledgeController extends Controller
{
    public function __construct(private readonly PledgeService $pledgeService) {}

    public function store(GuestMakePledgeRequest $request)
    {
        try {
            $result = $this->pledgeService->createPledge(null, $request->validated(), $request);

            return JsonResponser::send(false, 'Pledge created.', [
                'pledge' => PledgeResource::make($result['pledge']),
                'authorization_url' => $result['authorization_url'],
                'access_code' => $result['access_code'],
                'client_secret' => $result['client_secret'],
                'publishable_key' => $result['publishable_key'],
                'reference' => $result['reference'],
            ], 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Guest\Fundraising\GuestPledgeController@store');
        }
    }
}

<?php

namespace App\Http\Controllers\v1\Guest\Fundraising;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\Donations\GuestMakeDonationRequest;
use App\Http\Resources\Fundraising\DonationResource;
use App\Responser\JsonResponser;
use App\Services\Fundraising\DonationService;

/**
 * Guest donor flow (BRD ENT-04): donate without an account, using the same donation/payment
 * machinery as the authenticated flow (identity comes from `full_name`/`email` instead of a
 * token). No "list my donations" here since a guest has no login; use "Verify payment" and
 * the emailed receipt instead.
 */
class GuestDonationController extends Controller
{
    public function __construct(private readonly DonationService $donationService) {}

    public function store(GuestMakeDonationRequest $request)
    {
        try {
            $result = $this->donationService->createDonation(null, $request->validated(), $request);

            return JsonResponser::send(false, 'Donation created.', [
                'donation' => DonationResource::make($result['donation']),
                'authorization_url' => $result['authorization_url'],
                'access_code' => $result['access_code'],
                'client_secret' => $result['client_secret'],
                'publishable_key' => $result['publishable_key'],
                'reference' => $result['reference'],
            ], 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Guest\Fundraising\GuestDonationController@store');
        }
    }
}

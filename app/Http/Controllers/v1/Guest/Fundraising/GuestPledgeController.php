<?php

namespace App\Http\Controllers\v1\Guest\Fundraising;

use App\Enums\PaymentMethodEnum;
use App\Enums\PledgeFrequencyEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\Pledges\GuestMakePledgeRequest;
use App\Http\Resources\Fundraising\PledgeResource;
use App\Responser\JsonResponser;
use App\Services\Fundraising\PledgeService;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;

/**
 * Guest donor flow (BRD ENT-04): donate without registering an account. Same pledge/payment
 * machinery as the authenticated flow (PledgeService::createPledge(null, ...)). The only
 * difference is identity comes from `full_name`/`email` in the request instead of a token.
 * There is deliberately no "list my pledges" here: a guest has no login to fetch that with
 * later. Use "Verify payment" (Fundraising / Payments group) to confirm the payment, and
 * follow the emailed receipt for the record of it.
 */
#[Group('Guest Fundraising / Pledges', 'Make a one-time or recurring pledge without an account (BRD ENT-04).')]
class GuestPledgeController extends Controller
{
    public function __construct(private readonly PledgeService $pledgeService) {}

    #[Endpoint('Make a guest pledge')]
    #[Unauthenticated]
    #[BodyParam('campaign_uuid', 'string', 'UUID of the campaign to donate to.', required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[BodyParam('frequency', 'string', 'Pledge frequency.', required: true, example: 'one_time', enum: PledgeFrequencyEnum::class)]
    #[BodyParam('total_amount', 'number', 'Total pledge amount, in the campaign\'s currency.', required: true, example: 1000)]
    #[BodyParam('start_date', 'date', 'Anchor date the installment schedule is calculated from; also determines installment count together with end_date. Required unless frequency is `one_time`; prohibited when it is.', required: false, example: '2026-08-01')]
    #[BodyParam('end_date', 'date', 'End of the installment schedule; installment count is derived from the start_date-end_date range at the chosen frequency. Required unless frequency is `one_time`; prohibited when it is.', required: false, example: '2026-10-01')]
    #[BodyParam('expected_payment_date', 'date', 'Due date for the single payment. Required only when frequency is `one_time`; prohibited otherwise.', required: false, example: '2026-07-16')]
    #[BodyParam('is_anonymous', 'boolean', 'Hide donor identity on the recognition wall.', required: false, example: false)]
    #[BodyParam('payment_method', 'string', 'Payment method for this installment.', required: true, example: 'card', enum: PaymentMethodEnum::class)]
    #[BodyParam('full_name', 'string', 'Donor\'s full name.', required: true, example: 'Jane Donor')]
    #[BodyParam('email', 'string', 'Donor\'s email (used for the payment gateway and the emailed receipt).', required: true, example: 'jane.donor@example.com')]
    #[Response(status: 201, content: [
        'error' => false,
        'message' => 'Pledge created.',
        'data' => [
            'pledge' => ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'status' => 'pending'],
            'authorization_url' => 'https://checkout.paystack.com/abc123',
            'reference' => 'PLG_ABCDEFGHIJKLMNOPQRST',
        ],
    ], description: 'Pledge created; complete payment at authorization_url.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'This campaign does not accept recurring pledges.',
        'data' => null,
    ], description: 'Campaign does not allow the requested frequency, or is not accepting donations.')]
    public function store(GuestMakePledgeRequest $request)
    {
        try {
            $result = $this->pledgeService->createPledge(null, $request->validated(), $request);

            return JsonResponser::send(false, 'Pledge created.', [
                'pledge' => PledgeResource::make($result['pledge']),
                'authorization_url' => $result['authorization_url'],
                'reference' => $result['reference'],
            ], 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Guest\Fundraising\GuestPledgeController@store');
        }
    }
}

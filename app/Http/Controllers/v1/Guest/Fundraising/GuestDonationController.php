<?php

namespace App\Http\Controllers\v1\Guest\Fundraising;

use App\Enums\DonationFrequencyEnum;
use App\Enums\PaymentMethodEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\Donations\GuestMakeDonationRequest;
use App\Http\Resources\Fundraising\DonationResource;
use App\Responser\JsonResponser;
use App\Services\Fundraising\DonationService;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;

/**
 * Guest donor flow (BRD ENT-04): donate without registering an account. Same donation/payment
 * machinery as the authenticated flow (DonationService::createDonation(null, ...)). The only
 * difference is identity comes from `full_name`/`email` in the request instead of a token.
 * There is deliberately no "list my donations" here: a guest has no login to fetch that with
 * later. Use "Verify payment" (Fundraising / Payments group) to confirm the payment, and
 * follow the emailed receipt for the record of it.
 */
#[Group('Guest Fundraising / Donations', 'Make a one-time or recurring donation without an account (BRD ENT-04).')]
class GuestDonationController extends Controller
{
    public function __construct(private readonly DonationService $donationService) {}

    #[Endpoint('Make a guest donation')]
    #[Unauthenticated]
    #[BodyParam('campaign_uuid', 'string', 'UUID of the campaign to donate to.', required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[BodyParam('frequency', 'string', 'Donation frequency.', required: true, example: 'one_time', enum: DonationFrequencyEnum::class)]
    #[BodyParam('amount', 'number', 'Amount charged per cycle, in the campaign\'s currency.', required: true, example: 1000)]
    #[BodyParam('is_anonymous', 'boolean', 'Hide donor identity on the recognition wall.', required: false, example: false)]
    #[BodyParam('payment_method', 'string', 'Payment method for this charge.', required: true, example: 'card', enum: PaymentMethodEnum::class)]
    #[BodyParam('full_name', 'string', 'Donor\'s full name.', required: true, example: 'Jane Donor')]
    #[BodyParam('email', 'string', 'Donor\'s email (used for the payment gateway and the emailed receipt).', required: true, example: 'jane.donor@example.com')]
    #[Response(status: 201, content: [
        'error' => false,
        'message' => 'Donation created.',
        'data' => [
            'donation' => ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'status' => 'pending'],
            'authorization_url' => null,
            'access_code' => null,
            'client_secret' => 'pi_3P.._secret_abc123',
            'publishable_key' => 'pk_test_abc123',
            'reference' => 'DON_ABCDEFGHIJKLMNOPQRST',
        ],
    ], description: 'Donation created. Default (embedded) mode: Stripe returns client_secret (confirm in-app with Stripe.js/Elements + publishable_key), Paystack returns access_code/publishable_key (pass to Paystack Inline). In hosted mode, the active gateway returns authorization_url instead and the other fields are null. See STRIPE_CHECKOUT_MODE / PAYSTACK_CHECKOUT_MODE.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'This campaign does not accept recurring donations.',
        'data' => null,
    ], description: 'Campaign does not allow the requested frequency, or is not accepting donations.')]
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

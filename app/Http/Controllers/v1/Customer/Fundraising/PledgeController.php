<?php

namespace App\Http\Controllers\v1\Customer\Fundraising;

use App\Enums\PaymentMethodEnum;
use App\Enums\PledgeFrequencyEnum;
use App\Enums\PledgeStatusEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Pledges\MakePledgeRequest;
use App\Http\Requests\Customer\Pledges\PayInstallmentRequest;
use App\Http\Requests\Customer\Pledges\PledgeListRequest;
use App\Http\Resources\Fundraising\PledgePaymentResource;
use App\Http\Resources\Fundraising\PledgeResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Fundraising\PledgeService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('Customer Fundraising / Pledges', 'Make one-time or recurring pledges against a campaign and manage their installment payments (BRD FEM-01, FEM-04, FEM-06).')]
class PledgeController extends Controller
{
    public function __construct(private readonly PledgeService $pledgeService) {}

    /**
     * Make a pledge
     *
     * Creates a one-time or recurring pledge against a campaign and initializes payment for
     * the first installment. Recurring pledges have their installment count and due dates
     * derived from `start_date`, `end_date`, and `frequency` (e.g. monthly over a 3-month
     * range produces 3 installments), split evenly across the total amount. One-time pledges
     * use `expected_payment_date` as the single installment's due date. Follow
     * `authorization_url` to complete payment, then call "Verify payment" (or wait for the
     * gateway webhook).
     */
    #[Endpoint('Make a pledge')]
    #[Authenticated]
    #[BodyParam('campaign_uuid', 'string', 'UUID of the campaign to donate to.', required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[BodyParam('frequency', 'string', 'Pledge frequency.', required: true, example: 'monthly', enum: PledgeFrequencyEnum::class)]
    #[BodyParam('total_amount', 'number', 'Total pledge amount, in the campaign\'s currency.', required: true, example: 10000000)]
    #[BodyParam('start_date', 'date', 'Anchor date the installment schedule is calculated from; also determines installment count together with end_date. Required unless frequency is `one_time`; prohibited when it is.', required: false, example: '2026-07-15')]
    #[BodyParam('end_date', 'date', 'End of the installment schedule; installment count is derived from the start_date-end_date range at the chosen frequency. Required unless frequency is `one_time`; prohibited when it is.', required: false, example: '2026-09-15')]
    #[BodyParam('expected_payment_date', 'date', 'Due date for the single payment. Required only when frequency is `one_time`; prohibited otherwise.', required: false, example: '2026-07-15')]
    #[BodyParam('is_anonymous', 'boolean', 'Hide donor identity on the recognition wall.', required: false, example: false)]
    #[BodyParam('payment_method', 'string', 'Payment method for this installment.', required: true, example: 'card', enum: PaymentMethodEnum::class)]
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
    public function store(MakePledgeRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $result = $this->pledgeService->createPledge($user, $request->validated(), $request);

            return JsonResponser::send(false, 'Pledge created.', [
                'pledge' => PledgeResource::make($result['pledge']),
                'authorization_url' => $result['authorization_url'],
                'reference' => $result['reference'],
            ], 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\PledgeController@store');
        }
    }

    /**
     * List my pledges
     *
     * Returns the authenticated customer's pledges, optionally filtered by status.
     */
    #[Endpoint('List my pledges')]
    #[Authenticated]
    #[QueryParam('status', 'string', 'Filter by pledge status.', required: false, example: 'on_track', enum: PledgeStatusEnum::class)]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Pledges retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 15, 'total' => 0],
    ], description: 'Paginated list of the customer\'s pledges.')]
    public function index(PledgeListRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $paginator = $this->pledgeService->paginateForUser($user, $request->validated());

            return JsonResponser::send(false, 'Pledges retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\PledgeController@index');
        }
    }

    /**
     * View pledge
     *
     * Returns a single pledge with its installment schedule and payment history
     * (matches the "My Pledge" view: total, paid, remaining, installments, payment history).
     */
    #[Endpoint('View pledge')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Pledge UUID.', required: true, example: 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Pledge retrieved.',
        'data' => ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'status' => 'on_track', 'installments' => []],
    ], description: 'Pledge found and belongs to the authenticated customer.')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Pledge not found.',
        'data' => null,
    ], description: 'No pledge with that UUID belongs to the authenticated customer.')]
    public function show(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $pledge = $this->pledgeService->findForUser($user, $uuid);

            return JsonResponser::send(false, 'Pledge retrieved.', PledgeResource::make($pledge), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\PledgeController@show');
        }
    }

    /**
     * Pay an installment
     *
     * Initializes payment for a specific pending installment on an existing pledge (the next
     * scheduled instalment on a recurring pledge, or a retry of a failed payment).
     */
    #[Endpoint('Pay an installment')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Pledge UUID.', required: true, example: 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8')]
    #[UrlParam('installmentUuid', 'string', 'Installment UUID.', required: true, example: 'd4e5f6a7-b8c9-40d1-92e3-f4a5b6c7d8e9')]
    #[BodyParam('payment_method', 'string', 'Payment method for this installment.', required: true, example: 'bank_transfer', enum: PaymentMethodEnum::class)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Payment initialized.',
        'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'PLG_ABCDEFGHIJKLMNOPQRST'],
    ], description: 'Payment initialized; complete at authorization_url.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'This installment has already been paid.',
        'data' => null,
    ], description: 'Installment already paid, or pledge is cancelled/completed.')]
    public function payInstallment(PayInstallmentRequest $request, string $uuid, string $installmentUuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $result = $this->pledgeService->payInstallment($user, $uuid, $installmentUuid, $request, $request->validated('payment_method'));

            return JsonResponser::send(false, 'Payment initialized.', $result, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\PledgeController@payInstallment');
        }
    }

    /**
     * Verify payment
     *
     * Confirms a payment's status with the gateway. Safe to call after redirect back from
     * checkout even if the webhook already confirmed it (idempotent).
     */
    #[Endpoint('Verify payment')]
    #[Authenticated]
    #[UrlParam('reference', 'string', 'Gateway reference returned by "Make a pledge" or "Pay an installment".', required: true, example: 'PLG_ABCDEFGHIJKLMNOPQRST')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Payment verified.',
        'data' => ['payment' => ['status' => 'successful'], 'pledge' => ['status' => 'on_track']],
    ], description: 'Payment verified with the gateway (successful or failed).')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Payment not found.',
        'data' => null,
    ], description: 'No payment with that gateway reference.')]
    public function verifyPayment(Request $request, string $reference)
    {
        try {
            $this->requireCustomer($request);
            $result = $this->pledgeService->verifyPayment($reference, $request);

            return JsonResponser::send(false, 'Payment verified.', [
                'payment' => PledgePaymentResource::make($result['payment']),
                'pledge' => PledgeResource::make($result['pledge']),
            ], 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\PledgeController@verifyPayment');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = PledgeResource::collection($paginator)->resolve();

        return $payload;
    }

    private function requireCustomer(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403, 'Forbidden.');
        }

        return $user;
    }
}

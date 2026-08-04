<?php

namespace App\Http\Controllers\v1\Customer\Fundraising;

use App\Enums\DonationFrequencyEnum;
use App\Enums\DonationStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Donations\ChargeDonationRequest;
use App\Http\Requests\Customer\Donations\DonationHistoryOverviewRequest;
use App\Http\Requests\Customer\Donations\DonationListRequest;
use App\Http\Requests\Customer\Donations\DonationPaymentListRequest;
use App\Http\Requests\Customer\Donations\MakeDonationRequest;
use App\Http\Requests\Customer\Donations\ModifyDonationRequest;
use App\Http\Resources\Fundraising\DonationPaymentResource;
use App\Http\Resources\Fundraising\DonationResource;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Fundraising\DonationService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('Customer Fundraising / Donations', 'Make one-time or recurring donations against a campaign and view giving history (BRD FEM-01, FEM-02, FEM-05, FEM-06, FEM-07).')]
class DonationController extends Controller
{
    public function __construct(private readonly DonationService $donationService) {}

    #[Endpoint('Make a donation')]
    #[Authenticated]
    #[BodyParam('campaign_uuid', 'string', 'UUID of the campaign to donate to.', required: true, example: 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7')]
    #[BodyParam('frequency', 'string', 'Donation frequency.', required: true, example: 'one_time', enum: DonationFrequencyEnum::class)]
    #[BodyParam('amount', 'number', 'Amount charged per cycle, in the campaign\'s currency.', required: true, example: 50000)]
    #[BodyParam('is_anonymous', 'boolean', 'Hide donor identity on the recognition wall.', required: false, example: false)]
    #[BodyParam('payment_method', 'string', 'Payment method for this charge.', required: true, example: 'card', enum: PaymentMethodEnum::class)]
    #[Response(status: 201, content: [
        'error' => false,
        'message' => 'Donation created.',
        'data' => [
            'donation' => ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'status' => 'pending'],
            'authorization_url' => 'https://checkout.paystack.com/abc123',
            'reference' => 'DON_ABCDEFGHIJKLMNOPQRST',
        ],
    ], description: 'Donation created; complete payment at authorization_url.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'This campaign does not accept recurring donations.',
        'data' => null,
    ], description: 'Campaign does not allow the requested frequency, or is not accepting donations.')]
    public function store(MakeDonationRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $result = $this->donationService->createDonation($user, $request->validated(), $request);

            return JsonResponser::send(false, 'Donation created.', [
                'donation' => DonationResource::make($result['donation']),
                'authorization_url' => $result['authorization_url'],
                'reference' => $result['reference'],
            ], 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@store');
        }
    }

    #[Endpoint('List my donations')]
    #[Authenticated]
    #[QueryParam('status', 'string', 'Filter by donation status.', required: false, example: 'active', enum: DonationStatusEnum::class)]
    #[QueryParam('search', 'string', 'Filter by campaign title (partial match).', required: false, example: 'Scholarship')]
    #[QueryParam('is_recurring', 'boolean', 'true for the "Recurring Donations" view (monthly/quarterly/annually); false for one-time donations only. Omit to see both.', required: false, example: true)]
    #[QueryParam('start_date', 'date', 'Only donations created on/after this date.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'date', 'Only donations created on/before this date.', required: false, example: '2026-01-31')]
    #[QueryParam('sort_by', 'string', 'Order arrangement field: "name" (campaign title) or "value" (donation amount).', required: false, example: 'value')]
    #[QueryParam('sort_direction', 'string', 'Order arrangement direction: asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Donations retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 15, 'total' => 0],
    ], description: 'Paginated list of the customer\'s donations.')]
    public function index(DonationListRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $paginator = $this->donationService->paginateForUser($user, $request->validated());

            return JsonResponser::send(false, 'Donations retrieved.', $this->paginatedPayload($paginator), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@index');
        }
    }

    #[Endpoint('View donation')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Donation UUID.', required: true, example: 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Donation retrieved.',
        'data' => ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'status' => 'active', 'payments' => []],
    ], description: 'Donation found and belongs to the authenticated customer.')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Donation not found.',
        'data' => null,
    ], description: 'No donation with that UUID belongs to the authenticated customer.')]
    public function show(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $donation = $this->donationService->findForUser($user, $uuid);

            return JsonResponser::send(false, 'Donation retrieved.', DonationResource::make($donation), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@show');
        }
    }

    #[Endpoint('List my donation payments')]
    #[Authenticated]
    #[QueryParam('status', 'string', 'Filter by payment status.', required: false, example: 'successful')]
    #[QueryParam('search', 'string', 'Filter by campaign title (partial match).', required: false, example: 'Scholarship')]
    #[QueryParam('start_date', 'date', 'Only payments created on/after this date.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'date', 'Only payments created on/before this date.', required: false, example: '2026-01-31')]
    #[QueryParam('sort_by', 'string', 'Order arrangement field: "name" (campaign title) or "value" (payment amount).', required: false, example: 'value')]
    #[QueryParam('sort_direction', 'string', 'Order arrangement direction: asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Donation payments retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'per_page' => 15, 'total' => 0],
    ], description: 'Paginated list of the customer\'s donation payments.')]
    public function paymentHistory(DonationPaymentListRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $paginator = $this->donationService->paginatePaymentsForUser($user, $request->validated());

            $payload = $paginator->toArray();
            $payload['data'] = DonationPaymentResource::collection($paginator)->resolve();

            return JsonResponser::send(false, 'Donation payments retrieved.', $payload, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@paymentHistory');
        }
    }

    #[Endpoint('Donation history overview')]
    #[Authenticated]
    #[QueryParam('from', 'date', 'Scope "received" to payments paid on/after this date.', required: false, example: '2026-01-01')]
    #[QueryParam('to', 'date', 'Scope "received" to payments paid on/before this date.', required: false, example: '2026-01-31')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Overview retrieved.',
        'data' => [
            'target' => '89000000.00',
            'target_formatted' => 'NGN 89,000,000.00',
            'received' => '39293943.00',
            'received_formatted' => 'NGN 39,293,943.00',
        ],
    ], description: 'Overview totals retrieved.')]
    public function paymentHistoryOverview(DonationHistoryOverviewRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $overview = $this->donationService->historyOverview($user, $request->validated('from'), $request->validated('to'));

            return JsonResponser::send(false, 'Overview retrieved.', $overview, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@paymentHistoryOverview');
        }
    }

    #[Endpoint('View donation payment')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Donation payment UUID.', required: true, example: 'd4e5f6a7-b8c9-40d1-92e3-f4a5b6c7d8e9')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Donation payment retrieved.',
        'data' => ['uuid' => 'd4e5f6a7-b8c9-40d1-92e3-f4a5b6c7d8e9', 'status' => 'successful'],
    ], description: 'Donation payment found and belongs to the authenticated customer.')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Payment not found.',
        'data' => null,
    ], description: 'No payment with that UUID belongs to the authenticated customer.')]
    public function showPayment(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $payment = $this->donationService->findPaymentForUser($user, $uuid);

            return JsonResponser::send(false, 'Donation payment retrieved.', DonationPaymentResource::make($payment), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@showPayment');
        }
    }

    #[Endpoint('Charge next cycle')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Donation UUID.', required: true, example: 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8')]
    #[BodyParam('payment_method', 'string', 'Payment method for this charge.', required: true, example: 'bank_transfer', enum: PaymentMethodEnum::class)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Payment initialized.',
        'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'DON_ABCDEFGHIJKLMNOPQRST'],
    ], description: 'Payment initialized; complete at authorization_url.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'This is a one-time donation and cannot be charged again.',
        'data' => null,
    ], description: 'Donation is one-time, or is cancelled/failed.')]
    public function chargeNext(ChargeDonationRequest $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $result = $this->donationService->chargeNextCycle($user, $uuid, $request, $request->validated('payment_method'));

            return JsonResponser::send(false, 'Payment initialized.', $result, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@chargeNext');
        }
    }

    #[Endpoint('Modify recurring donation')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Donation UUID.', required: true, example: 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8')]
    #[BodyParam('amount', 'number', 'New amount charged per cycle. Required if frequency is omitted.', required: false, example: 10000)]
    #[BodyParam('frequency', 'string', 'New donation frequency (monthly, quarterly, or annually only). Required if amount is omitted.', required: false, example: 'quarterly')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Donation modified.',
        'data' => ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'amount' => '10000.00', 'frequency' => 'quarterly'],
    ], description: 'Donation modified.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Only an active or paused recurring donation can be modified.',
        'data' => null,
    ], description: 'Donation is one-time, or already completed/cancelled.')]
    public function modify(ModifyDonationRequest $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $donation = $this->donationService->modifyDonation($user, $uuid, $request->validated(), $request);

            return JsonResponser::send(false, 'Donation modified.', DonationResource::make($donation), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@modify');
        }
    }

    #[Endpoint('Pause recurring donation')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Donation UUID.', required: true, example: 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Donation paused.',
        'data' => ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'status' => 'paused'],
    ], description: 'Donation paused.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Only an active recurring donation can be paused.',
        'data' => null,
    ], description: 'Donation is one-time, or not currently active.')]
    public function pause(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $donation = $this->donationService->pauseDonation($user, $uuid, $request);

            return JsonResponser::send(false, 'Donation paused.', DonationResource::make($donation), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@pause');
        }
    }

    #[Endpoint('Resume recurring donation')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Donation UUID.', required: true, example: 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Donation resumed.',
        'data' => ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'status' => 'active'],
    ], description: 'Donation resumed.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Only a paused donation can be resumed.',
        'data' => null,
    ], description: 'Donation is not currently paused.')]
    public function resume(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $donation = $this->donationService->resumeDonation($user, $uuid, $request);

            return JsonResponser::send(false, 'Donation resumed.', DonationResource::make($donation), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@resume');
        }
    }

    #[Endpoint('Cancel recurring donation')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Donation UUID.', required: true, example: 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Donation cancelled.',
        'data' => ['uuid' => 'c3d4e5f6-a7b8-49c0-91d2-e3f4a5b6c7d8', 'status' => 'cancelled'],
    ], description: 'Donation cancelled.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'Only an active or paused recurring donation can be cancelled.',
        'data' => null,
    ], description: 'Donation is one-time, or already completed/cancelled.')]
    public function cancel(Request $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $donation = $this->donationService->cancelDonation($user, $uuid, $request);

            return JsonResponser::send(false, 'Donation cancelled.', DonationResource::make($donation), 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@cancel');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = DonationResource::collection($paginator)->resolve();

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

<?php

namespace App\Http\Controllers\v1\Customer\Fundraising;

use App\Helpers\GeneralHelper;
use App\Helpers\PDFReportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Donations\ChargeDonationRequest;
use App\Http\Requests\Customer\Donations\DonationHistoryOverviewRequest;
use App\Http\Requests\Customer\Donations\DonationListRequest;
use App\Http\Requests\Customer\Donations\DonationPaymentListRequest;
use App\Http\Requests\Customer\Donations\MakeDonationRequest;
use App\Http\Requests\Customer\Donations\ModifyDonationRequest;
use App\Http\Resources\Fundraising\DonationPaymentResource;
use App\Http\Resources\Fundraising\DonationResource;
use App\Models\DonationPayment;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Fundraising\DonationService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DonationController extends Controller
{
    public function __construct(
        private readonly DonationService $donationService,
        private readonly PDFReportHelper $pdfReportHelper,
    ) {}

    public function store(MakeDonationRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $result = $this->donationService->createDonation($user, $request->validated(), $request);

            return JsonResponser::send(false, 'Donation created.', [
                'donation' => DonationResource::make($result['donation']),
                'authorization_url' => $result['authorization_url'],
                'access_code' => $result['access_code'],
                'client_secret' => $result['client_secret'],
                'publishable_key' => $result['publishable_key'],
                'reference' => $result['reference'],
            ], 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@store');
        }
    }

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

    public function paymentHistory(DonationPaymentListRequest $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $filters = $request->validated();
            $export = $filters['export'] ?? null;

            return match ($export) {
                'csv' => $this->respondPaymentHistoryCsv($user, $filters),
                'pdf' => $this->respondPaymentHistoryPdf($user, $filters),
                default => $this->respondPaymentHistoryPaginated($user, $filters),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@paymentHistory');
        }
    }

    private function respondPaymentHistoryPaginated(User $user, array $filters)
    {
        $paginator = $this->donationService->paginatePaymentsForUser($user, $filters);

        $payload = $paginator->toArray();
        $payload['data'] = DonationPaymentResource::collection($paginator)->resolve();

        return JsonResponser::send(false, 'Donation payments retrieved.', $payload, 200);
    }

    private function respondPaymentHistoryCsv(User $user, array $filters): StreamedResponse
    {
        /** @var Collection<int, DonationPayment> $collection */
        [$collection, $truncated] = $this->donationService->exportPaymentsForUser($user, $filters);

        $filename = 'donation-history-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($collection): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Date', 'Campaign', 'Amount', 'Currency', 'Method', 'Status', 'Reference']);

            foreach ($collection as $payment) {
                fputcsv($out, $this->paymentTabularRow($payment));
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    private function respondPaymentHistoryPdf(User $user, array $filters)
    {
        /** @var Collection<int, DonationPayment> $collection */
        [$collection, $truncated] = $this->donationService->exportPaymentsForUser($user, $filters);

        $filename = 'donation-history-'.now()->format('Y-m-d-His').'.pdf';

        $headings = ['Date', 'Campaign', 'Amount', 'Method', 'Status', 'Reference'];

        $rows = $collection->values()->map(fn (DonationPayment $payment): array => [
            $payment->paid_at?->format('Y-m-d H:i') ?? $payment->created_at?->format('Y-m-d H:i') ?? '',
            $payment->donation?->campaign?->title ?? '',
            Money::format($payment->amount, $payment->currency),
            $payment->method ?? '',
            $payment->status,
            $payment->gateway_reference ?? '',
        ]);

        return $this->pdfReportHelper->download(
            rows: $rows,
            headings: $headings,
            title: 'Donation history',
            filename: $filename,
            orientation: 'portrait',
            periodStart: $filters['start_date'] ?? 'All dates',
            periodEnd: $filters['end_date'] ?? 'All dates',
            generatedAt: now((string) config('app.timezone')),
            truncated: $truncated,
            includedRows: $rows->count(),
        );
    }

    /**
     * @return list<string>
     */
    private function paymentTabularRow(DonationPayment $payment): array
    {
        return [
            $payment->paid_at?->toIso8601String() ?? $payment->created_at?->toIso8601String() ?? '',
            $payment->donation?->campaign?->title ?? '',
            (string) $payment->amount,
            $payment->currency,
            $payment->method ?? '',
            $payment->status,
            $payment->gateway_reference ?? '',
        ];
    }

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

    public function chargeNext(ChargeDonationRequest $request, string $uuid)
    {
        try {
            $user = $this->requireCustomer($request);
            $result = $this->donationService->chargeNextCycle($user, $uuid, $request);

            return JsonResponser::send(false, 'Payment initialized.', $result, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Fundraising\DonationController@chargeNext');
        }
    }

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

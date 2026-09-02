<?php

namespace App\Http\Controllers\v1\Admin\Donation;

use App\Helpers\GeneralHelper;
use App\Helpers\PDFReportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DateRangeStatsRequest;
use App\Http\Requests\Admin\Donation\DonationPaymentListRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Http\Requests\Recognition\LeaderboardListRequest;
use App\Http\Resources\Admin\Donation\DonationPaymentAdminResource;
use App\Http\Resources\Recognition\LeaderboardEntryResource;
use App\Models\DonationPayment;
use App\Responser\JsonResponser;
use App\Services\Fundraising\DonationService;
use App\Services\Recognition\RecognitionService;
use App\Support\Money;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DonationController extends Controller
{
    public function __construct(
        private readonly DonationService $donationService,
        private readonly RecognitionService $recognitionService,
        private readonly PDFReportHelper $pdfReportHelper,
    ) {}

    public function index(DonationPaymentListRequest $request)
    {
        try {
            $filters = $request->validated();
            $export = $filters['export'] ?? null;

            return match ($export) {
                'csv' => $this->respondCsv($filters),
                'pdf' => $this->respondPdf($filters),
                default => JsonResponser::send(false, 'Donations retrieved.', $this->paginatedPayload(
                    $this->donationService->paginatePaymentsForAdmin($filters),
                    DonationPaymentAdminResource::class
                )),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Donation\DonationController@index');
        }
    }

    private function respondCsv(array $filters): StreamedResponse
    {
        /** @var Collection<int, DonationPayment> $collection */
        [$collection, $truncated] = $this->donationService->exportPaymentsForAdmin($filters);

        $filename = 'donations-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($collection): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Donor', 'Campaign', 'Amount', 'Currency', 'Method', 'Status', 'Reference']);

            foreach ($collection as $payment) {
                fputcsv($out, $this->tabularRow($payment));
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    private function respondPdf(array $filters)
    {
        /** @var Collection<int, DonationPayment> $collection */
        [$collection, $truncated] = $this->donationService->exportPaymentsForAdmin($filters);

        $filename = 'donations-'.now()->format('Y-m-d-His').'.pdf';
        $headings = ['Date', 'Donor', 'Campaign', 'Amount', 'Method', 'Status', 'Reference'];

        $rows = $collection->values()->map(fn (DonationPayment $payment): array => [
            $payment->paid_at?->format('Y-m-d H:i') ?? $payment->created_at?->format('Y-m-d H:i') ?? '',
            $payment->donation?->donorName() ?? '',
            $payment->donation?->campaign?->title ?? '',
            Money::format($payment->amount, $payment->currency),
            $payment->method ?? '',
            $payment->status,
            $payment->gateway_reference ?? '',
        ]);

        return $this->pdfReportHelper->download(
            rows: $rows,
            headings: $headings,
            title: 'Donations',
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
    private function tabularRow(DonationPayment $payment): array
    {
        return [
            $payment->paid_at?->toIso8601String() ?? $payment->created_at?->toIso8601String() ?? '',
            $payment->donation?->donorName() ?? '',
            $payment->donation?->campaign?->title ?? '',
            (string) $payment->amount,
            $payment->currency,
            $payment->method ?? '',
            $payment->status,
            $payment->gateway_reference ?? '',
        ];
    }

    public function show(string $uuid)
    {
        try {
            $payment = $this->donationService->findPaymentForAdmin($uuid);

            return JsonResponser::send(false, 'Donation retrieved.', DonationPaymentAdminResource::make($payment)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Donation\DonationController@show');
        }
    }

    public function overview(DateRangeStatsRequest $request)
    {
        try {
            $window = ListingFilterRules::resolveDateWindow($request->validated());
            $overview = $this->donationService->adminHistoryOverview(
                $window['start']?->toDateString(),
                $window['end']?->toDateString(),
            );

            return JsonResponser::send(false, 'Donation overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Donation\DonationController@overview');
        }
    }

    public function leaderboard(LeaderboardListRequest $request)
    {
        try {
            $paginator = $this->recognitionService->leaderboard($request->validated());

            return JsonResponser::send(false, 'Leaderboard retrieved.', $this->paginatedPayload($paginator, LeaderboardEntryResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Donation\DonationController@leaderboard');
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
}

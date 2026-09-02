<?php

namespace App\Http\Controllers\v1\Admin\ConstituentManagement;

use App\Helpers\GeneralHelper;
use App\Helpers\PDFReportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConstituentManagement\ConstituentDonationListRequest;
use App\Http\Requests\Admin\ConstituentManagement\ConstituentListRequest;
use App\Http\Requests\Admin\ConstituentManagement\ConstituentPaymentListRequest;
use App\Http\Requests\Admin\ConstituentManagement\ConstituentPaymentOverviewRequest;
use App\Http\Requests\Admin\ConstituentManagement\ConstituentPledgeListRequest;
use App\Http\Requests\Admin\ConstituentManagement\InviteConstituentRequest;
use App\Http\Requests\Admin\ConstituentManagement\SendPledgeReminderRequest;
use App\Http\Requests\Admin\ConstituentManagement\UpdateConstituentRequest;
use App\Http\Requests\Admin\DateRangeStatsRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Http\Resources\Admin\ConstituentManagement\ConstituentAdminResource;
use App\Http\Resources\Admin\ConstituentManagement\ConstituentDetailResource;
use App\Http\Resources\Fundraising\DonationPaymentResource;
use App\Http\Resources\Fundraising\DonationResource;
use App\Http\Resources\Fundraising\PledgeResource;
use App\Models\Admin;
use App\Models\DonationPayment;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\ConstituentManagement\AdminConstituentService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConstituentController extends Controller
{
    public function __construct(
        private readonly AdminConstituentService $constituentService,
        private readonly PDFReportHelper $pdfReportHelper,
    ) {}

    public function store(InviteConstituentRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $user = $this->constituentService->invite($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Constituent invited successfully.', ConstituentDetailResource::make($user)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@store');
        }
    }

    public function index(ConstituentListRequest $request)
    {
        try {
            $filters = $request->validated();
            $export = $filters['export'] ?? null;

            return match ($export) {
                'csv' => $this->respondAlumniCsv($filters),
                'pdf' => $this->respondAlumniPdf($filters),
                default => JsonResponser::send(false, 'Constituents retrieved.', $this->paginatedPayload(
                    $this->constituentService->paginateForAdmin($filters),
                    ConstituentAdminResource::class
                )),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@index');
        }
    }

    private function respondAlumniCsv(array $filters): StreamedResponse
    {
        /** @var Collection<int, User> $collection */
        [$collection, $truncated] = $this->constituentService->exportForAdmin($filters);

        $filename = 'alumni-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($collection): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Name', 'Email', 'University', 'Department', 'Graduation year', 'Status', 'Date added']);

            foreach ($collection as $user) {
                fputcsv($out, $this->alumniTabularRow($user));
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    private function respondAlumniPdf(array $filters)
    {
        /** @var Collection<int, User> $collection */
        [$collection, $truncated] = $this->constituentService->exportForAdmin($filters);

        $filename = 'alumni-'.now()->format('Y-m-d-His').'.pdf';
        $headings = ['Name', 'Email', 'University', 'Department', 'Graduation year', 'Status', 'Date added'];
        $rows = $collection->values()->map(fn (User $user): array => $this->alumniTabularRow($user));

        return $this->pdfReportHelper->download(
            rows: $rows,
            headings: $headings,
            title: 'Alumni',
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
    private function alumniTabularRow(User $user): array
    {
        return [
            $user->displayName(),
            $user->email,
            $user->university ?? '',
            $user->department ?? '',
            $user->year_of_graduation !== null ? (string) $user->year_of_graduation : '',
            $user->status,
            $user->created_at?->toIso8601String() ?? '',
        ];
    }

    public function overview(DateRangeStatsRequest $request)
    {
        try {
            $window = ListingFilterRules::resolveDateWindow($request->validated());
            $overview = $this->constituentService->overview($window['start'], $window['end']);

            return JsonResponser::send(false, 'Constituent overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@overview');
        }
    }

    public function show(string $uuid)
    {
        try {
            $user = $this->constituentService->showForAdmin($uuid);

            return JsonResponser::send(false, 'Constituent retrieved.', ConstituentDetailResource::make($user)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@show');
        }
    }

    public function update(UpdateConstituentRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $user = $this->constituentService->update($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Constituent updated.', ConstituentDetailResource::make($user)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@update');
        }
    }

    public function revoke(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $user = $this->constituentService->revokeAccess($uuid, $admin, $request);

            return JsonResponser::send(false, 'Constituent access revoked.', ConstituentDetailResource::make($user)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@revoke');
        }
    }

    public function reactivate(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $user = $this->constituentService->reactivateAccess($uuid, $admin, $request);

            return JsonResponser::send(false, 'Constituent access reactivated.', ConstituentDetailResource::make($user)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@reactivate');
        }
    }

    public function resendInvite(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $user = $this->constituentService->resendInvite($uuid, $admin, $request);

            return JsonResponser::send(false, 'Onboarding invite resent.', ConstituentDetailResource::make($user)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@resendInvite');
        }
    }

    public function donations(ConstituentDonationListRequest $request, string $uuid)
    {
        try {
            $user = $this->constituentService->findForAdmin($uuid);
            $paginator = $this->constituentService->paginateDonations($user, $request->validated());

            return JsonResponser::send(false, 'Constituent donations retrieved.', $this->paginatedPayload($paginator, DonationResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@donations');
        }
    }

    public function payments(ConstituentPaymentListRequest $request, string $uuid)
    {
        try {
            $user = $this->constituentService->findForAdmin($uuid);
            $filters = $request->validated();
            $export = $filters['export'] ?? null;

            return match ($export) {
                'csv' => $this->respondPaymentsCsv($user, $filters),
                'pdf' => $this->respondPaymentsPdf($user, $filters),
                default => JsonResponser::send(false, 'Constituent donation payments retrieved.', $this->paginatedPayload(
                    $this->constituentService->paginatePayments($user, $filters),
                    DonationPaymentResource::class
                )),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@payments');
        }
    }

    private function respondPaymentsCsv(User $user, array $filters): StreamedResponse
    {
        /** @var Collection<int, DonationPayment> $collection */
        [$collection, $truncated] = $this->constituentService->exportPayments($user, $filters);

        $filename = 'alumni-donations-'.now()->format('Y-m-d-His').'.csv';

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

    private function respondPaymentsPdf(User $user, array $filters)
    {
        /** @var Collection<int, DonationPayment> $collection */
        [$collection, $truncated] = $this->constituentService->exportPayments($user, $filters);

        $filename = 'alumni-donations-'.now()->format('Y-m-d-His').'.pdf';
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
            title: $user->displayName().' - Donation history',
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

    public function showPayment(string $uuid, string $paymentUuid)
    {
        try {
            $user = $this->constituentService->findForAdmin($uuid);
            $payment = $this->constituentService->findPaymentForAdmin($user, $paymentUuid);

            return JsonResponser::send(false, 'Donation payment retrieved.', DonationPaymentResource::make($payment)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@showPayment');
        }
    }

    public function paymentsOverview(ConstituentPaymentOverviewRequest $request, string $uuid)
    {
        try {
            $user = $this->constituentService->findForAdmin($uuid);
            $overview = $this->constituentService->paymentsOverview($user, $request->validated('from'), $request->validated('to'));

            return JsonResponser::send(false, 'Donation overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@paymentsOverview');
        }
    }

    public function pledges(ConstituentPledgeListRequest $request, string $uuid)
    {
        try {
            $user = $this->constituentService->findForAdmin($uuid);
            $paginator = $this->constituentService->paginatePledges($user, $request->validated());

            return JsonResponser::send(false, 'Constituent pledges retrieved.', $this->paginatedPayload($paginator, PledgeResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@pledges');
        }
    }

    public function showPledge(string $uuid, string $pledgeUuid)
    {
        try {
            $user = $this->constituentService->findForAdmin($uuid);
            $pledge = $this->constituentService->findPledgeForAdmin($user, $pledgeUuid);

            return JsonResponser::send(false, 'Pledge retrieved.', PledgeResource::make($pledge)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@showPledge');
        }
    }

    public function pledgesOverview(string $uuid)
    {
        try {
            $user = $this->constituentService->findForAdmin($uuid);
            $overview = $this->constituentService->pledgesOverview($user);

            return JsonResponser::send(false, 'Pledge overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@pledgesOverview');
        }
    }

    public function sendPledgeReminder(SendPledgeReminderRequest $request, string $uuid, string $pledgeUuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $user = $this->constituentService->findForAdmin($uuid);
            $pledge = $this->constituentService->findPledgeForAdmin($user, $pledgeUuid);
            $this->constituentService->sendPledgeReminder($pledge, $user, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Reminder sent.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\ConstituentManagement\ConstituentController@sendPledgeReminder');
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

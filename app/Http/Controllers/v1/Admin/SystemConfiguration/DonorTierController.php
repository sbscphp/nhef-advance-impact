<?php

namespace App\Http\Controllers\v1\Admin\SystemConfiguration;

use App\Helpers\GeneralHelper;
use App\Helpers\PDFReportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemConfiguration\CreateDonorTierRequest;
use App\Http\Requests\Admin\SystemConfiguration\DonorTierAlumniListRequest;
use App\Http\Requests\Admin\SystemConfiguration\DonorTierListRequest;
use App\Http\Requests\Admin\SystemConfiguration\UpdateDonorTierRequest;
use App\Http\Resources\Admin\SystemConfiguration\DonorTierAdminResource;
use App\Http\Resources\Admin\SystemConfiguration\DonorTierAlumniResource;
use App\Http\Resources\Admin\SystemConfiguration\DonorTierDetailResource;
use App\Models\Admin;
use App\Models\DonorTier;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\DonorTier\DonorTierService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DonorTierController extends Controller
{
    public function __construct(
        private readonly DonorTierService $tierService,
        private readonly PDFReportHelper $pdfReportHelper,
    ) {}

    public function store(CreateDonorTierRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $tier = $this->tierService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Donation tier created.', DonorTierAdminResource::make($tier)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\DonorTierController@store');
        }
    }

    public function index(DonorTierListRequest $request)
    {
        try {
            $filters = $request->validated();
            $export = $filters['export'] ?? null;

            return match ($export) {
                'csv' => $this->respondCsv($filters),
                'pdf' => $this->respondPdf($filters),
                default => JsonResponser::send(false, 'Donation tiers retrieved.', $this->paginatedPayload(
                    $this->tierService->paginateForAdmin($filters),
                    DonorTierAdminResource::class
                )),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\DonorTierController@index');
        }
    }

    private function respondCsv(array $filters): StreamedResponse
    {
        /** @var Collection<int, DonorTier> $collection */
        [$collection, $truncated] = $this->tierService->exportForAdmin($filters);

        $filename = 'donation-tiers-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($collection): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Tier ID', 'Tier Name', 'Minimum Threshold', 'Maximum Threshold', 'Status', 'Last Updated']);

            foreach ($collection as $tier) {
                fputcsv($out, $this->tierTabularRow($tier));
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    private function respondPdf(array $filters)
    {
        /** @var Collection<int, DonorTier> $collection */
        [$collection, $truncated] = $this->tierService->exportForAdmin($filters);

        $filename = 'donation-tiers-'.now()->format('Y-m-d-His').'.pdf';
        $headings = ['Tier ID', 'Tier Name', 'Minimum Threshold', 'Maximum Threshold', 'Status', 'Last Updated'];
        $rows = $collection->values()->map(fn (DonorTier $tier): array => $this->tierTabularRow($tier));

        return $this->pdfReportHelper->download(
            rows: $rows,
            headings: $headings,
            title: 'Donation Tiers',
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
    private function tierTabularRow(DonorTier $tier): array
    {
        return [
            $tier->code(),
            $tier->name,
            (string) $tier->minimum_amount,
            $tier->maximum_amount !== null ? (string) $tier->maximum_amount : '',
            $tier->is_active ? 'Active' : 'Deactivated',
            $tier->updated_at?->toIso8601String() ?? '',
        ];
    }

    public function show(string $uuid)
    {
        try {
            $tier = $this->tierService->detailForAdmin($uuid);

            return JsonResponser::send(false, 'Donation tier retrieved.', DonorTierDetailResource::make($tier)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\DonorTierController@show');
        }
    }

    public function update(UpdateDonorTierRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $tier = $this->tierService->update($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Donation tier updated.', DonorTierAdminResource::make($tier)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\DonorTierController@update');
        }
    }

    public function toggleStatus(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $tier = $this->tierService->toggleActiveStatus($uuid, $admin, $request);
            $message = $tier->is_active ? 'Donation tier reactivated.' : 'Donation tier deactivated.';

            return JsonResponser::send(false, $message, DonorTierAdminResource::make($tier)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\DonorTierController@toggleStatus');
        }
    }

    public function destroy(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $this->tierService->delete($uuid, $admin, $request);

            return JsonResponser::send(false, 'Donation tier deleted.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\DonorTierController@destroy');
        }
    }

    public function alumni(DonorTierAlumniListRequest $request, string $uuid)
    {
        try {
            $filters = $request->validated();
            $export = $filters['export'] ?? null;

            return match ($export) {
                'csv' => $this->respondAlumniCsv($uuid, $filters),
                'pdf' => $this->respondAlumniPdf($uuid, $filters),
                default => JsonResponser::send(false, 'Tier alumni retrieved.', $this->paginatedPayload(
                    $this->tierService->paginateAlumni($uuid, $filters),
                    DonorTierAlumniResource::class
                )),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\DonorTierController@alumni');
        }
    }

    private function respondAlumniCsv(string $uuid, array $filters): StreamedResponse
    {
        /** @var Collection<int, User> $collection */
        [$collection, $truncated] = $this->tierService->exportAlumni($uuid, $filters);

        $filename = 'donation-tier-alumni-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($collection): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Alumni Name', 'Linked Institution', 'Email Address', 'No. of Donations', 'Life Time Donations', 'Date of Upgrade']);

            foreach ($collection as $user) {
                fputcsv($out, $this->alumniTabularRow($user));
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    private function respondAlumniPdf(string $uuid, array $filters)
    {
        /** @var Collection<int, User> $collection */
        [$collection, $truncated] = $this->tierService->exportAlumni($uuid, $filters);

        $filename = 'donation-tier-alumni-'.now()->format('Y-m-d-His').'.pdf';
        $headings = ['Alumni Name', 'Linked Institution', 'Email Address', 'No. of Donations', 'Life Time Donations', 'Date of Upgrade'];
        $rows = $collection->values()->map(fn (User $user): array => $this->alumniTabularRow($user));

        return $this->pdfReportHelper->download(
            rows: $rows,
            headings: $headings,
            title: 'Donation Tier Alumni',
            filename: $filename,
            orientation: 'portrait',
            periodStart: 'All dates',
            periodEnd: 'All dates',
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
            $user->university ?? '',
            $user->email,
            (string) $user->payments_count,
            (string) $user->lifetime_total,
            $user->upgraded_at?->toIso8601String() ?? '',
        ];
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

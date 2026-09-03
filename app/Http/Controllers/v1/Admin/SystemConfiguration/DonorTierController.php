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
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DonorTierController extends Controller
{
    public function __construct(
        private readonly DonorTierService $tierService,
        private readonly PDFReportHelper $pdfReportHelper,
    ) {}

    #[Group('Donation Tier Configuration', 'Create and manage donation tiers to provide donors with predefined giving options and associated benefits or recognition levels. Postman folder: Donation Tier Configuration.')]
    #[Endpoint('Create a donation tier', 'Requires the `system_configuration.create` permission.')]
    #[Authenticated]
    #[BodyParam('name', 'string', 'Tier name; must be unique.', required: true, example: 'Alumni Gold Tier')]
    #[BodyParam('minimum_amount', 'number', 'Minimum lifetime NGN total to qualify for this tier.', required: true, example: 1000000)]
    #[BodyParam('maximum_amount', 'number', 'Upper display bound for this tier; must be greater than minimum_amount.', required: true, example: 5000000)]
    #[BodyParam('badge', 'string', 'Tier badge image: file upload, URL, or base64. JPG/PNG/WEBP, max 1MB, exactly 512x512px.', required: true)]
    #[Response(status: 201, content: [
        'error' => false,
        'message' => 'Donation tier created.',
        'data' => [
            'uuid' => 'f8a85dba-9add-4871-b9f9-2779ac6738b9',
            'code' => 'NHEF-AD-F8A85D',
            'name' => 'Alumni Gold Tier',
            'minimum_amount_formatted' => 'NGN 1,000,000.00',
            'maximum_amount_formatted' => 'NGN 5,000,000.00',
            'status' => 'active',
        ],
    ], description: 'Tier created.')]
    #[Response(status: 422, content: [
        'error' => true,
        'message' => 'The name has already been taken.',
        'data' => null,
    ], description: 'Validation error.')]
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

    #[Group('Donation Tier Configuration', 'Create and manage donation tiers to provide donors with predefined giving options and associated benefits or recognition levels. Postman folder: Donation Tier Configuration.')]
    #[Endpoint('List donation tiers', 'Requires the `system_configuration.read` permission. Pass `export=csv` or `export=pdf` to download instead of JSON.')]
    #[Authenticated]
    #[QueryParam('search', 'string', 'Matches tier name.', required: false, example: 'Gold')]
    #[QueryParam('filters[status]', 'string', 'active or inactive.', required: false, example: 'active')]
    #[QueryParam('sort_by', 'string', 'One of: name, value, updated_at.', required: false, example: 'updated_at')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'integer', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('export', 'string', 'Set to `csv` or `pdf` to download instead of receiving JSON.', required: false, example: 'csv')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Donation tiers retrieved.',
        'data' => [
            'current_page' => 1,
            'per_page' => 15,
            'total' => 6,
            'last_page' => 1,
            'data' => [
                ['uuid' => 'b30b75c8-d73c-431c-9b5b-9c61fa7ea55e', 'code' => 'NHEF-AD-B30B75', 'name' => 'Bronze Benefactor', 'minimum_amount_formatted' => 'NGN 500,000.00', 'status' => 'active'],
            ],
        ],
    ], description: 'Paginated tier list.')]
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

    #[Group('Donation Tier Configuration', 'Create and manage donation tiers to provide donors with predefined giving options and associated benefits or recognition levels. Postman folder: Donation Tier Configuration.')]
    #[Endpoint('View a donation tier', 'Basic Details for one tier: thresholds, badge, creator, and alumni/institution counts. Requires the `system_configuration.read` permission.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Donation tier UUID.', required: true, example: 'b30b75c8-d73c-431c-9b5b-9c61fa7ea55e')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Donation tier retrieved.',
        'data' => [
            'uuid' => 'b30b75c8-d73c-431c-9b5b-9c61fa7ea55e',
            'code' => 'NHEF-AD-B30B75',
            'name' => 'Bronze Benefactor',
            'minimum_amount_formatted' => 'NGN 500,000.00',
            'maximum_amount_formatted' => null,
            'status' => 'active',
            'created_by' => ['name' => 'Jide Adeola', 'role' => 'Fin Manager', 'label' => 'Jide Adeola (Fin Manager) ; ID:89301'],
            'created_at' => '2026-08-10T08:48:56.000000Z',
            'alumni_count' => 2,
            'institution_count' => 2,
        ],
    ], description: 'Tier found.')]
    #[Response(status: 404, content: [
        'error' => true,
        'message' => 'Donation tier not found.',
        'data' => null,
    ], description: 'No tier with that UUID exists.')]
    public function show(string $uuid)
    {
        try {
            $tier = $this->tierService->detailForAdmin($uuid);

            return JsonResponser::send(false, 'Donation tier retrieved.', DonorTierDetailResource::make($tier)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\DonorTierController@show');
        }
    }

    #[Group('Donation Tier Configuration', 'Create and manage donation tiers to provide donors with predefined giving options and associated benefits or recognition levels. Postman folder: Donation Tier Configuration.')]
    #[Endpoint('Edit a donation tier', 'Partial update; only send the fields being changed. Requires the `system_configuration.update` permission.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Donation tier UUID.', required: true, example: 'b30b75c8-d73c-431c-9b5b-9c61fa7ea55e')]
    #[BodyParam('name', 'string', 'Tier name; must stay unique.', required: false, example: 'Alumni Gold Tier')]
    #[BodyParam('minimum_amount', 'number', 'Minimum lifetime NGN total to qualify.', required: false, example: 1000000)]
    #[BodyParam('maximum_amount', 'number', 'Upper display bound; must be greater than minimum_amount.', required: false, example: 5000000)]
    #[BodyParam('badge', 'string', 'New tier badge: file upload, URL, or base64. JPG/PNG/WEBP, max 1MB, exactly 512x512px.', required: false)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Donation tier updated.',
        'data' => ['uuid' => 'b30b75c8-d73c-431c-9b5b-9c61fa7ea55e', 'name' => 'Alumni Gold Tier'],
    ], description: 'Tier updated.')]
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

    #[Group('Donation Tier Configuration', 'Create and manage donation tiers to provide donors with predefined giving options and associated benefits or recognition levels. Postman folder: Donation Tier Configuration.')]
    #[Endpoint('Deactivate / reactivate a donation tier', 'Flips the tier\'s current status; the same endpoint backs both the "Deactivate" and "Reactivate" actions. Requires the `system_configuration.update` permission.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Donation tier UUID.', required: true, example: 'b30b75c8-d73c-431c-9b5b-9c61fa7ea55e')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Donation tier deactivated.',
        'data' => ['uuid' => 'b30b75c8-d73c-431c-9b5b-9c61fa7ea55e', 'status' => 'inactive'],
    ], description: 'Status flipped.')]
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

    #[Group('Donation Tier Configuration', 'Create and manage donation tiers to provide donors with predefined giving options and associated benefits or recognition levels. Postman folder: Donation Tier Configuration.')]
    #[Endpoint('Delete a donation tier', 'Permanently removes the tier. Requires the `system_configuration.delete` permission.')]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Donation tier UUID.', required: true, example: 'b30b75c8-d73c-431c-9b5b-9c61fa7ea55e')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Donation tier deleted.',
        'data' => null,
    ], description: 'Tier deleted.')]
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

    #[Group('Donation Tier Configuration', 'Create and manage donation tiers to provide donors with predefined giving options and associated benefits or recognition levels. Postman folder: Donation Tier Configuration.')]
    #[Endpoint('List alumni in a donation tier', "Donors whose lifetime NGN total currently falls in this tier's bracket, with donation count, lifetime total, and the date they upgraded into it. Requires the `system_configuration.read` permission. Pass `export=csv` or `export=pdf` to download instead of JSON.")]
    #[Authenticated]
    #[UrlParam('uuid', 'string', 'Donation tier UUID.', required: true, example: 'b30b75c8-d73c-431c-9b5b-9c61fa7ea55e')]
    #[QueryParam('search', 'string', 'Matches alumni name or email.', required: false, example: 'Ibadan')]
    #[QueryParam('institution', 'string', 'Filter by exact university name.', required: false, example: 'University of Ibadan')]
    #[QueryParam('page', 'integer', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('export', 'string', 'Set to `csv` or `pdf` to download instead of receiving JSON.', required: false, example: 'csv')]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Tier alumni retrieved.',
        'data' => [
            'current_page' => 1,
            'per_page' => 15,
            'total' => 2,
            'last_page' => 1,
            'data' => [
                [
                    'uuid' => 'e94b235f-b695-4363-9cb7-d6c538b704d6',
                    'name' => 'Alumni University of Ibadan',
                    'institution' => 'University of Ibadan',
                    'donations_count' => 1,
                    'lifetime_total_formatted' => 'NGN 980,000.00',
                    'upgraded_at' => '2026-08-12T11:22:58.000000Z',
                ],
            ],
        ],
    ], description: 'Paginated alumni list for this tier.')]
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

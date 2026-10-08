<?php

namespace App\Http\Controllers\v1\Admin\SystemConfiguration;

use App\Helpers\GeneralHelper;
use App\Helpers\PDFReportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemConfiguration\ConstituencyTypeListRequest;
use App\Http\Requests\Admin\SystemConfiguration\CreateConstituencyTypeRequest;
use App\Http\Requests\Admin\SystemConfiguration\UpdateConstituencyTypeRequest;
use App\Http\Resources\Admin\SystemConfiguration\ConstituencyTypeAdminResource;
use App\Http\Resources\Admin\SystemConfiguration\ConstituencyTypeDetailResource;
use App\Models\Admin;
use App\Models\ConstituencyType;
use App\Responser\JsonResponser;
use App\Services\ConstituencyType\ConstituencyTypeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConstituencyTypeController extends Controller
{
    public function __construct(
        private readonly ConstituencyTypeService $typeService,
        private readonly PDFReportHelper $pdfReportHelper,
    ) {}

    public function store(CreateConstituencyTypeRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $type = $this->typeService->create($request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Constituency type created.', ConstituencyTypeAdminResource::make($type)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\ConstituencyTypeController@store');
        }
    }

    public function index(ConstituencyTypeListRequest $request)
    {
        try {
            $filters = $request->validated();
            $export = $filters['export'] ?? null;

            return match ($export) {
                'csv' => $this->respondCsv($filters),
                'pdf' => $this->respondPdf($filters),
                default => JsonResponser::send(false, 'Constituency types retrieved.', $this->paginatedPayload(
                    $this->typeService->paginateForAdmin($filters),
                    ConstituencyTypeAdminResource::class
                )),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\ConstituencyTypeController@index');
        }
    }

    private function respondCsv(array $filters): StreamedResponse
    {
        /** @var Collection<int, ConstituencyType> $collection */
        [$collection, $truncated] = $this->typeService->exportForAdmin($filters);

        $filename = 'constituency-types-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($collection): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Type Name', 'Date Created', 'Last Updated', 'Status']);

            foreach ($collection as $type) {
                fputcsv($out, $this->typeTabularRow($type));
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    private function respondPdf(array $filters)
    {
        /** @var Collection<int, ConstituencyType> $collection */
        [$collection, $truncated] = $this->typeService->exportForAdmin($filters);

        $filename = 'constituency-types-'.now()->format('Y-m-d-His').'.pdf';
        $headings = ['Type Name', 'Date Created', 'Last Updated', 'Status'];
        $rows = $collection->values()->map(fn (ConstituencyType $type): array => $this->typeTabularRow($type));

        return $this->pdfReportHelper->download(
            rows: $rows,
            headings: $headings,
            title: 'Constituency Types',
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
    private function typeTabularRow(ConstituencyType $type): array
    {
        return [
            $type->name,
            $type->created_at?->toIso8601String() ?? '',
            $type->updated_at?->toIso8601String() ?? '',
            $type->is_active ? 'Active' : 'Deactivated',
        ];
    }

    public function show(string $uuid)
    {
        try {
            $type = $this->typeService->detailForAdmin($uuid);

            return JsonResponser::send(false, 'Constituency type retrieved.', ConstituencyTypeDetailResource::make($type)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\ConstituencyTypeController@show');
        }
    }

    public function update(UpdateConstituencyTypeRequest $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $type = $this->typeService->update($uuid, $request->validated(), $admin, $request);

            return JsonResponser::send(false, 'Constituency type updated.', ConstituencyTypeAdminResource::make($type)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\ConstituencyTypeController@update');
        }
    }

    public function toggleStatus(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $type = $this->typeService->toggleActiveStatus($uuid, $admin, $request);
            $message = $type->is_active ? 'Constituency type reactivated.' : 'Constituency type deactivated.';

            return JsonResponser::send(false, $message, ConstituencyTypeAdminResource::make($type)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\ConstituencyTypeController@toggleStatus');
        }
    }

    public function destroy(Request $request, string $uuid)
    {
        try {
            $admin = $this->requireAdmin($request);
            $this->typeService->delete($uuid, $admin, $request);

            return JsonResponser::send(false, 'Constituency type deleted.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\SystemConfiguration\ConstituencyTypeController@destroy');
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

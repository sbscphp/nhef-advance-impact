<?php

namespace App\Http\Controllers\v1\Admin\Reporting;

use App\Enums\ReportDatasetEnum;
use App\Helpers\GeneralHelper;
use App\Helpers\PDFReportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reporting\DistributionReportRequest;
use App\Http\Requests\Admin\Reporting\GenerateReportRequest;
use App\Http\Requests\Admin\Reporting\PreviewReportRequest;
use App\Http\Requests\Admin\Reporting\ReportFieldsRequest;
use App\Http\Requests\Admin\Reporting\ReportHistoryListRequest;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Http\Resources\Reporting\GeneratedReportResource;
use App\Models\Admin;
use App\Models\GeneratedReport;
use App\Responser\JsonResponser;
use App\Services\Reporting\ReportBuilderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportBuilderService $reportService,
        private readonly PDFReportHelper $pdfReportHelper,
    ) {}

    public function metadata()
    {
        try {
            $datasets = array_map(fn (ReportDatasetEnum $dataset): array => [
                'value' => $dataset->value,
                'label' => $dataset->label(),
                'description' => $dataset->description(),
            ], ReportDatasetEnum::cases());

            return JsonResponser::send(false, 'Report metadata retrieved.', [
                'datasets' => $datasets,
                'periods' => ListingFilterRules::periodOptions(),
                'formats' => [
                    ['value' => 'csv', 'label' => 'Excel (CSV File)'],
                    ['value' => 'pdf', 'label' => 'PDF'],
                ],
            ]);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Reporting\ReportController@metadata');
        }
    }

    public function fields(ReportFieldsRequest $request)
    {
        try {
            $dataset = $request->validated()['dataset'];

            return JsonResponser::send(false, 'Report fields retrieved.', [
                'fields' => $this->reportService->fieldsForDataset($dataset),
                'groupable_fields' => $this->reportService->groupableFieldsForDataset($dataset),
            ]);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Reporting\ReportController@fields');
        }
    }

    public function distribution(DistributionReportRequest $request)
    {
        try {
            $validated = $request->validated();

            $distribution = $this->reportService->distribution(
                $validated['dataset'],
                $validated['field'],
                $validated['period'] ?? null,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null,
            );

            return JsonResponser::send(false, 'Report distribution retrieved.', ['distribution' => $distribution]);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Reporting\ReportController@distribution');
        }
    }

    public function preview(PreviewReportRequest $request)
    {
        try {
            $filters = $request->validated();
            $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

            $paginator = $this->reportService->preview(
                $filters['dataset'],
                $filters['fields'],
                $filters['period'] ?? null,
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null,
                $filters['search'] ?? null,
                $perPage,
            );

            return JsonResponser::send(false, 'Report preview retrieved.', $paginator->toArray());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Reporting\ReportController@preview');
        }
    }

    public function store(GenerateReportRequest $request)
    {
        try {
            $admin = $this->requireAdmin($request);
            $validated = $request->validated();
            $result = $this->reportService->generate($admin, $validated);

            $fieldLabels = $this->fieldLabels($result['report']->dataset, $result['report']->fields);

            return $validated['format'] === 'pdf'
                ? $this->respondPdf($result['report'], $result['rows'], $fieldLabels, $result['truncated'])
                : $this->respondCsv($result['report'], $result['rows'], $fieldLabels, $result['truncated']);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Reporting\ReportController@store');
        }
    }

    public function index(ReportHistoryListRequest $request)
    {
        try {
            $filters = $request->validated();

            return JsonResponser::send(false, 'Report history retrieved.', $this->paginatedPayload(
                $this->reportService->paginateHistory($filters),
                GeneratedReportResource::class
            ));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Reporting\ReportController@index');
        }
    }

    public function showPreview(Request $request, string $uuid)
    {
        try {
            $perPage = max(1, min((int) $request->query('per_page', 15), 100));
            $paginator = $this->reportService->previewSaved($uuid, $perPage);

            return JsonResponser::send(false, 'Report preview retrieved.', $paginator->toArray());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Reporting\ReportController@showPreview');
        }
    }

    public function download(Request $request, string $uuid)
    {
        try {
            $validated = Validator::make($request->all(), [
                'format' => ['required', 'in:csv,pdf'],
            ])->validate();

            $report = $this->reportService->findForAdmin($uuid);
            $export = $this->reportService->exportRows($report);
            $fieldLabels = $this->fieldLabels($report->dataset, $report->fields);

            return $validated['format'] === 'pdf'
                ? $this->respondPdf($report, $export['rows'], $fieldLabels, $export['truncated'])
                : $this->respondCsv($report, $export['rows'], $fieldLabels, $export['truncated']);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Reporting\ReportController@download');
        }
    }

    /**
     * @return array<string, string>
     */
    private function fieldLabels(string $dataset, array $fieldKeys): array
    {
        $grouped = $this->reportService->fieldsForDataset($dataset);
        $byKey = [];

        foreach ($grouped as $group) {
            foreach ($group as $field) {
                $byKey[$field['key']] = $field['label'];
            }
        }

        $labels = [];
        foreach ($fieldKeys as $key) {
            $labels[$key] = $byKey[$key] ?? $key;
        }

        return $labels;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $fieldLabels
     */
    private function respondCsv(GeneratedReport $report, Collection $rows, array $fieldLabels, bool $truncated): StreamedResponse
    {
        $filename = Str::slug($report->name).'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows, $fieldLabels): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($fieldLabels));

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn (string $key) => $row[$key] ?? '', array_keys($fieldLabels)));
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $fieldLabels
     */
    private function respondPdf(GeneratedReport $report, Collection $rows, array $fieldLabels, bool $truncated)
    {
        $filename = Str::slug($report->name).'-'.now()->format('Y-m-d-His').'.pdf';
        $headings = array_values($fieldLabels);
        $tabularRows = $rows->map(fn (array $row): array => array_map(fn (string $key) => (string) ($row[$key] ?? ''), array_keys($fieldLabels)));

        return $this->pdfReportHelper->download(
            rows: $tabularRows,
            headings: $headings,
            title: $report->name,
            filename: $filename,
            orientation: 'portrait',
            periodStart: $report->start_date?->toDateString() ?? 'All dates',
            periodEnd: $report->end_date?->toDateString() ?? 'All dates',
            generatedAt: now((string) config('app.timezone')),
            truncated: $truncated,
            includedRows: $tabularRows->count(),
        );
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

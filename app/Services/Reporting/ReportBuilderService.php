<?php

namespace App\Services\Reporting;

use App\Enums\ReportDatasetEnum;
use App\Exceptions\ApiException;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Admin;
use App\Models\CustomFieldDefinition;
use App\Models\GeneratedReport;
use App\Models\Institution;
use App\Repositories\Contracts\CustomField\CustomFieldDefinitionRepositoryInterface;
use App\Repositories\Contracts\CustomField\CustomFieldValueRepositoryInterface;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use App\Repositories\Contracts\Reporting\GeneratedReportRepositoryInterface;
use App\Services\Reporting\Datasets\HasMonetaryFields;
use App\Services\Reporting\Datasets\PreparesReportRecords;
use App\Services\Reporting\Datasets\ReportDatasetInterface;
use App\Support\ViewerVisibility;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportBuilderService
{
    private const MAX_EXPORT_ROWS = 5000;

    public function __construct(
        private readonly ReportDatasetResolver $datasetResolver,
        private readonly CustomFieldDefinitionRepositoryInterface $customFieldDefinitionRepository,
        private readonly CustomFieldValueRepositoryInterface $customFieldValueRepository,
        private readonly GeneratedReportRepositoryInterface $reportRepository,
        private readonly InstitutionRepositoryInterface $institutionRepository,
    ) {}

    /**
     * @return array<string, list<array{key: string, label: string, type: string}>>
     */
    public function fieldsForDataset(string $datasetKey): array
    {
        $dataset = $this->datasetResolver->make($datasetKey);
        $fields = $this->withoutHiddenNativeFields($dataset, $dataset->nativeFields());

        $module = ReportDatasetEnum::from($datasetKey)->customFieldModule();

        if ($module !== null) {
            $customDefinitions = $this->customFieldDefinitionRepository->reportableForModule($module);

            if ($customDefinitions->isNotEmpty()) {
                $fields['Custom Fields'] = $customDefinitions->map(fn (CustomFieldDefinition $definition): array => [
                    'key' => 'custom:'.$definition->uuid,
                    'label' => $definition->name,
                    'type' => $definition->type,
                ])->all();
            }
        }

        return $fields;
    }

    /**
     * Drops fields the viewer may not see (monetary columns) from a requested field list.
     *
     * @param  list<string>  $fieldKeys
     * @return list<string>
     */
    public function visibleFieldKeys(string $datasetKey, array $fieldKeys): array
    {
        $hidden = $this->hiddenFieldKeys($this->datasetResolver->make($datasetKey));

        return array_values(array_diff($fieldKeys, $hidden));
    }

    /**
     * @param  array<string, list<array{key: string, label: string, type: string}>>  $fields
     * @return array<string, list<array{key: string, label: string, type: string}>>
     */
    private function withoutHiddenNativeFields(ReportDatasetInterface $dataset, array $fields): array
    {
        $hidden = $this->hiddenFieldKeys($dataset);

        return array_filter(array_map(
            fn (array $group): array => array_values(array_filter($group, fn (array $field): bool => ! in_array($field['key'], $hidden, true))),
            $fields,
        ));
    }

    /** @return list<string> */
    private function hiddenFieldKeys(ReportDatasetInterface $dataset): array
    {
        return $dataset instanceof HasMonetaryFields && ! ViewerVisibility::canSeeMoney()
            ? $dataset->monetaryFieldKeys()
            : [];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function groupableFieldsForDataset(string $datasetKey): array
    {
        return $this->datasetResolver->make($datasetKey)->groupableFields();
    }

    /**
     * Groups matching records by a groupable field and counts them; feeds a distribution/count
     * chart (e.g. "Alumni distribution by graduation year").
     *
     * @return list<array{label: string, count: int}>
     */
    public function distribution(string $datasetKey, string $fieldKey, ?string $period, ?string $startDate, ?string $endDate): array
    {
        $dataset = $this->datasetResolver->make($datasetKey);
        $field = collect($dataset->groupableFields())->firstWhere('key', $fieldKey);

        if ($field === null) {
            throw new ApiException('"'.$fieldKey.'" is not a groupable field for this dataset.', 422);
        }

        [$start, $end] = $this->resolveRange($period, $startDate, $endDate);
        $column = $dataset->groupableColumn($fieldKey);
        $isBoolean = $field['type'] === 'boolean';

        return $dataset->aggregateQuery($start, $end)
            ->select($column.' as label')
            ->selectRaw('COUNT(*) as count')
            ->groupBy($column)
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'label' => $this->distributionLabel($row->label, $isBoolean),
                'count' => (int) $row->count,
            ])
            ->all();
    }

    private function distributionLabel(mixed $rawValue, bool $isBoolean): string
    {
        if ($rawValue === null) {
            return 'Unspecified';
        }

        return $isBoolean ? ((bool) $rawValue ? 'Yes' : 'No') : (string) $rawValue;
    }

    /**
     * @param  list<string>  $fieldKeys
     */
    public function preview(string $datasetKey, array $fieldKeys, ?string $period, ?string $startDate, ?string $endDate, ?string $search, ?string $institutionUuid, int $perPage): LengthAwarePaginator
    {
        $dataset = $this->datasetResolver->make($datasetKey);
        [$start, $end] = $this->resolveRange($period, $startDate, $endDate);
        [$nativeKeys, $customDefinitions] = $this->splitFieldKeys($this->visibleFieldKeys($datasetKey, $fieldKeys));
        $institution = $this->landlordGuardedInstitution($institutionUuid);

        $paginator = $this->withInstitutionScope(
            $institution,
            fn () => $dataset->query($start, $end, $search)->paginate($perPage),
        );
        $paginator->setCollection($this->mapRows($dataset, $paginator->getCollection(), $nativeKeys, $customDefinitions));

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{report: GeneratedReport, rows: Collection<int, array<string, mixed>>, truncated: bool}
     */
    public function generate(Admin $actor, array $payload): array
    {
        [$start, $end] = $this->resolveRange($payload['period'] ?? null, $payload['start_date'] ?? null, $payload['end_date'] ?? null);
        $datasetEnum = ReportDatasetEnum::from($payload['dataset']);
        $institution = $this->landlordGuardedInstitution($payload['institution_uuid'] ?? null);

        $report = $this->reportRepository->create([
            'name' => filled($payload['name'] ?? null) ? $payload['name'] : $datasetEnum->label().' Report - '.now()->format('M d, Y'),
            'dataset' => $payload['dataset'],
            'fields' => $this->visibleFieldKeys($payload['dataset'], $payload['fields']),
            'search' => $payload['search'] ?? null,
            'institution_uuid' => $institution?->uuid,
            'period' => $payload['period'] ?? null,
            'start_date' => $start?->toDateString(),
            'end_date' => $end?->toDateString(),
            'created_by' => $actor->uuid,
        ]);

        $export = $this->exportRows($report);

        return ['report' => $report, 'rows' => $export['rows'], 'truncated' => $export['truncated']];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateHistory(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->reportRepository->paginateForAdmin([...$filters, 'exclude_datasets' => ReportDatasetEnum::hiddenFromViewer()], $perPage);
    }

    public function findForAdmin(string $uuid): GeneratedReport
    {
        $report = $this->reportRepository->findByUuid($uuid);

        if (! $report instanceof GeneratedReport) {
            throw new ApiException('Report not found.', 404);
        }

        return $report;
    }

    /**
     * $searchOverride/$institutionUuidOverride let the viewer re-filter an already-generated
     * report's preview on the fly, without mutating the values the report was originally
     * generated with. A tenant-scoped viewer can never override institution - see
     * {@see self::landlordGuardedInstitution()}.
     */
    public function previewSaved(string $uuid, int $perPage, ?string $searchOverride = null, ?string $institutionUuidOverride = null): LengthAwarePaginator
    {
        $report = $this->findForAdmin($uuid);
        $dataset = $this->datasetResolver->make($report->dataset);
        [$nativeKeys, $customDefinitions] = $this->splitFieldKeys($this->visibleFieldKeys($report->dataset, $report->fields));

        $search = $searchOverride ?? $report->search;
        $institution = $this->landlordGuardedInstitution($institutionUuidOverride ?? $report->institution_uuid);

        $paginator = $this->withInstitutionScope(
            $institution,
            fn () => $dataset->query($report->start_date, $report->end_date, $search)->paginate($perPage),
        );
        $paginator->setCollection($this->mapRows($dataset, $paginator->getCollection(), $nativeKeys, $customDefinitions));

        return $paginator;
    }

    /**
     * $searchOverride/$institutionUuidOverride let a download re-filter an already-generated
     * report on the fly, same as {@see self::previewSaved()} - without them, falls back to the
     * report's own saved `search`/`institution_uuid`.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, truncated: bool}
     */
    public function exportRows(GeneratedReport $report, ?string $searchOverride = null, ?string $institutionUuidOverride = null): array
    {
        $dataset = $this->datasetResolver->make($report->dataset);
        [$nativeKeys, $customDefinitions] = $this->splitFieldKeys($this->visibleFieldKeys($report->dataset, $report->fields));

        $search = $searchOverride ?? $report->search;
        $institution = $this->landlordGuardedInstitution($institutionUuidOverride ?? $report->institution_uuid);

        [$records, $truncated] = $this->withInstitutionScope($institution, function () use ($dataset, $report, $search) {
            $query = $dataset->query($report->start_date, $report->end_date, $search);
            $truncated = (clone $query)->count() > self::MAX_EXPORT_ROWS;

            return [$query->limit(self::MAX_EXPORT_ROWS)->get(), $truncated];
        });

        return ['rows' => $this->mapRows($dataset, $records, $nativeKeys, $customDefinitions), 'truncated' => $truncated];
    }

    /**
     * Resolves an institution filter to an {@see Institution} model - null when none was given,
     * and forced to null for a viewer who is already tenant-scoped (an Institution Admin), who
     * must never be able to switch the query's scope to a DIFFERENT institution via this filter.
     * Mirrors {@see ReportDatasetEnum} landlord-only semantics.
     */
    private function landlordGuardedInstitution(?string $uuid): ?Institution
    {
        if ($uuid === null || Institution::checkCurrent()) {
            return null;
        }

        return $this->institutionRepository->findByUuid($uuid);
    }

    /**
     * Runs $callback with one institution temporarily set as the "current" tenant, so every
     * dataset's underlying model scopes to it automatically via its existing
     * BelongsToTenant/OwnedByInstitution global scope - no changes needed to any individual
     * dataset's query() method. Restores whatever tenant (if any) was current before, rather than
     * assuming it was unscoped.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withInstitutionScope(?Institution $institution, callable $callback): mixed
    {
        if ($institution === null) {
            return $callback();
        }

        $previous = Institution::current();
        $institution->makeCurrent();

        try {
            return $callback();
        } finally {
            $previous instanceof Institution ? $previous->makeCurrent() : Institution::forgetCurrent();
        }
    }

    /**
     * @param  list<string>  $fieldKeys
     * @return array{0: list<string>, 1: Collection<int, CustomFieldDefinition>}
     */
    private function splitFieldKeys(array $fieldKeys): array
    {
        $native = [];
        $customUuids = [];

        foreach ($fieldKeys as $key) {
            if (str_starts_with($key, 'custom:')) {
                $customUuids[] = substr($key, 7);
            } else {
                $native[] = $key;
            }
        }

        $customDefinitions = $customUuids === []
            ? new Collection
            : CustomFieldDefinition::query()->whereIn('uuid', $customUuids)->get();

        return [$native, $customDefinitions];
    }

    /**
     * @param  Collection<int, Model>  $records
     * @param  list<string>  $nativeKeys
     * @param  Collection<int, CustomFieldDefinition>  $customDefinitions
     * @return Collection<int, array<string, mixed>>
     */
    private function mapRows(ReportDatasetInterface $dataset, Collection $records, array $nativeKeys, Collection $customDefinitions): Collection
    {
        if ($dataset instanceof PreparesReportRecords) {
            $dataset->prepareRecords($records);
        }

        $valuesByRecordAndDefinition = [];

        if ($customDefinitions->isNotEmpty()) {
            $fieldableIds = $records->map(fn ($record) => $dataset->fieldableId($record))->unique()->values()->all();
            $values = $this->customFieldValueRepository->valuesForFieldableIds($dataset->fieldableMorphClass(), $fieldableIds);

            foreach ($values as $value) {
                $valuesByRecordAndDefinition[$value->fieldable_id][$value->custom_field_definition_id] = $value->value;
            }
        }

        return $records->map(function ($record) use ($dataset, $nativeKeys, $customDefinitions, $valuesByRecordAndDefinition): array {
            $row = $dataset->mapRow($record, $nativeKeys);
            $fieldableId = $dataset->fieldableId($record);

            foreach ($customDefinitions as $definition) {
                $row['custom:'.$definition->uuid] = $valuesByRecordAndDefinition[$fieldableId][$definition->id] ?? null;
            }

            return $row;
        })->values();
    }

    /**
     * @return array{0: ?CarbonInterface, 1: ?CarbonInterface}
     */
    private function resolveRange(?string $period, ?string $startDate, ?string $endDate): array
    {
        $window = ListingFilterRules::resolveDateWindow([
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return [$window['start'], $window['end']];
    }
}

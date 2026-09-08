<?php

namespace App\Services\Reporting\Datasets;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface ReportDatasetInterface
{
    public function label(): string;

    /**
     * @return array<string, list<array{key: string, label: string, type: string}>>
     */
    public function nativeFields(): array;

    /**
     * Scoped by date range and search, not yet paginated/limited.
     *
     * @return Builder<Model>
     */
    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder;

    /**
     * @param  list<string>  $nativeFieldKeys
     * @return array<string, mixed>
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array;

    /** The morph class custom field values for this dataset are stored against. */
    public function fieldableMorphClass(): string;

    /**
     * The id to join custom field values on; usually $record->getKey(), but differs when the
     * queried record isn't the same model custom values were saved against (Donation reports
     * query DonationPayment rows, but values are saved against the parent Donation).
     */
    public function fieldableId(Model $record): int;

    /**
     * Plain, discrete columns on this dataset's own base table that a distribution chart can
     * group by (no relations, no computed values, no custom fields).
     *
     * @return list<array{key: string, label: string, type: string}>
     */
    public function groupableFields(): array;

    /** The real column name backing a groupable field key. */
    public function groupableColumn(string $key): string;

    /**
     * Scoped by date range only (no eager loads/counts/search) - the plain base query a
     * GROUP BY distribution runs against.
     *
     * @return Builder<Model>
     */
    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder;
}

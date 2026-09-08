<?php

namespace App\Repositories\Contracts\Reporting;

use App\Models\GeneratedReport;
use Illuminate\Pagination\LengthAwarePaginator;

interface GeneratedReportRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): GeneratedReport;

    public function findByUuid(string $uuid): ?GeneratedReport;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator;
}

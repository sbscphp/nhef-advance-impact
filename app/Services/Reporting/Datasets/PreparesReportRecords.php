<?php

namespace App\Services\Reporting\Datasets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** For datasets whose columns need a batched lookup across the whole page/export instead of one query per row. */
interface PreparesReportRecords
{
    /**
     * @param  Collection<int, Model>  $records
     */
    public function prepareRecords(Collection $records): void;
}

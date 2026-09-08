<?php

namespace App\Services\Reporting\Datasets\Concerns;

use App\Exceptions\ApiException;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BuildsSimpleAggregateQuery
{
    /**
     * @param  class-string<Model>  $modelClass
     * @return Builder<Model>
     */
    private function simpleAggregateQuery(string $modelClass, string $dateColumn, ?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $modelClass::query()
            ->when($start !== null, fn ($query) => $query->where($dateColumn, '>=', $start))
            ->when($end !== null, fn ($query) => $query->where($dateColumn, '<=', $end));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function resolveGroupableColumn(array $map, string $key): string
    {
        if (! isset($map[$key])) {
            throw new ApiException('"'.$key.'" is not a groupable field for this dataset.', 422);
        }

        return $map[$key];
    }
}

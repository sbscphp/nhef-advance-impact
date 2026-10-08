<?php

namespace App\Services\Reporting\Datasets;

/** For datasets with money columns, so they can be withheld from viewers without monetary access. */
interface HasMonetaryFields
{
    /** @return list<string> */
    public function monetaryFieldKeys(): array;
}

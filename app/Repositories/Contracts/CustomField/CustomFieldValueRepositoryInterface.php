<?php

namespace App\Repositories\Contracts\CustomField;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface CustomFieldValueRepositoryInterface
{
    /**
     * @return Collection<int, CustomFieldValue>
     */
    public function valuesForFieldable(Model $fieldable): Collection;

    public function upsert(CustomFieldDefinition $definition, Model $fieldable, mixed $value): CustomFieldValue;

    /**
     * All values for many records of the same type at once; feeds a report row's custom-field
     * columns without an N+1 query per row.
     *
     * @param  list<int>  $fieldableIds
     * @return Collection<int, CustomFieldValue>
     */
    public function valuesForFieldableIds(string $fieldableType, array $fieldableIds): Collection;
}

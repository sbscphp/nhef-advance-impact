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
}

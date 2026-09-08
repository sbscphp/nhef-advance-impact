<?php

namespace App\Repositories\CustomField;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Repositories\Contracts\CustomField\CustomFieldValueRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CustomFieldValueRepository implements CustomFieldValueRepositoryInterface
{
    public function valuesForFieldable(Model $fieldable): Collection
    {
        return CustomFieldValue::query()
            ->where('fieldable_type', $fieldable->getMorphClass())
            ->where('fieldable_id', $fieldable->getKey())
            ->get();
    }

    public function upsert(CustomFieldDefinition $definition, Model $fieldable, mixed $value): CustomFieldValue
    {
        return CustomFieldValue::updateOrCreate(
            [
                'custom_field_definition_id' => $definition->id,
                'fieldable_type' => $fieldable->getMorphClass(),
                'fieldable_id' => $fieldable->getKey(),
            ],
            ['value' => $value]
        );
    }
}

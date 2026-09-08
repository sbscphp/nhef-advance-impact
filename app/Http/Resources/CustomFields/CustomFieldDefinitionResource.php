<?php

namespace App\Http\Resources\CustomFields;

use App\Enums\CustomFieldTypeEnum;
use App\Enums\ModuleEnums;
use App\Models\CustomFieldDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomFieldDefinition */
class CustomFieldDefinitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = CustomFieldTypeEnum::from($this->type);

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $type->value,
            'type_label' => $type->label(),
            'applicable_modules' => $this->applicable_modules,
            'applicable_module_labels' => array_map(
                fn (string $module): string => ModuleEnums::from($module)->label(),
                $this->applicable_modules,
            ),
            'options' => $this->options,
            'is_required' => $this->is_required,
            'is_searchable' => $this->is_searchable,
            'is_filterable' => $this->is_filterable,
            'show_in_reports' => $this->show_in_reports,
            'status' => $this->status,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'uuid' => $this->creator->uuid,
                'name' => $this->creator->displayName(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

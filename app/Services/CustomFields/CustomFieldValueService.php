<?php

namespace App\Services\CustomFields;

use App\Enums\CustomFieldStatusEnum;
use App\Enums\CustomFieldTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Models\CustomFieldDefinition;
use App\Models\User;
use App\Repositories\Contracts\CustomField\CustomFieldDefinitionRepositoryInterface;
use App\Repositories\Contracts\CustomField\CustomFieldValueRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class CustomFieldValueService
{
    public function __construct(
        private readonly CustomFieldDefinitionRepositoryInterface $definitionRepository,
        private readonly CustomFieldValueRepositoryInterface $valueRepository,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForFieldable(Model $fieldable, string $module): array
    {
        $definitions = $this->definitionRepository->activeForModule($module);
        $values = $this->valueRepository->valuesForFieldable($fieldable)->keyBy('custom_field_definition_id');

        return $definitions->map(fn (CustomFieldDefinition $definition): array => $this->present($definition, $values->get($definition->id)?->value))->all();
    }

    /**
     * Field definitions for a module before any record exists yet (e.g. a donation/event
     * registration form): no current value to show, since nothing has been created/saved.
     *
     * @return list<array<string, mixed>>
     */
    public function fieldsForModule(string $module): array
    {
        return $this->definitionRepository->activeForModule($module)
            ->map(fn (CustomFieldDefinition $definition): array => $this->present($definition, null))
            ->all();
    }

    /**
     * @param  list<array{custom_field_uuid: string, value: mixed}>  $entries
     * @return list<array<string, mixed>>
     */
    public function updateForFieldable(Model $fieldable, string $module, array $entries): array
    {
        foreach ($entries as $entry) {
            $definition = $this->definitionRepository->findByUuid($entry['custom_field_uuid']);

            if (! $definition instanceof CustomFieldDefinition || $definition->status !== CustomFieldStatusEnum::ACTIVE->value) {
                throw new ApiException('One of the submitted custom fields could not be found.', 404);
            }

            if (! in_array($module, $definition->applicable_modules, true)) {
                throw new ApiException('"'.$definition->name.'" is not applicable here.', 422);
            }

            $this->valueRepository->upsert($definition, $fieldable, $this->normalize($definition, $entry['value'] ?? null));
        }

        return $this->listForFieldable($fieldable, $module);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CustomFieldDefinition $definition, mixed $value): array
    {
        return [
            'uuid' => $definition->uuid,
            'name' => $definition->name,
            'description' => $definition->description,
            'type' => $definition->type,
            'type_label' => CustomFieldTypeEnum::from($definition->type)->label(),
            'options' => $definition->options,
            'is_required' => $definition->is_required,
            'value' => $value,
        ];
    }

    private function normalize(CustomFieldDefinition $definition, mixed $raw): mixed
    {
        $blank = $raw === null || $raw === '' || $raw === [];

        if ($blank) {
            if ($definition->is_required) {
                throw new ApiException('"'.$definition->name.'" is required.', 422);
            }

            return null;
        }

        return match (CustomFieldTypeEnum::from($definition->type)) {
            CustomFieldTypeEnum::TEXT => $this->stringValue($definition, $raw, 250),
            CustomFieldTypeEnum::LONG_TEXT => $this->stringValue($definition, $raw, 5000),
            CustomFieldTypeEnum::NUMBER => $this->numberValue($definition, $raw),
            CustomFieldTypeEnum::DATE => $this->dateValue($definition, $raw),
            CustomFieldTypeEnum::CHECKBOX => (bool) $raw,
            CustomFieldTypeEnum::DROPDOWN => $this->dropdownValue($definition, $raw),
            CustomFieldTypeEnum::MULTI_SELECT => $this->multiSelectValue($definition, $raw),
            CustomFieldTypeEnum::USER_LOOKUP => $this->userLookupValue($definition, $raw),
            CustomFieldTypeEnum::FILE_UPLOAD => $this->fileUploadValue($definition, $raw),
        };
    }

    private function stringValue(CustomFieldDefinition $definition, mixed $raw, int $max): string
    {
        if (! is_string($raw) || mb_strlen($raw) > $max) {
            throw new ApiException('"'.$definition->name.'" must be text up to '.$max.' characters.', 422);
        }

        return $raw;
    }

    private function numberValue(CustomFieldDefinition $definition, mixed $raw): int|float
    {
        if (! is_numeric($raw)) {
            throw new ApiException('"'.$definition->name.'" must be a number.', 422);
        }

        return $raw + 0;
    }

    private function dateValue(CustomFieldDefinition $definition, mixed $raw): string
    {
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            throw new ApiException('"'.$definition->name.'" must be a valid date.', 422);
        }
    }

    private function dropdownValue(CustomFieldDefinition $definition, mixed $raw): string
    {
        $options = $definition->options ?? [];

        if (! is_string($raw) || ! in_array($raw, $options, true)) {
            throw new ApiException('"'.$definition->name.'" must be one of: '.implode(', ', $options).'.', 422);
        }

        return $raw;
    }

    /**
     * @return list<string>
     */
    private function multiSelectValue(CustomFieldDefinition $definition, mixed $raw): array
    {
        $options = $definition->options ?? [];
        $values = is_array($raw) ? $raw : [$raw];

        foreach ($values as $value) {
            if (! is_string($value) || ! in_array($value, $options, true)) {
                throw new ApiException('"'.$definition->name.'" must only contain: '.implode(', ', $options).'.', 422);
            }
        }

        return array_values(array_unique($values));
    }

    private function userLookupValue(CustomFieldDefinition $definition, mixed $raw): string
    {
        if (! is_string($raw) || ! User::query()->where('uuid', $raw)->exists()) {
            throw new ApiException('"'.$definition->name.'" must reference a valid user.', 422);
        }

        return $raw;
    }

    private function fileUploadValue(CustomFieldDefinition $definition, mixed $raw): string
    {
        $url = FileUploadHelper::smartSingleFileUpload($raw, 'custom-fields/'.$definition->uuid);

        if ($url === null) {
            throw new ApiException('"'.$definition->name.'" must be a valid file.', 422);
        }

        return $url;
    }
}

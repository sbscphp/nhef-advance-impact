<?php

namespace App\Services\CustomFields;

use App\Enums\AuditActionEnum;
use App\Enums\CustomFieldStatusEnum;
use App\Enums\CustomFieldTypeEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\CustomFieldDefinition;
use App\Repositories\Contracts\CustomField\CustomFieldDefinitionRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomFieldDefinitionService
{
    public function __construct(
        private readonly CustomFieldDefinitionRepositoryInterface $definitionRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Admin $actor, Request $request): CustomFieldDefinition
    {
        $type = CustomFieldTypeEnum::from($payload['type']);

        $definition = $this->definitionRepository->create([
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'type' => $type->value,
            'applicable_modules' => $payload['applicable_modules'],
            'options' => $type->requiresOptions() ? $this->parseOptions($payload['options']) : null,
            'is_required' => (bool) ($payload['is_required'] ?? false),
            'is_searchable' => (bool) ($payload['is_searchable'] ?? false),
            'is_filterable' => (bool) ($payload['is_filterable'] ?? false),
            'show_in_reports' => (bool) ($payload['show_in_reports'] ?? false),
            'status' => CustomFieldStatusEnum::ACTIVE->value,
            'created_by' => $actor->uuid,
        ]);

        // Avoids a re-query; we already have the admin that was just set as creator.
        $definition->setRelation('creator', $actor);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CUSTOM_FIELD_CREATED,
            $request,
            $actor->uuid,
            ['custom_field_uuid' => $definition->uuid, 'name' => $definition->name, 'type' => $definition->type],
            $actor->displayName().' created a custom field: '.$definition->name.'.',
            CustomFieldDefinition::class,
            $definition->uuid,
            ModuleEnums::custom_field,
            201,
        );

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $uuid, array $payload, Admin $actor, Request $request): CustomFieldDefinition
    {
        $definition = $this->findForAdmin($uuid);
        $type = CustomFieldTypeEnum::from($definition->type);

        $updates = [];
        foreach (['name', 'description', 'applicable_modules', 'is_required', 'is_searchable', 'is_filterable', 'show_in_reports'] as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = $payload[$field];
            }
        }

        if ($type->requiresOptions() && array_key_exists('options', $payload)) {
            $updates['options'] = $this->parseOptions($payload['options']);
        }

        if ($updates !== []) {
            $definition = $this->definitionRepository->update($definition, $updates);
        }

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CUSTOM_FIELD_UPDATED,
            $request,
            $actor->uuid,
            ['custom_field_uuid' => $definition->uuid, 'name' => $definition->name, 'fields' => array_keys($updates)],
            $actor->displayName().' updated a custom field: '.$definition->name.'.',
            CustomFieldDefinition::class,
            $definition->uuid,
            ModuleEnums::custom_field,
            200,
        );

        return $definition;
    }

    /**
     * One-way: an archived field cannot be reactivated (no such control exists in the design).
     */
    public function archive(string $uuid, Admin $actor, Request $request): CustomFieldDefinition
    {
        $definition = $this->findForAdmin($uuid);

        if ($definition->status === CustomFieldStatusEnum::ARCHIVED->value) {
            throw new ApiException('This custom field has already been archived.', 422);
        }

        $definition = $this->definitionRepository->update($definition, ['status' => CustomFieldStatusEnum::ARCHIVED->value]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CUSTOM_FIELD_ARCHIVED,
            $request,
            $actor->uuid,
            ['custom_field_uuid' => $definition->uuid, 'name' => $definition->name],
            $actor->displayName().' archived a custom field: '.$definition->name.'.',
            CustomFieldDefinition::class,
            $definition->uuid,
            ModuleEnums::custom_field,
            200,
        );

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->definitionRepository->paginateForAdmin($filters, $perPage);
    }

    public function findForAdmin(string $uuid): CustomFieldDefinition
    {
        $definition = $this->definitionRepository->findByUuid($uuid);

        if (! $definition instanceof CustomFieldDefinition) {
            throw new ApiException('Custom field not found.', 404);
        }

        return $definition;
    }

    /**
     * Parses a ";"-delimited options string (e.g. "Male ; Female ; Prefer not to Say").
     *
     * @return list<string>
     */
    private function parseOptions(string $raw): array
    {
        $options = array_map('trim', explode(';', $raw));

        return array_values(array_filter($options, fn (string $option): bool => $option !== ''));
    }
}

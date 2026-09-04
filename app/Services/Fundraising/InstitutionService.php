<?php

namespace App\Services\Fundraising;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\Institution;
use App\Models\TertiaryInstitution;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use App\Repositories\Contracts\TertiaryInstitution\TertiaryInstitutionRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class InstitutionService
{
    public function __construct(
        private readonly InstitutionRepositoryInterface $institutionRepository,
        private readonly TertiaryInstitutionRepositoryInterface $tertiaryInstitutionRepository,
    ) {}

    /**
     * @return Collection<int, Institution>
     */
    public function list(bool $activeOnly = true): Collection
    {
        return $this->institutionRepository->all($activeOnly);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Admin $actor, Request $request): Institution
    {
        $tertiaryInstitution = $this->tertiaryInstitutionRepository->findByUuid((string) $payload['tertiary_institution_uuid']);

        if (! $tertiaryInstitution instanceof TertiaryInstitution) {
            throw new ApiException('Tertiary institution not found.', 404);
        }

        if ($this->institutionRepository->nameExists($tertiaryInstitution->name)) {
            throw new ApiException('This institution has already been added.', 422);
        }

        $institution = $this->institutionRepository->create([
            'name' => $tertiaryInstitution->name,
            'tertiary_institution_id' => $tertiaryInstitution->id,
            'is_active' => true,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::INSTITUTION_CREATED,
            $request,
            $actor->uuid,
            ['institution_uuid' => $institution->uuid, 'name' => $institution->name],
            $actor->displayName().' added an institution: '.$institution->name.'.',
            Institution::class,
            $institution->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $institution;
    }
}

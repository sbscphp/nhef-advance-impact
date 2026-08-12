<?php

namespace App\Services\Fundraising;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\Institution;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class InstitutionService
{
    public function __construct(
        private readonly InstitutionRepositoryInterface $institutionRepository,
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
        if ($this->institutionRepository->nameExists((string) $payload['name'])) {
            throw new ApiException('An institution with this name already exists.', 422);
        }

        $institution = $this->institutionRepository->create([
            'name' => $payload['name'],
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

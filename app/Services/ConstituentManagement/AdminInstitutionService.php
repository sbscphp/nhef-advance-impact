<?php

namespace App\Services\ConstituentManagement;

use App\Enums\AuditActionEnum;
use App\Enums\InstitutionStatusEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Jobs\SendInstitutionInviteEmailJob;
use App\Models\Admin;
use App\Models\CampaignInstitution;
use App\Models\Institution;
use App\Models\TertiaryInstitution;
use App\Repositories\Contracts\CampaignInstitution\CampaignInstitutionRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationRepositoryInterface;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use App\Repositories\Contracts\TertiaryInstitution\TertiaryInstitutionRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminInstitutionService
{
    public function __construct(
        private readonly InstitutionRepositoryInterface $institutionRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly DonationRepositoryInterface $donationRepository,
        private readonly CampaignInstitutionRepositoryInterface $campaignInstitutionRepository,
        private readonly DonationPaymentRepositoryInterface $donationPaymentRepository,
        private readonly PledgeRepositoryInterface $pledgeRepository,
        private readonly TertiaryInstitutionRepositoryInterface $tertiaryInstitutionRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function invite(array $payload, Admin $actor, Request $request): Institution
    {
        $tertiaryInstitution = $this->tertiaryInstitutionRepository->findByUuid((string) $payload['tertiary_institution_uuid']);

        if (! $tertiaryInstitution instanceof TertiaryInstitution) {
            throw new ApiException('Tertiary institution not found.', 404);
        }

        if ($this->institutionRepository->existsForTertiaryInstitution($tertiaryInstitution->id)) {
            throw new ApiException('This institution has already been invited.', 422);
        }

        if ($this->institutionRepository->emailExists((string) $payload['email'])) {
            throw new ApiException('An institution with this email already exists.', 422);
        }

        $institution = $this->institutionRepository->create([
            'name' => $tertiaryInstitution->name,
            'tertiary_institution_id' => $tertiaryInstitution->id,
            'email' => $payload['email'],
            'phone_number' => $payload['phone_number'] ?? null,
            'invite_message' => $payload['invite_message'] ?? null,
            'status' => InstitutionStatusEnum::INVITE_SENT->value,
            'is_active' => true,
            'created_by' => $actor->uuid,
            'invited_at' => now(),
        ]);

        // TODO: switch back to ::dispatch() (queued) once a worker is confirmed running in QA;
        // dispatchSync runs it inline so invite emails go out immediately without one.
        // SendInstitutionInviteEmailJob::dispatch($institution->uuid);
        SendInstitutionInviteEmailJob::dispatchSync($institution->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::INSTITUTION_INVITED,
            $request,
            $actor->uuid,
            ['institution_uuid' => $institution->uuid, 'name' => $institution->name, 'email' => $institution->email],
            $actor->displayName().' invited an institution: '.$institution->name.'.',
            Institution::class,
            $institution->uuid,
            ModuleEnums::constituent_management,
            201,
        );

        return $institution;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->institutionRepository->paginateForAdmin($filters, $perPage);
    }

    /**
     * @return array{all: int, active: int, access_revoked: int}
     */
    public function overview(?CarbonInterface $start, ?CarbonInterface $end): array
    {
        return $this->institutionRepository->countByStatus($start, $end);
    }

    public function findForAdmin(string $uuid): Institution
    {
        $institution = $this->institutionRepository->findByUuid($uuid);

        if (! $institution instanceof Institution) {
            throw new ApiException('Institution not found.', 404);
        }

        return $institution;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $uuid, array $payload, Admin $actor, Request $request): Institution
    {
        $institution = $this->findForAdmin($uuid);

        $updates = [];

        if (array_key_exists('tertiary_institution_uuid', $payload)) {
            $tertiaryInstitution = $this->tertiaryInstitutionRepository->findByUuid((string) $payload['tertiary_institution_uuid']);

            if (! $tertiaryInstitution instanceof TertiaryInstitution) {
                throw new ApiException('Tertiary institution not found.', 404);
            }

            if ($this->institutionRepository->existsForTertiaryInstitution($tertiaryInstitution->id, $institution->id)) {
                throw new ApiException('This institution has already been invited.', 422);
            }

            $updates['name'] = $tertiaryInstitution->name;
            $updates['tertiary_institution_id'] = $tertiaryInstitution->id;
        }

        foreach (['email', 'phone_number', 'country', 'state', 'address', 'invite_message', 'status'] as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = $payload[$field];
            }
        }

        if (isset($updates['status'])) {
            $updates['is_active'] = $updates['status'] !== InstitutionStatusEnum::ACCESS_REVOKED->value;
        }

        if ($updates !== []) {
            $institution = $this->institutionRepository->update($institution, $updates);
        }

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::INSTITUTION_UPDATED,
            $request,
            $actor->uuid,
            ['institution_uuid' => $institution->uuid, 'name' => $institution->name],
            $actor->displayName().' updated an institution: '.$institution->name.'.',
            Institution::class,
            $institution->uuid,
            ModuleEnums::constituent_management,
            200,
        );

        return $institution;
    }

    public function revokeAccess(string $uuid, Admin $actor, Request $request): Institution
    {
        $institution = $this->findForAdmin($uuid);

        if ($institution->status === InstitutionStatusEnum::ACCESS_REVOKED->value) {
            throw new ApiException('Institution access is already revoked.', 422);
        }

        $institution = $this->institutionRepository->update($institution, [
            'status' => InstitutionStatusEnum::ACCESS_REVOKED->value,
            'is_active' => false,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::INSTITUTION_ACCESS_REVOKED,
            $request,
            $actor->uuid,
            ['institution_uuid' => $institution->uuid, 'name' => $institution->name],
            $actor->displayName().' revoked access for institution: '.$institution->name.'.',
            Institution::class,
            $institution->uuid,
            ModuleEnums::constituent_management,
            200,
        );

        return $institution;
    }

    public function reactivateAccess(string $uuid, Admin $actor, Request $request): Institution
    {
        $institution = $this->findForAdmin($uuid);

        if ($institution->status !== InstitutionStatusEnum::ACCESS_REVOKED->value) {
            throw new ApiException('Only an institution with revoked access can be reactivated.', 422);
        }

        $institution = $this->institutionRepository->update($institution, [
            'status' => InstitutionStatusEnum::ACTIVE->value,
            'is_active' => true,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::INSTITUTION_REACTIVATED,
            $request,
            $actor->uuid,
            ['institution_uuid' => $institution->uuid, 'name' => $institution->name],
            $actor->displayName().' reactivated access for institution: '.$institution->name.'.',
            Institution::class,
            $institution->uuid,
            ModuleEnums::constituent_management,
            200,
        );

        return $institution;
    }

    public function resendInvite(string $uuid, Admin $actor, Request $request): Institution
    {
        $institution = $this->findForAdmin($uuid);

        if ($institution->status !== InstitutionStatusEnum::INVITE_SENT->value) {
            throw new ApiException('Invite can only be resent while the institution is pending onboarding.', 422);
        }

        $institution = $this->institutionRepository->update($institution, ['invited_at' => now()]);

        // TODO: switch back to ::dispatch() (queued) once a worker is confirmed running in QA;
        // dispatchSync runs it inline so invite emails go out immediately without one.
        // SendInstitutionInviteEmailJob::dispatch($institution->uuid);
        SendInstitutionInviteEmailJob::dispatchSync($institution->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::INSTITUTION_INVITE_RESENT,
            $request,
            $actor->uuid,
            ['institution_uuid' => $institution->uuid, 'name' => $institution->name],
            $actor->displayName().' resent the onboarding invite for institution: '.$institution->name.'.',
            Institution::class,
            $institution->uuid,
            ModuleEnums::constituent_management,
            200,
        );

        return $institution;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAlumni(Institution $institution, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        if ($institution->tertiary_institution_id === null) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        $paginator = $this->userRepository->paginateForInstitution($institution->tertiary_institution_id, $filters, $perPage);

        $userIds = array_map(fn ($user) => $user->id, $paginator->items());
        $donationCounts = $this->donationRepository->countByUserIds($userIds);

        foreach ($paginator->items() as $user) {
            $user->setAttribute('donation_count', $donationCounts[$user->id] ?? 0);
        }

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateCampaigns(Institution $institution, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $paginator = $this->campaignInstitutionRepository->paginateForInstitution($institution->id, $filters, $perPage);

        foreach ($paginator->items() as $row) {
            /** @var CampaignInstitution $row */
            $raised = $institution->tertiary_institution_id === null ? 0.0 :
                (float) $this->donationPaymentRepository->sumSuccessfulForCampaignAndInstitution($row->campaign_id, $institution->tertiary_institution_id)
                + (float) $this->pledgeRepository->sumReceivedForCampaignAndInstitution($row->campaign_id, $institution->tertiary_institution_id);
            $row->setAttribute('raised_amount', (string) $raised);
        }

        return $paginator;
    }
}

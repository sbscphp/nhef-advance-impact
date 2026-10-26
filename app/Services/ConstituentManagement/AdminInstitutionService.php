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
use App\Models\InstitutionOfficer;
use App\Models\TertiaryInstitution;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;
use App\Repositories\Contracts\CampaignInstitution\CampaignInstitutionRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationRepositoryInterface;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use App\Repositories\Contracts\InstitutionOfficer\InstitutionOfficerRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use App\Repositories\Contracts\TertiaryInstitution\TertiaryInstitutionRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        private readonly AdminRepositoryInterface $adminRepository,
        private readonly InstitutionStatsLoader $statsLoader,
        private readonly DonorTierRepositoryInterface $donorTierRepository,
        private readonly InstitutionOfficerRepositoryInterface $institutionOfficerRepository,
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

        $email = (string) $payload['email'];
        $adminEmail = (string) $payload['advancement_database_manager_email'];

        if ($this->institutionRepository->emailExists($email) || $this->adminRepository->emailExists($email)) {
            throw new ApiException('An institution or admin with this email already exists.', 422);
        }

        // The Advancement Database Manager becomes the institution's own admin login (2026-10-23,
        // client requirement) - checked separately since it's a different email from the
        // institution's own above, and is the one that actually needs to be unique as a login.
        if ($adminEmail !== $email && $this->adminRepository->emailExists($adminEmail)) {
            throw new ApiException('An admin with the Advancement Database Manager\'s email already exists.', 422);
        }

        $institution = DB::transaction(function () use ($payload, $tertiaryInstitution, $actor, $email, $adminEmail): Institution {
            $institution = $this->institutionRepository->create([
                'name' => $tertiaryInstitution->name,
                'slug' => $this->uniqueSlug($tertiaryInstitution->name),
                'tertiary_institution_id' => $tertiaryInstitution->id,
                'email' => $email,
                'phone_number' => $payload['phone_number'] ?? null,
                'invite_message' => $payload['invite_message'] ?? null,
                'vc_name' => $payload['vc_name'],
                'vc_email' => $payload['vc_email'],
                'vc_phone_number' => $payload['vc_phone_number'],
                'advancement_director_name' => $payload['advancement_director_name'],
                'advancement_director_email' => $payload['advancement_director_email'],
                'advancement_director_phone_number' => $payload['advancement_director_phone_number'],
                'advancement_database_manager_name' => $payload['advancement_database_manager_name'],
                'advancement_database_manager_email' => $payload['advancement_database_manager_email'],
                'advancement_database_manager_phone_number' => $payload['advancement_database_manager_phone_number'],
                'status' => InstitutionStatusEnum::INVITE_SENT->value,
                'is_active' => true,
                'created_by' => $actor->uuid,
                'invited_at' => now(),
            ]);

            $this->adminRepository->createInstitutionOwner($institution, (string) $payload['advancement_database_manager_name'], $adminEmail);

            foreach ((array) ($payload['officers'] ?? []) as $officer) {
                $this->institutionOfficerRepository->create($institution, [
                    'role' => $officer['role'],
                    'name' => $officer['name'],
                    'email' => $officer['email'],
                    'phone_number' => $officer['phone_number'],
                ]);
            }

            // create()'s hasMany()->create() calls above don't populate this instance's own
            // `officers` relation, so InstitutionDetailResource's whenLoaded('officers') would
            // silently omit the key entirely without this.
            $institution->load('officers');

            return $institution;
        });

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

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'institution';
        $slug = $base;
        $suffix = 2;

        while ($this->institutionRepository->slugExists($slug)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $paginator = $this->institutionRepository->paginateForAdmin($filters, $perPage);
        $this->statsLoader->attach($paginator->items());

        return $paginator;
    }

    /**
     * Tertiary institutions the Super Admin can pick from when inviting, flagged when already invited.
     *
     * @param  array<string, mixed>  $filters
     */
    public function tertiaryOptions(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $paginator = $this->tertiaryInstitutionRepository->paginate($filters, $perPage);

        $linked = $this->institutionRepository->linkedTertiaryInstitutionIds(array_map(fn ($row) => $row->id, $paginator->items()));
        foreach ($paginator->items() as $row) {
            $row->setAttribute('already_invited', in_array($row->id, $linked, true));
        }

        return $paginator;
    }

    public function showForAdmin(string $uuid): Institution
    {
        $institution = $this->findForAdmin($uuid);
        $this->statsLoader->attach([$institution]);

        return $institution;
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

        foreach ([
            'email', 'phone_number', 'country', 'state', 'address', 'invite_message', 'status',
            'vc_name', 'vc_email', 'vc_phone_number',
            'advancement_director_name', 'advancement_director_email', 'advancement_director_phone_number',
            'advancement_database_manager_name', 'advancement_database_manager_email', 'advancement_database_manager_phone_number',
        ] as $field) {
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
     * Any further custom officers beyond the three fixed ones on `institutions` itself
     * (2026-10-26, client requirement) - `role` is free-typed, not an enum, since the FE defines
     * it per entry rather than picking from a preset list. Contact info only, no login.
     *
     * @param  array<string, mixed>  $payload
     */
    public function addOfficer(string $uuid, array $payload, Admin $actor, Request $request): InstitutionOfficer
    {
        $institution = $this->findForAdmin($uuid);

        $officer = $this->institutionOfficerRepository->create($institution, [
            'role' => $payload['role'],
            'name' => $payload['name'],
            'email' => $payload['email'],
            'phone_number' => $payload['phone_number'],
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::INSTITUTION_OFFICER_ADDED,
            $request,
            $actor->uuid,
            ['institution_uuid' => $institution->uuid, 'officer_uuid' => $officer->uuid, 'role' => $officer->role, 'name' => $officer->name],
            $actor->displayName().' added '.$officer->name.' ('.$officer->role.') to institution: '.$institution->name.'.',
            Institution::class,
            $institution->uuid,
            ModuleEnums::constituent_management,
            201,
        );

        return $officer;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateOfficer(string $uuid, string $officerUuid, array $payload, Admin $actor, Request $request): InstitutionOfficer
    {
        $institution = $this->findForAdmin($uuid);

        $officer = $this->institutionOfficerRepository->findForInstitution($institution->id, $officerUuid);
        if (! $officer instanceof InstitutionOfficer) {
            throw new ApiException('Institution officer not found.', 404);
        }

        $updates = [];
        foreach (['role', 'name', 'email', 'phone_number'] as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = $payload[$field];
            }
        }

        if ($updates !== []) {
            $officer = $this->institutionOfficerRepository->update($officer, $updates);
        }

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::INSTITUTION_OFFICER_UPDATED,
            $request,
            $actor->uuid,
            ['institution_uuid' => $institution->uuid, 'officer_uuid' => $officer->uuid, 'fields' => array_keys($updates)],
            $actor->displayName().' updated an officer on institution: '.$institution->name.'.',
            Institution::class,
            $institution->uuid,
            ModuleEnums::constituent_management,
            200,
        );

        return $officer;
    }

    public function removeOfficer(string $uuid, string $officerUuid, Admin $actor, Request $request): void
    {
        $institution = $this->findForAdmin($uuid);

        $officer = $this->institutionOfficerRepository->findForInstitution($institution->id, $officerUuid);
        if (! $officer instanceof InstitutionOfficer) {
            throw new ApiException('Institution officer not found.', 404);
        }

        $officerName = $officer->name;
        $this->institutionOfficerRepository->delete($officer);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::INSTITUTION_OFFICER_REMOVED,
            $request,
            $actor->uuid,
            ['institution_uuid' => $institution->uuid, 'name' => $officerName],
            $actor->displayName().' removed '.$officerName.' from institution: '.$institution->name.'.',
            Institution::class,
            $institution->uuid,
            ModuleEnums::constituent_management,
            200,
        );
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
        $totals = $this->donationPaymentRepository->totalsByUserIds($userIds);

        // One institution already, so one tier lookup for the whole page - institution-scoped
        // tiers (2026-10-22), falling back to the platform-wide default set if this institution
        // hasn't configured its own.
        $tiers = $this->donorTierRepository->activeOrderedByThreshold($institution->id);
        if ($tiers->isEmpty()) {
            $tiers = $this->donorTierRepository->activeOrderedByThreshold(null);
        }

        foreach ($paginator->items() as $user) {
            $user->setAttribute('donation_count', $donationCounts[$user->id] ?? 0);

            $total = $totals[$user->id]['total'] ?? '0';
            $tier = $tiers->filter(fn ($candidate) => bccomp($total, (string) $candidate->minimum_amount, 2) >= 0)->last();
            $user->setAttribute('tier', $tier?->name);
            $user->setAttribute('tier_color', $tier?->color);
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

    /**
     * This institution's own admin users, paginated - client feedback (2026-10-16): NHEF had no
     * way at all to see which admins manage a given institution, anywhere in the product.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdmins(Institution $institution, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->adminRepository->paginateForInstitution($institution->id, $filters, $perPage);
    }
}

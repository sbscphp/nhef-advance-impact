<?php

namespace App\Services\DonorTier;

use App\Enums\AdminScopeEnum;
use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\DonorTier;
use App\Models\Institution;
use App\Models\User;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use App\Services\Recognition\RecognitionService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DonorTierService
{
    public function __construct(
        private readonly DonorTierRepositoryInterface $tierRepository,
        private readonly DonationPaymentRepositoryInterface $paymentRepository,
        private readonly InstitutionRepositoryInterface $institutionRepository,
    ) {}

    /**
     * Institution-scoped (2026-10-22): every tier belongs to exactly one institution. An
     * institution admin's own tier is always stamped with their own institution (their
     * `institution_uuid` input, if any, is ignored - never trust the client for this); NHEF
     * picks which institution via `institution_uuid`, since NHEF has no institution of its own.
     *
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Admin $actor, Request $request): DonorTier
    {
        $institutionId = $this->resolveInstitutionIdForCreate($payload, $actor);

        $tier = $this->tierRepository->create([
            'institution_id' => $institutionId,
            'name' => $payload['name'],
            'minimum_amount' => $payload['minimum_amount'],
            'maximum_amount' => $payload['maximum_amount'],
            'badge_url' => FileUploadHelper::smartSingleFileUpload($payload['badge'], 'donor-tiers/badges'),
            'color' => $payload['color'],
            'is_active' => true,
            'created_by' => $actor->uuid,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::DONOR_TIER_CREATED,
            $request,
            $actor->uuid,
            ['tier_uuid' => $tier->uuid, 'name' => $tier->name],
            $actor->displayName().' created a donation tier: '.$tier->name.'.',
            DonorTier::class,
            $tier->uuid,
            ModuleEnums::donor_tier,
            201,
        );

        return $tier;
    }

    private function resolveInstitutionIdForCreate(array $payload, Admin $actor): int
    {
        if (AdminScopeEnum::of($actor) === AdminScopeEnum::INSTITUTION) {
            if ($actor->institution_id === null) {
                throw new ApiException('Your admin account has no institution to create a tier for.', 422);
            }

            return $actor->institution_id;
        }

        $institution = $this->institutionRepository->findByUuid((string) ($payload['institution_uuid'] ?? ''));
        if (! $institution instanceof Institution) {
            throw new ApiException('The selected institution does not exist.', 422);
        }

        return $institution->id;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters): LengthAwarePaginator
    {
        $filters = $this->scopeFiltersToOwnInstitution($filters);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->tierRepository->paginateForAdmin($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, DonorTier>, 1: bool}
     */
    public function exportForAdmin(array $filters): array
    {
        return $this->tierRepository->exportForAdmin($this->scopeFiltersToOwnInstitution($filters));
    }

    /**
     * An institution admin only ever sees/lists their own institution's tiers - their own
     * institution_uuid filter, never whatever (if anything) the client sent, same reasoning as
     * resolveInstitutionIdForCreate().
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function scopeFiltersToOwnInstitution(array $filters): array
    {
        $actor = $this->currentAdmin();
        if ($actor === null || AdminScopeEnum::of($actor) !== AdminScopeEnum::INSTITUTION) {
            return $filters;
        }

        $actor->loadMissing('institution');
        $filters['filters']['institution_uuid'] = $actor->institution?->uuid;

        return $filters;
    }

    public function findForAdmin(string $uuid): DonorTier
    {
        $tier = $this->tierRepository->findByUuid($uuid);

        if (! $tier instanceof DonorTier) {
            throw new ApiException('Donation tier not found.', 404);
        }

        $this->assertOwnsTier($tier);

        return $tier;
    }

    /**
     * An institution admin can only reach their own institution's tiers - 404, not 403, so this
     * doesn't confirm another institution's tier exists.
     */
    private function assertOwnsTier(DonorTier $tier): void
    {
        $actor = $this->currentAdmin();
        if ($actor === null || AdminScopeEnum::of($actor) !== AdminScopeEnum::INSTITUTION) {
            return;
        }

        if ($tier->institution_id !== $actor->institution_id) {
            throw new ApiException('Donation tier not found.', 404);
        }
    }

    private function currentAdmin(): ?Admin
    {
        $actor = request()->user();

        return $actor instanceof Admin ? $actor : null;
    }

    /**
     * Same as {@see self::findForAdmin()} but attaches creator info and the alumni/institution
     * counts as transient, non-column attributes for DonorTierDetailResource to read; never
     * call this on a DonorTier that will be saved afterwards.
     */
    public function detailForAdmin(string $uuid): DonorTier
    {
        $tier = $this->findForAdmin($uuid);
        $tier->loadMissing(['creator.roles:id,name']);

        $bounds = $this->resolveBounds($tier);
        $stats = $tier->is_active
            ? $this->tierRepository->statsForRange($bounds['min'], $bounds['max'], $tier->institution?->tertiary_institution_id)
            : ['alumni_count' => 0, 'institution_count' => 0];

        $tier->setAttribute('alumni_count', $stats['alumni_count']);
        $tier->setAttribute('institution_count', $stats['institution_count']);

        return $tier;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $uuid, array $payload, Admin $actor, Request $request): DonorTier
    {
        $tier = $this->findForAdmin($uuid);
        $previous = [
            'name' => $tier->name,
            'minimum_amount' => (string) $tier->minimum_amount,
            'maximum_amount' => $tier->maximum_amount !== null ? (string) $tier->maximum_amount : null,
            'badge_url' => $tier->badge_url,
            'color' => $tier->color,
        ];

        $updates = [];
        foreach (['name', 'minimum_amount', 'maximum_amount', 'color'] as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = $payload[$field];
            }
        }
        if (array_key_exists('badge', $payload) && $payload['badge'] !== null && $payload['badge'] !== '') {
            $updates['badge_url'] = FileUploadHelper::smartSingleFileUpload($payload['badge'], 'donor-tiers/badges');
        }

        if ($updates !== []) {
            $tier = $this->tierRepository->update($tier, $updates);
        }

        $changedFields = [];
        foreach (array_keys($previous) as $field) {
            $current = in_array($field, ['minimum_amount', 'maximum_amount'], true)
                ? ($tier->{$field} !== null ? (string) $tier->{$field} : null)
                : $tier->{$field};
            if ($previous[$field] !== $current) {
                $changedFields[] = $field;
            }
        }

        if ($changedFields !== []) {
            GeneralHelper::storeAuditLog(
                UserTypeEnum::ADMIN,
                AuditActionEnum::DONOR_TIER_UPDATED,
                $request,
                $actor->uuid,
                ['tier_uuid' => $tier->uuid, 'name' => $tier->name, 'fields' => $changedFields],
                $actor->displayName().' updated a donation tier: '.$tier->name.'.',
                DonorTier::class,
                $tier->uuid,
                ModuleEnums::donor_tier,
                200,
            );
        }

        return $tier;
    }

    public function toggleActiveStatus(string $uuid, Admin $actor, Request $request): DonorTier
    {
        $tier = $this->findForAdmin($uuid);
        $isActive = ! (bool) $tier->is_active;

        $tier = $this->tierRepository->update($tier, ['is_active' => $isActive]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::DONOR_TIER_STATUS_TOGGLED,
            $request,
            $actor->uuid,
            ['tier_uuid' => $tier->uuid, 'name' => $tier->name, 'new_status' => $isActive ? 'active' : 'inactive'],
            $actor->displayName().($isActive ? ' reactivated' : ' deactivated').' a donation tier: '.$tier->name.'.',
            DonorTier::class,
            $tier->uuid,
            ModuleEnums::donor_tier,
            200,
        );

        return $tier;
    }

    public function delete(string $uuid, Admin $actor, Request $request): void
    {
        $tier = $this->findForAdmin($uuid);
        $tierUuid = $tier->uuid;
        $tierName = $tier->name;

        $this->tierRepository->delete($tier);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::DONOR_TIER_DELETED,
            $request,
            $actor->uuid,
            ['tier_uuid' => $tierUuid, 'name' => $tierName],
            $actor->displayName().' deleted a donation tier: '.$tierName.'.',
            DonorTier::class,
            $tierUuid,
            ModuleEnums::donor_tier,
            200,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAlumni(string $uuid, array $filters): LengthAwarePaginator
    {
        $tier = $this->findForAdmin($uuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        if (! $tier->is_active) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        $bounds = $this->resolveBounds($tier);

        $paginator = $this->tierRepository->paginateUsersInRange($bounds['min'], $bounds['max'], $filters, $perPage, $tier->institution?->tertiary_institution_id);
        $paginator->setCollection($this->attachUpgradeDates($paginator->getCollection(), $tier));

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, User>, 1: bool}
     */
    public function exportAlumni(string $uuid, array $filters): array
    {
        $tier = $this->findForAdmin($uuid);

        if (! $tier->is_active) {
            return [new Collection, false];
        }

        $bounds = $this->resolveBounds($tier);

        [$rows, $truncated] = $this->tierRepository->exportUsersInRange($bounds['min'], $bounds['max'], $filters, $tier->institution?->tertiary_institution_id);

        return [$this->attachUpgradeDates($rows, $tier), $truncated];
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    private function attachUpgradeDates(Collection $users, DonorTier $tier): Collection
    {
        return $users->map(function (User $user) use ($tier): User {
            $user->setAttribute('upgraded_at', $this->paymentRepository->resolveTierUpgradeDate($user->id, (string) $tier->minimum_amount));

            return $user;
        });
    }

    /**
     * The bracket [minimum_amount, next active tier's minimum_amount) that a donor's lifetime
     * total must fall in to currently resolve to this (active) tier (see {@see RecognitionService::tierFor()}
     * for the same open-ended-above logic used everywhere else tier membership is decided).
     *
     * @return array{min: string, max: ?string}
     */
    private function resolveBounds(DonorTier $tier): array
    {
        $tiers = $this->tierRepository->activeOrderedByThreshold($tier->institution_id);
        $next = $tiers->first(fn (DonorTier $candidate) => (float) $candidate->minimum_amount > (float) $tier->minimum_amount);

        return [
            'min' => (string) $tier->minimum_amount,
            'max' => $next !== null ? (string) $next->minimum_amount : null,
        ];
    }

}

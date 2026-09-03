<?php

namespace App\Services\DonorTier;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\DonorTier;
use App\Models\User;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use App\Services\Recognition\RecognitionService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DonorTierService
{
    public function __construct(
        private readonly DonorTierRepositoryInterface $tierRepository,
        private readonly DonationPaymentRepositoryInterface $paymentRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Admin $actor, Request $request): DonorTier
    {
        $tier = $this->tierRepository->create([
            'name' => $payload['name'],
            'minimum_amount' => $payload['minimum_amount'],
            'maximum_amount' => $payload['maximum_amount'],
            'badge_url' => FileUploadHelper::smartSingleFileUpload($payload['badge'], 'donor-tiers/badges'),
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
            ModuleEnums::system_configuration,
            201,
        );

        return $tier;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->tierRepository->paginateForAdmin($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, DonorTier>, 1: bool}
     */
    public function exportForAdmin(array $filters): array
    {
        return $this->tierRepository->exportForAdmin($filters);
    }

    public function findForAdmin(string $uuid): DonorTier
    {
        $tier = $this->tierRepository->findByUuid($uuid);

        if (! $tier instanceof DonorTier) {
            throw new ApiException('Donation tier not found.', 404);
        }

        return $tier;
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
        $stats = $this->tierRepository->statsForRange($bounds['min'], $bounds['max']);

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
        ];

        $updates = [];
        foreach (['name', 'minimum_amount', 'maximum_amount'] as $field) {
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
                ModuleEnums::system_configuration,
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
            ModuleEnums::system_configuration,
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
            ModuleEnums::system_configuration,
            200,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAlumni(string $uuid, array $filters): LengthAwarePaginator
    {
        $tier = $this->findForAdmin($uuid);
        $bounds = $this->resolveBounds($tier);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $paginator = $this->tierRepository->paginateUsersInRange($bounds['min'], $bounds['max'], $filters, $perPage);
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
        $bounds = $this->resolveBounds($tier);

        [$rows, $truncated] = $this->tierRepository->exportUsersInRange($bounds['min'], $bounds['max'], $filters);

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
     * The bracket [minimum_amount, next tier's minimum_amount) that a donor's lifetime total
     * must fall in to currently resolve to this tier (see {@see RecognitionService::tierFor()}
     * for the same open-ended-above logic used everywhere else tier membership is decided).
     *
     * @return array{min: string, max: ?string}
     */
    private function resolveBounds(DonorTier $tier): array
    {
        $tiers = $this->tierRepository->allOrderedByThreshold();
        $next = $tiers->first(fn (DonorTier $candidate) => (float) $candidate->minimum_amount > (float) $tier->minimum_amount);

        return [
            'min' => (string) $tier->minimum_amount,
            'max' => $next !== null ? (string) $next->minimum_amount : null,
        ];
    }
}

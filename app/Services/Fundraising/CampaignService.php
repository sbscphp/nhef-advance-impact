<?php

namespace App\Services\Fundraising;

use App\Enums\AuditActionEnum;
use App\Enums\CampaignStatusEnum;
use App\Enums\CampaignTypeEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Admin;
use App\Models\BankAccount;
use App\Models\Campaign;
use App\Models\CampaignInstitution;
use App\Models\Institution;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;
use App\Repositories\Contracts\BankAccount\BankAccountRepositoryInterface;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\CampaignInstitution\CampaignInstitutionRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CampaignService
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly AdminRepositoryInterface $adminRepository,
        private readonly BankAccountRepositoryInterface $bankAccountRepository,
        private readonly DonationPaymentRepositoryInterface $paymentRepository,
        private readonly PledgeRepositoryInterface $pledgeRepository,
        private readonly DonorTierRepositoryInterface $donorTierRepository,
        private readonly InstitutionRepositoryInterface $institutionRepository,
        private readonly CampaignInstitutionRepositoryInterface $campaignInstitutionRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->campaignRepository->paginateActive($filters, $perPage);
    }

    public function findActiveByUuid(string $uuid): Campaign
    {
        $campaign = $this->campaignRepository->findActiveByUuid($uuid);

        if (! $campaign instanceof Campaign) {
            throw new ApiException('Campaign not found.', 404);
        }

        return $campaign;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->campaignRepository->paginateAdmin($filters, $perPage);
    }

    /** Unscoped by status; the admin "View Campaign" screen must load a paused/draft/completed campaign too. */
    public function findForAdmin(string $uuid): Campaign
    {
        $campaign = $this->campaignRepository->findByUuid($uuid);

        if (! $campaign instanceof Campaign) {
            throw new ApiException('Campaign not found.', 404);
        }

        return $campaign;
    }

    public function donorsCount(Campaign $campaign): int
    {
        return $this->campaignRepository->countDistinctDonors($campaign);
    }

    public function daysRemaining(Campaign $campaign): ?int
    {
        if ($campaign->ends_at === null) {
            return null;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($campaign->ends_at->copy()->startOfDay(), false));
    }

    /**
     * Summing across currencies isn't meaningful, so this aggregates NGN institutions only.
     *
     * @return array{goal_amount: string, raised_amount: string, currency: string}
     */
    public function nationalGivingDayTotals(Campaign $campaign): array
    {
        $rows = $this->campaignInstitutionRepository->allForCampaign($campaign->id)
            ->filter(fn (CampaignInstitution $row) => $row->currency === 'NGN');

        $goalAmount = (string) $rows->sum(fn (CampaignInstitution $row) => (float) $row->goal_amount);

        $raisedAmount = (string) $rows->sum(function (CampaignInstitution $row) use ($campaign) {
            return (float) $this->paymentRepository->sumSuccessfulForCampaignAndInstitution($campaign->id, $row->institution->tertiary_institution_id)
                + (float) $this->pledgeRepository->sumReceivedForCampaignAndInstitution($campaign->id, $row->institution->tertiary_institution_id);
        });

        return [
            'goal_amount' => $goalAmount,
            'raised_amount' => $raisedAmount,
            'currency' => 'NGN',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Admin $actor, Request $request): Campaign
    {
        $allocatedAdmin = $this->adminRepository->findByUuid((string) $payload['allocated_admin_id']);
        if (! $allocatedAdmin instanceof Admin) {
            throw new ApiException('The selected officer does not exist.', 422);
        }

        $bankAccount = $this->bankAccountRepository->findByUuid((string) $payload['bank_account_id']);
        if (! $bankAccount instanceof BankAccount) {
            throw new ApiException('The selected bank account does not exist.', 422);
        }

        $coverUrl = FileUploadHelper::smartSingleFileUpload($payload['cover'] ?? null, 'campaigns/covers');

        $campaign = $this->campaignRepository->create([
            'title' => $payload['title'],
            'slug' => $this->uniqueSlug((string) $payload['title']),
            'description' => $payload['description'],
            'cover_image_url' => $coverUrl,
            'currency' => $payload['currency'],
            'goal_amount' => $payload['goal_amount'],
            'raised_amount' => 0,
            'allow_one_time' => true,
            'allow_recurring' => true,
            'allow_anonymous' => true,
            'status' => CampaignStatusEnum::ACTIVE->value,
            'starts_at' => $payload['starts_at'],
            'ends_at' => $payload['ends_at'] ?? null,
            'created_by' => $actor->uuid,
            'allocated_admin_id' => $allocatedAdmin->id,
            'bank_account_id' => $bankAccount->id,
        ]);

        $campaign->setRelation('allocatedAdmin', $allocatedAdmin);
        $campaign->setRelation('bankAccount', $bankAccount);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CAMPAIGN_CREATED,
            $request,
            $actor->uuid,
            [
                'campaign_uuid' => $campaign->uuid,
                'title' => $campaign->title,
                'goal_amount' => $campaign->goal_amount,
                'currency' => $campaign->currency,
            ],
            $actor->displayName().' created a campaign: '.$campaign->title.'.',
            Campaign::class,
            $campaign->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $campaign;
    }

    /**
     * Unlike a standard campaign, there's no top-level goal/currency/bank account; each
     * institution carries its own.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createNationalGivingDay(array $payload, Admin $actor, Request $request): Campaign
    {
        $allocatedAdmin = $this->adminRepository->findByUuid((string) $payload['allocated_admin_id']);
        if (! $allocatedAdmin instanceof Admin) {
            throw new ApiException('The selected officer does not exist.', 422);
        }

        $institutionRows = $this->resolveInstitutionRows((array) $payload['institutions']);

        $coverUrl = FileUploadHelper::smartSingleFileUpload($payload['cover'] ?? null, 'campaigns/covers');

        $campaign = $this->campaignRepository->create([
            'title' => $payload['title'],
            'slug' => $this->uniqueSlug((string) $payload['title']),
            'description' => $payload['description'],
            'cover_image_url' => $coverUrl,
            'type' => CampaignTypeEnum::NATIONAL_GIVING_DAY->value,
            'currency' => null,
            'goal_amount' => null,
            'raised_amount' => 0,
            'allow_one_time' => true,
            'allow_recurring' => true,
            'allow_anonymous' => true,
            'status' => CampaignStatusEnum::ACTIVE->value,
            'starts_at' => $payload['starts_at'],
            'ends_at' => $payload['ends_at'] ?? null,
            'created_by' => $actor->uuid,
            'allocated_admin_id' => $allocatedAdmin->id,
            'bank_account_id' => null,
        ]);

        $this->campaignInstitutionRepository->createMany($campaign, $institutionRows);

        $campaign->setRelation('allocatedAdmin', $allocatedAdmin);
        $campaign->load('campaignInstitutions.institution', 'campaignInstitutions.bankAccount.bank');

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CAMPAIGN_CREATED,
            $request,
            $actor->uuid,
            [
                'campaign_uuid' => $campaign->uuid,
                'title' => $campaign->title,
                'type' => $campaign->type,
                'institution_count' => count($institutionRows),
            ],
            $actor->displayName().' created a National Giving Day campaign: '.$campaign->title.'.',
            Campaign::class,
            $campaign->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $campaign;
    }

    /**
     * @param  list<array<string, mixed>>  $institutions
     * @return list<array<string, mixed>>
     */
    private function resolveInstitutionRows(array $institutions): array
    {
        $rows = [];

        foreach ($institutions as $entry) {
            $institution = $this->institutionRepository->findByUuid((string) $entry['institution_id']);
            if (! $institution instanceof Institution) {
                throw new ApiException('One of the selected institutions does not exist.', 422);
            }

            $bankAccount = $this->bankAccountRepository->findByUuid((string) $entry['bank_account_id']);
            if (! $bankAccount instanceof BankAccount) {
                throw new ApiException('One of the selected bank accounts does not exist.', 422);
            }

            $rows[] = [
                'institution_id' => $institution->id,
                'goal_amount' => $entry['goal_amount'],
                'currency' => $entry['currency'],
                'bank_account_id' => $bankAccount->id,
            ];
        }

        return $rows;
    }

    /**
     * Backs "Edit Campaign"; only sends fields that changed. Slug is left untouched even if the
     * title changes, since the campaign's public/shared link must keep working.
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(string $uuid, array $payload, Admin $actor, Request $request): Campaign
    {
        $campaign = $this->findForAdmin($uuid);

        $updates = [];
        foreach (['title', 'description', 'goal_amount', 'currency', 'starts_at', 'ends_at'] as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = $payload[$field];
            }
        }

        if (array_key_exists('allocated_admin_id', $payload)) {
            $allocatedAdmin = $this->adminRepository->findByUuid((string) $payload['allocated_admin_id']);
            if (! $allocatedAdmin instanceof Admin) {
                throw new ApiException('The selected officer does not exist.', 422);
            }
            $updates['allocated_admin_id'] = $allocatedAdmin->id;
        }

        if (array_key_exists('bank_account_id', $payload)) {
            $bankAccount = $this->bankAccountRepository->findByUuid((string) $payload['bank_account_id']);
            if (! $bankAccount instanceof BankAccount) {
                throw new ApiException('The selected bank account does not exist.', 422);
            }
            $updates['bank_account_id'] = $bankAccount->id;
        }

        if (! empty($payload['cover'])) {
            $updates['cover_image_url'] = FileUploadHelper::smartSingleFileUpload($payload['cover'], 'campaigns/covers');
        }

        if ($updates === []) {
            return $campaign;
        }

        $campaign = $this->campaignRepository->update($campaign, $updates);
        $campaign = $this->campaignRepository->findByUuid($campaign->uuid) ?? $campaign;

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CAMPAIGN_UPDATED,
            $request,
            $actor->uuid,
            ['campaign_uuid' => $campaign->uuid, 'fields' => array_keys($updates)],
            $actor->displayName().' updated a campaign: '.$campaign->title.'.',
            Campaign::class,
            $campaign->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $campaign;
    }

    public function pause(string $uuid, Admin $actor, Request $request): Campaign
    {
        $campaign = $this->findForAdmin($uuid);

        if ($campaign->status !== CampaignStatusEnum::ACTIVE->value) {
            throw new ApiException('Only an active campaign can be paused.', 422);
        }

        $campaign = $this->campaignRepository->update($campaign, ['status' => CampaignStatusEnum::PAUSED->value]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CAMPAIGN_PAUSED,
            $request,
            $actor->uuid,
            ['campaign_uuid' => $campaign->uuid, 'title' => $campaign->title],
            $actor->displayName().' paused a campaign: '.$campaign->title.'.',
            Campaign::class,
            $campaign->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $campaign;
    }

    public function resume(string $uuid, Admin $actor, Request $request): Campaign
    {
        $campaign = $this->findForAdmin($uuid);

        if ($campaign->status !== CampaignStatusEnum::PAUSED->value) {
            throw new ApiException('Only a paused campaign can be resumed.', 422);
        }

        $campaign = $this->campaignRepository->update($campaign, ['status' => CampaignStatusEnum::ACTIVE->value]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CAMPAIGN_RESUMED,
            $request,
            $actor->uuid,
            ['campaign_uuid' => $campaign->uuid, 'title' => $campaign->title],
            $actor->displayName().' resumed a campaign: '.$campaign->title.'.',
            Campaign::class,
            $campaign->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $campaign;
    }

    /**
     * `raised_amount` is computed live from donors linked to the same tertiary institution.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listInstitutions(string $uuid, array $filters): LengthAwarePaginator
    {
        $campaign = $this->findForAdmin($uuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $paginator = $this->campaignInstitutionRepository->paginateForCampaign($campaign->id, $filters, $perPage);

        foreach ($paginator->items() as $row) {
            /** @var CampaignInstitution $row */
            $raised = (float) $this->paymentRepository->sumSuccessfulForCampaignAndInstitution($campaign->id, $row->institution->tertiary_institution_id)
                + (float) $this->pledgeRepository->sumReceivedForCampaignAndInstitution($campaign->id, $row->institution->tertiary_institution_id);
            $row->setAttribute('raised_amount', (string) $raised);
        }

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addInstitution(string $uuid, array $payload, Admin $actor, Request $request): CampaignInstitution
    {
        $campaign = $this->findForAdmin($uuid);
        $this->assertNationalGivingDay($campaign);

        $rows = $this->resolveInstitutionRows([$payload]);
        $campaignInstitution = $this->campaignInstitutionRepository->create($campaign, $rows[0]);
        $campaignInstitution->load('institution', 'bankAccount.bank');

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CAMPAIGN_INSTITUTION_ADDED,
            $request,
            $actor->uuid,
            ['campaign_uuid' => $campaign->uuid, 'institution' => $campaignInstitution->institution->name],
            $actor->displayName().' added '.$campaignInstitution->institution->name.' to campaign: '.$campaign->title.'.',
            Campaign::class,
            $campaign->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $campaignInstitution;
    }

    /**
     * Matches rows by `institution_id` (unique per campaign): updates matches, creates new
     * ones, deletes rows no longer present.
     *
     * @param  list<array<string, mixed>>  $institutions
     * @return Collection<int, CampaignInstitution>
     */
    public function syncInstitutions(string $uuid, array $institutions, Admin $actor, Request $request): Collection
    {
        $campaign = $this->findForAdmin($uuid);
        $this->assertNationalGivingDay($campaign);

        $rows = $this->resolveInstitutionRows($institutions);

        $campaignInstitutions = DB::transaction(function () use ($campaign, $rows) {
            $existing = $this->campaignInstitutionRepository->allForCampaign($campaign->id)->keyBy('institution_id');
            $submittedInstitutionIds = array_column($rows, 'institution_id');

            foreach ($rows as $row) {
                $current = $existing->get($row['institution_id']);

                if ($current instanceof CampaignInstitution) {
                    $this->campaignInstitutionRepository->update($current, $row);
                } else {
                    $this->campaignInstitutionRepository->create($campaign, $row);
                }
            }

            foreach ($existing as $institutionId => $campaignInstitution) {
                if (! in_array($institutionId, $submittedInstitutionIds, true)) {
                    $this->campaignInstitutionRepository->delete($campaignInstitution);
                }
            }

            return $this->campaignInstitutionRepository->allForCampaign($campaign->id);
        });

        $campaignInstitutions->load('institution', 'bankAccount.bank');

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CAMPAIGN_INSTITUTION_UPDATED,
            $request,
            $actor->uuid,
            ['campaign_uuid' => $campaign->uuid, 'institution_count' => $campaignInstitutions->count()],
            $actor->displayName().' updated the institution allocations for campaign: '.$campaign->title.'.',
            Campaign::class,
            $campaign->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $campaignInstitutions;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateInstitution(string $uuid, string $institutionUuid, array $payload, Admin $actor, Request $request): CampaignInstitution
    {
        $campaign = $this->findForAdmin($uuid);
        $this->assertNationalGivingDay($campaign);

        $campaignInstitution = $this->campaignInstitutionRepository->findForCampaign($campaign->id, $institutionUuid);
        if (! $campaignInstitution instanceof CampaignInstitution) {
            throw new ApiException('Campaign institution not found.', 404);
        }

        $updates = [];
        foreach (['goal_amount', 'currency'] as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = $payload[$field];
            }
        }

        if (array_key_exists('bank_account_id', $payload)) {
            $bankAccount = $this->bankAccountRepository->findByUuid((string) $payload['bank_account_id']);
            if (! $bankAccount instanceof BankAccount) {
                throw new ApiException('The selected bank account does not exist.', 422);
            }
            $updates['bank_account_id'] = $bankAccount->id;
        }

        if ($updates !== []) {
            $campaignInstitution = $this->campaignInstitutionRepository->update($campaignInstitution, $updates);
        }
        $campaignInstitution->load('institution', 'bankAccount.bank');

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CAMPAIGN_INSTITUTION_UPDATED,
            $request,
            $actor->uuid,
            ['campaign_uuid' => $campaign->uuid, 'campaign_institution_uuid' => $campaignInstitution->uuid, 'fields' => array_keys($updates)],
            $actor->displayName().' updated an institution allocation on campaign: '.$campaign->title.'.',
            Campaign::class,
            $campaign->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $campaignInstitution;
    }

    public function removeInstitution(string $uuid, string $institutionUuid, Admin $actor, Request $request): void
    {
        $campaign = $this->findForAdmin($uuid);
        $this->assertNationalGivingDay($campaign);

        $campaignInstitution = $this->campaignInstitutionRepository->findForCampaign($campaign->id, $institutionUuid);
        if (! $campaignInstitution instanceof CampaignInstitution) {
            throw new ApiException('Campaign institution not found.', 404);
        }

        $institutionName = $campaignInstitution->institution->name;
        $this->campaignInstitutionRepository->delete($campaignInstitution);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CAMPAIGN_INSTITUTION_REMOVED,
            $request,
            $actor->uuid,
            ['campaign_uuid' => $campaign->uuid, 'institution' => $institutionName],
            $actor->displayName().' removed '.$institutionName.' from campaign: '.$campaign->title.'.',
            Campaign::class,
            $campaign->uuid,
            ModuleEnums::fundraising,
            200,
        );
    }

    private function assertNationalGivingDay(Campaign $campaign): void
    {
        if ($campaign->type !== CampaignTypeEnum::NATIONAL_GIVING_DAY->value) {
            throw new ApiException('Institutions can only be managed on a National Giving Day campaign.', 422);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listDonations(string $uuid, array $filters): LengthAwarePaginator
    {
        $campaign = $this->findForAdmin($uuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->paymentRepository->paginateForCampaign($campaign->id, $filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listPledges(string $uuid, array $filters): LengthAwarePaginator
    {
        $campaign = $this->findForAdmin($uuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->pledgeRepository->paginateForCampaign($campaign->id, $filters, $perPage);
    }

    /**
     * Backs the "Donation" tab's Overview cards. The target is the campaign's fixed goal; the
     * received total is scoped to the requested date window (all-time if none given).
     *
     * @param  array<string, mixed>  $filters
     * @return array{period: ?string, start_date: ?string, end_date: ?string, target_amount: string, target_amount_formatted: string, received_amount: string, received_amount_formatted: string}
     */
    public function donationsOverview(string $uuid, array $filters): array
    {
        $campaign = $this->findForAdmin($uuid);
        $window = ListingFilterRules::resolveDateWindow($filters);

        $received = $this->paymentRepository->sumSuccessfulForCampaign(
            $campaign->id,
            $window['start']?->toDateString(),
            $window['end']?->toDateString(),
        );

        return array_merge(ListingFilterRules::periodMeta($filters), [
            'target_amount' => (string) $campaign->goal_amount,
            'target_amount_formatted' => Money::format($campaign->goal_amount, $campaign->currency),
            'received_amount' => $received,
            'received_amount_formatted' => Money::format($received, $campaign->currency),
        ]);
    }

    /**
     * Donor counts per recognition tier (BRD REC-01/REC-02). `$filters` only decides which
     * donors are counted (did they give within the window); each counted donor's tier is
     * still their lifetime, cross-campaign total, matching the Recognition Wall itself.
     *
     * @param  array<string, mixed>  $filters
     * @return array{period: ?string, start_date: ?string, end_date: ?string, tiers: list<array{tier: string, donors: int}>}
     */
    public function donorBreakdown(string $uuid, array $filters): array
    {
        $campaign = $this->findForAdmin($uuid);
        $window = ListingFilterRules::resolveDateWindow($filters);

        $userIds = $this->paymentRepository->distinctSuccessfulDonorUserIdsForCampaign(
            $campaign->id,
            $window['start']?->toDateString(),
            $window['end']?->toDateString(),
        );

        $tiers = $this->donorTierRepository->allOrderedByThreshold();
        $counts = [];
        foreach ($tiers as $tier) {
            $counts[$tier->name] = 0;
        }

        foreach ($userIds as $userId) {
            $lifetimeTotal = $this->paymentRepository->sumSuccessfulForUser($userId, null, null);
            $tier = $this->donorTierRepository->findForAmount($lifetimeTotal);
            if ($tier !== null) {
                $counts[$tier->name]++;
            }
        }

        return array_merge(ListingFilterRules::periodMeta($filters), [
            'tiers' => $tiers->map(fn ($tier) => [
                'tier' => $tier->name,
                'donors' => $counts[$tier->name],
            ])->values()->all(),
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while ($this->campaignRepository->slugExists($slug)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}

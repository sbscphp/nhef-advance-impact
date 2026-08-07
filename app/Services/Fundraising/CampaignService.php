<?php

namespace App\Services\Fundraising;

use App\Enums\AuditActionEnum;
use App\Enums\CampaignStatusEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Admin;
use App\Models\BankAccount;
use App\Models\Campaign;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;
use App\Repositories\Contracts\BankAccount\BankAccountRepositoryInterface;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

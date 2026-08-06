<?php

namespace App\Services\Fundraising;

use App\Enums\AuditActionEnum;
use App\Enums\CampaignStatusEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\BankAccount;
use App\Models\Campaign;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;
use App\Repositories\Contracts\BankAccount\BankAccountRepositoryInterface;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CampaignService
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly AdminRepositoryInterface $adminRepository,
        private readonly BankAccountRepositoryInterface $bankAccountRepository,
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

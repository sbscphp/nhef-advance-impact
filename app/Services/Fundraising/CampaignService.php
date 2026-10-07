<?php

namespace App\Services\Fundraising;

use App\Enums\AdminScopeEnum;
use App\Enums\AuditActionEnum;
use App\Enums\CampaignStatusEnum;
use App\Enums\CampaignTypeEnum;
use App\Enums\ePermission;
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
use App\Models\CampaignProject;
use App\Models\Institution;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;
use App\Repositories\Contracts\BankAccount\BankAccountRepositoryInterface;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\CampaignInstitution\CampaignInstitutionRepositoryInterface;
use App\Repositories\Contracts\CampaignProject\CampaignProjectRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use App\Support\HtmlSanitizer;
use App\Support\Money;
use App\Support\ViewerVisibility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
        private readonly CampaignProjectRepositoryInterface $campaignProjectRepository,
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

    public function recentDonors(Campaign $campaign, int $perPage): LengthAwarePaginator
    {
        return $this->paymentRepository->paginateRecentDonorsForCampaign($campaign->id, $perPage);
    }

    public function daysRemaining(Campaign $campaign): ?int
    {
        if ($campaign->ends_at === null) {
            return null;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($campaign->ends_at->copy()->startOfDay(), false));
    }

    /**
     * The campaign's overall target is the sum of its projects' goals, not the institutions'
     * goals: an institution's own goal is how much *that institution* intends to raise toward
     * the shared target, not a second definition of the target itself. `$campaign->projects`
     * must be eager-loaded by the caller. Raised amount still aggregates actual institution-level
     * payments and pledges (summing across currencies isn't meaningful, so NGN institutions
     * only); Phase 1 only tracks money at the institution level, not per project.
     *
     * @return array{goal_amount: string, raised_amount: string, currency: string}
     */
    public function nationalGivingDayTotals(Campaign $campaign): array
    {
        $goalAmount = (string) $campaign->projects->sum('goal_amount');

        $rows = $this->campaignInstitutionRepository->allForCampaign($campaign->id)
            ->filter(fn (CampaignInstitution $row) => $row->currency === 'NGN');

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
        if (! $actor->checkPermissionTo(ePermission::CAMPAIGNS_CREATE_STANDARD->value)) {
            throw new ApiException('Standard campaigns can only be created by an institution. NHEF creates National Giving Day campaigns on an institution\'s behalf instead.', 403);
        }

        $assignedAdmin = $this->adminRepository->findByUuid((string) $payload['assigned_admin_id']);
        if (! $assignedAdmin instanceof Admin) {
            throw new ApiException('The selected officer does not exist.', 422);
        }

        $bankAccount = $this->bankAccountRepository->findByUuid((string) $payload['bank_account_id']);
        if (! $bankAccount instanceof BankAccount) {
            throw new ApiException('The selected bank account does not exist.', 422);
        }

        $tenant = $this->assertBelongsToOwnInstitution($assignedAdmin, $bankAccount);

        $coverUrl = FileUploadHelper::smartSingleFileUpload($payload['cover'] ?? null, 'campaigns/covers');

        $campaign = $this->campaignRepository->create([
            'title' => $payload['title'],
            'slug' => $this->uniqueSlug((string) $payload['title']),
            // Explicit, not left to the DB column default: create() returns the model as built in
            // memory, never refreshed from DB, so a DB-only default would leave `type` null on the
            // response to this very request (subsequent GETs would still show it correctly).
            'type' => CampaignTypeEnum::STANDARD->value,
            'description' => $payload['description'],
            'cover_image_url' => $coverUrl,
            'currency' => $payload['currency'],
            'goal_amount' => $payload['goal_amount'],
            'raised_amount' => 0,
            'allow_one_time' => true,
            'allow_recurring' => true,
            'allow_anonymous' => true,
            'status' => CampaignStatusEnum::ACTIVE->value,
            'cover_media_type' => $this->coverMediaType($payload['cover'] ?? null, $coverUrl),
            'starts_at' => $this->scheduleValue($payload['starts_at'], false),
            'ends_at' => $this->scheduleValue($payload['ends_at'] ?? null, true),
            'created_by' => $actor->uuid,
            'allocated_admin_id' => $assignedAdmin->id,
            'bank_account_id' => $bankAccount->id,
        ]);

        // A standard campaign has no institution_id of its own; this row is what makes it visible
        // to the creating institution afterward, via Campaign::constrainToTenant().
        $this->campaignInstitutionRepository->create($campaign, [
            'institution_id' => $tenant->id,
            'goal_amount' => $payload['goal_amount'],
            'currency' => $payload['currency'],
            'bank_account_id' => $bankAccount->id,
        ]);

        $campaign->setRelation('allocatedAdmin', $assignedAdmin);
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
        if (! $actor->checkPermissionTo(ePermission::CAMPAIGNS_CREATE_NATIONAL_GIVING_DAY->value)) {
            throw new ApiException('You do not have permission to create a National Giving Day campaign.', 403);
        }

        $assignedAdmin = $this->adminRepository->findByUuid((string) $payload['assigned_admin_id']);
        if (! $assignedAdmin instanceof Admin) {
            throw new ApiException('The selected officer does not exist.', 422);
        }

        if (AdminScopeEnum::current() === AdminScopeEnum::INSTITUTION) {
            $this->assertOwnInstitutionOnly((array) $payload['institutions'], $assignedAdmin);
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
            // A National Giving Day campaign runs for a fixed window (a day or two, see
            // starts_at/ends_at), not an ongoing fund - a recurring/subscription donation would
            // keep charging a donor long after the campaign has ended, so it's never offered here.
            // DonationService/PledgeService already gate on this flag; this is the only place it
            // needs to be set correctly.
            'allow_recurring' => false,
            'allow_anonymous' => true,
            'status' => CampaignStatusEnum::ACTIVE->value,
            'cover_media_type' => $this->coverMediaType($payload['cover'] ?? null, $coverUrl),
            'starts_at' => $this->scheduleValue($payload['starts_at'], false),
            'ends_at' => $this->scheduleValue($payload['ends_at'] ?? null, true),
            'timer_lead_hours' => $payload['timer_lead_hours'] ?? null,
            'created_by' => $actor->uuid,
            'allocated_admin_id' => $assignedAdmin->id,
            'bank_account_id' => null,
        ]);

        $this->campaignInstitutionRepository->createMany($campaign, $institutionRows);
        $this->syncProjects($campaign, (array) ($payload['projects'] ?? []));

        $campaign->setRelation('allocatedAdmin', $assignedAdmin);
        $campaign->load('campaignInstitutions.institution', 'campaignInstitutions.bankAccount.bank', 'projects');

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
                'project_count' => count((array) ($payload['projects'] ?? [])),
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
     * Backstop for an institution admin creating their own National Giving Day campaign: the
     * campaign must target exactly their own institution, never another one, and the assigned
     * officer must belong to that same institution.
     *
     * @param  list<array<string, mixed>>  $institutions
     */
    private function assertOwnInstitutionOnly(array $institutions, Admin $assignedAdmin): void
    {
        if (count($institutions) !== 1) {
            throw new ApiException('An institution can only create a National Giving Day campaign scoped to its own institution.', 403);
        }

        $tenant = $this->assertBelongsToOwnInstitution($assignedAdmin, null);

        if ((string) ($institutions[0]['institution_id'] ?? '') !== $tenant->uuid) {
            throw new ApiException('An institution can only create a National Giving Day campaign scoped to its own institution.', 403);
        }

        $bankAccount = $this->bankAccountRepository->findByUuid((string) ($institutions[0]['bank_account_id'] ?? ''));
        $this->assertBankAccountBelongsToInstitution($bankAccount, $tenant);
    }

    /**
     * Backstop for an institution admin creating their own campaign (standard or National Giving
     * Day): the assigned officer, and optionally a bank account, must belong to their own
     * institution, never NHEF's or another institution's.
     */
    private function assertBelongsToOwnInstitution(Admin $assignedAdmin, ?BankAccount $bankAccount): Institution
    {
        $tenant = Institution::current();
        if ($tenant === null) {
            throw new ApiException('Your institution could not be determined.', 403);
        }

        if ($assignedAdmin->institution_id !== $tenant->id) {
            throw new ApiException('The assigned officer must belong to your own institution.', 422);
        }

        if ($bankAccount !== null) {
            $this->assertBankAccountBelongsToInstitution($bankAccount, $tenant);
        }

        return $tenant;
    }

    private function assertBankAccountBelongsToInstitution(?BankAccount $bankAccount, Institution $tenant): void
    {
        $owner = $bankAccount === null ? null : $this->adminRepository->findByUuid((string) $bankAccount->created_by);
        if ($owner === null || $owner->institution_id !== $tenant->id) {
            throw new ApiException('The selected bank account must belong to your own institution.', 422);
        }
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
        foreach (['title', 'description', 'goal_amount', 'currency', 'timer_lead_hours'] as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = $payload[$field];
            }
        }

        if (array_key_exists('starts_at', $payload)) {
            $updates['starts_at'] = $this->scheduleValue($payload['starts_at'], false);
        }

        if (array_key_exists('ends_at', $payload)) {
            $updates['ends_at'] = $this->scheduleValue($payload['ends_at'], true);
        }

        if (array_key_exists('timer_lead_hours', $payload) || array_key_exists('projects', $payload)) {
            $this->assertNationalGivingDay($campaign, 'The countdown lead time and projects can only be set on a National Giving Day campaign.');
        }

        if (array_key_exists('assigned_admin_id', $payload)) {
            $assignedAdmin = $this->adminRepository->findByUuid((string) $payload['assigned_admin_id']);
            if (! $assignedAdmin instanceof Admin) {
                throw new ApiException('The selected officer does not exist.', 422);
            }
            $updates['allocated_admin_id'] = $assignedAdmin->id;
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
            $updates['cover_media_type'] = $this->coverMediaType($payload['cover'], $updates['cover_image_url']);
        }

        $projectsChanged = array_key_exists('projects', $payload);

        if ($updates === [] && ! $projectsChanged) {
            return $campaign;
        }

        DB::transaction(function () use ($campaign, $updates, $payload, $projectsChanged): void {
            if ($updates !== []) {
                $this->campaignRepository->update($campaign, $updates);
            }

            if ($projectsChanged) {
                $this->syncProjects($campaign, (array) $payload['projects']);
            }
        });

        $campaign = $this->campaignRepository->findByUuid($campaign->uuid) ?? $campaign;

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CAMPAIGN_UPDATED,
            $request,
            $actor->uuid,
            ['campaign_uuid' => $campaign->uuid, 'fields' => array_merge(array_keys($updates), $projectsChanged ? ['projects'] : [])],
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

            $pledges = $this->pledgeRepository->totalsForCampaignAndInstitution($campaign->id, $row->institution->tertiary_institution_id);
            $row->setAttribute('pledges_count', $pledges['count']);
            $row->setAttribute('pledges_total', $pledges['total']);
        }

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, CampaignInstitution>
     */
    public function exportInstitutions(string $uuid, array $filters): Collection
    {
        $campaign = $this->findForAdmin($uuid);
        $rows = $this->campaignInstitutionRepository->exportForCampaign($campaign->id, $filters);

        foreach ($rows as $row) {
            $tertiaryId = $row->institution->tertiary_institution_id;
            $row->setAttribute('raised_amount', (string) ((float) $this->paymentRepository->sumSuccessfulForCampaignAndInstitution($campaign->id, $tertiaryId)
                + (float) $this->pledgeRepository->sumReceivedForCampaignAndInstitution($campaign->id, $tertiaryId)));

            $pledges = $this->pledgeRepository->totalsForCampaignAndInstitution($campaign->id, $tertiaryId);
            $row->setAttribute('pledges_count', $pledges['count']);
            $row->setAttribute('pledges_total', $pledges['total']);
        }

        return $rows;
    }

    /**
     * Adds the list columns (donations, donors, institutions) in three grouped queries for the page.
     *
     * @param  iterable<Campaign>  $campaigns
     */
    public function attachListStats(iterable $campaigns): void
    {
        $campaigns = collect($campaigns);
        $ids = $campaigns->pluck('id')->all();

        $payments = $this->paymentRepository->statsByCampaigns($ids);
        $institutions = $this->campaignInstitutionRepository->countByCampaigns($ids);
        $institutionNames = $this->campaignInstitutionRepository->namesByCampaigns($ids);
        $pledges = $this->pledgeRepository->totalsByCampaigns($ids);

        foreach ($campaigns as $campaign) {
            $campaign->setAttribute('donations_count', $payments[$campaign->id]['donations'] ?? 0);
            $campaign->setAttribute('donors_count', $payments[$campaign->id]['donors'] ?? 0);
            $campaign->setAttribute('institutions_count', $institutions[$campaign->id] ?? 0);
            $campaign->setAttribute('institutions', $institutionNames[$campaign->id] ?? []);
            $campaign->setAttribute('pledges_count', $pledges[$campaign->id]['count'] ?? 0);
        }
    }

    /**
     * Backs the "View Campaign" Overview cards: donation and pledge counts and values.
     *
     * @return array{donations_count: int, amount_generated: string, pledges_count: int, pledges_total: string}
     */
    public function detailOverview(Campaign $campaign): array
    {
        $payments = $this->paymentRepository->statsByCampaigns([$campaign->id]);
        $pledges = $this->pledgeRepository->totalsByCampaigns([$campaign->id]);

        return [
            'donations_count' => $payments[$campaign->id]['donations'] ?? 0,
            'amount_generated' => (string) $campaign->raised_amount,
            'pledges_count' => $pledges[$campaign->id]['count'] ?? 0,
            'pledges_total' => $pledges[$campaign->id]['total'] ?? '0',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addInstitution(string $uuid, array $payload, Admin $actor, Request $request): CampaignInstitution
    {
        if (AdminScopeEnum::current() === AdminScopeEnum::INSTITUTION) {
            throw new ApiException('Only NHEF can add another institution to a campaign.', 403);
        }

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
            $actor->displayName().' updated the institutions on campaign: '.$campaign->title.'.',
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
            $actor->displayName().' updated an institution on campaign: '.$campaign->title.'.',
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

    private function assertNationalGivingDay(Campaign $campaign, string $message = 'Institutions can only be managed on a National Giving Day campaign.'): void
    {
        if ($campaign->type !== CampaignTypeEnum::NATIONAL_GIVING_DAY->value) {
            throw new ApiException($message, 422);
        }
    }

    /** Date-only input means the whole day: start-of-day for a start, end-of-day for an end. */
    private function scheduleValue(mixed $value, bool $isEnd): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $moment = Carbon::parse((string) $value, config('app.timezone'));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $value)) === 1) {
            return $isEnd ? $moment->endOfDay() : $moment->startOfDay();
        }

        return $moment;
    }

    private function coverMediaType(mixed $input, ?string $url): string
    {
        $isVideo = match (true) {
            $input instanceof UploadedFile => str_starts_with((string) $input->getMimeType(), 'video/'),
            is_string($input) && str_starts_with(trim($input), 'data:video/') => true,
            default => $url !== null && preg_match('/\.(mp4|webm|mov)(\?|$)/i', $url) === 1,
        };

        return $isVideo ? 'video' : 'image';
    }

    /**
     * Full replace: rows carrying an existing `uuid` are updated, new ones created, missing ones
     * removed. Project-level donations are not routed yet (planned for a later phase), so nothing
     * references a project row.
     *
     * @param  list<array<string, mixed>>  $projects
     */
    private function syncProjects(Campaign $campaign, array $projects): void
    {
        $existing = $this->campaignProjectRepository->allForCampaign($campaign->id)->keyBy('uuid');
        $keep = [];

        foreach (array_values($projects) as $index => $entry) {
            $data = [
                'name' => $entry['name'],
                'goal_amount' => $entry['goal_amount'],
                'description' => HtmlSanitizer::clean($entry['description'] ?? null),
                'sort_order' => $index,
            ];

            $current = isset($entry['uuid']) ? $existing->get($entry['uuid']) : null;

            if ($current instanceof CampaignProject) {
                $this->campaignProjectRepository->update($current, $data);
                $keep[] = $current->uuid;
            } else {
                $keep[] = $this->campaignProjectRepository->create($campaign, $data)->uuid;
            }
        }

        foreach ($existing as $uuid => $project) {
            if (! in_array($uuid, $keep, true)) {
                $this->campaignProjectRepository->delete($project);
            }
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
    /**
     * Org-wide stat cards for the Fundraising Management dashboard; donations only, no
     * admin-facing pledge aggregation exists yet.
     *
     * @param  array<string, mixed>  $filters
     */
    public function adminOverview(array $filters): array
    {
        $window = ListingFilterRules::resolveDateWindow($filters);
        $start = $window['start']?->toDateString();
        $end = $window['end']?->toDateString();

        $type = filled($filters['type'] ?? null) ? (string) $filters['type'] : null;

        $totalRaised = $this->paymentRepository->sumSuccessfulForAdmin($start, $end, $type);
        $totalDonors = count($this->paymentRepository->distinctSuccessfulDonorUserIdsForAdmin($start, $end, $type));

        return array_merge(ListingFilterRules::periodMeta($filters), [
            'active_campaigns' => $this->campaignRepository->countActive(),
            'ongoing_campaigns' => $this->campaignRepository->countOngoing($type),
            ...ViewerVisibility::money([
                'total_raised' => $totalRaised,
                'total_raised_formatted' => Money::format($totalRaised, 'NGN'),
            ]),
            'total_donors' => $totalDonors,
        ]);
    }

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
            ...ViewerVisibility::money([
                'target_amount' => (string) $campaign->goal_amount,
                'target_amount_formatted' => Money::format($campaign->goal_amount, $campaign->currency),
                'received_amount' => $received,
                'received_amount_formatted' => Money::format($received, $campaign->currency),
            ]),
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

        $tiers = $this->donorTierRepository->activeOrderedByThreshold();
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

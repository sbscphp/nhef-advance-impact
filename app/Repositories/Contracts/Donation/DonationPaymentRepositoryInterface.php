<?php

namespace App\Repositories\Contracts\Donation;

use App\Models\DonationPayment;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DonationPaymentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DonationPayment;

    public function findByReference(string $reference): ?DonationPayment;

    /**
     * Same as {@see self::findByReference()} but with `lockForUpdate()`; call only inside an
     * open transaction, right before settling, to close the race between the webhook and the
     * frontend-driven verify call both reaching the "not yet successful" check at once.
     */
    public function findByReferenceForUpdate(string $reference): ?DonationPayment;

    public function markFailed(DonationPayment $payment): DonationPayment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function markSuccessful(DonationPayment $payment, array $data): DonationPayment;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Same filters as {@see self::paginateForUser()}, capped at MAX_EXPORT_ROWS instead of paginated.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, DonationPayment>, 1: bool}
     */
    public function exportForUser(int $userId, array $filters): array;

    public function findByUuidForUser(int $userId, string $uuid): ?DonationPayment;

    public function sumSuccessfulForUser(int $userId, ?string $from, ?string $to): string;

    public function distinctCampaignGoalTotalForUser(int $userId): string;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForCampaign(int $campaignId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Public "Recent Donors" feed for a campaign's donation page: successful payments only,
     * excluding donations marked anonymous, most recent first.
     */
    public function paginateRecentDonorsForCampaign(int $campaignId, int $perPage): LengthAwarePaginator;

    /**
     * Registered donors with a successful payment to this campaign, optionally date-scoped;
     * feeds the "Donor Breakdown" chart (tiers are lifetime, the window only decides who's counted).
     *
     * @return list<int>
     */
    public function distinctSuccessfulDonorUserIdsForCampaign(int $campaignId, ?string $from, ?string $to): array;

    /**
     * Sum of successful payments for a campaign, optionally scoped to a date window; feeds the
     * "Donation" tab overview (Total Donation Received).
     */
    public function sumSuccessfulForCampaign(int $campaignId, ?string $from, ?string $to): string;

    /**
     * Sum of successful payments for a campaign from donors linked to the given tertiary
     * institution; feeds a National Giving Day campaign's per-institution progress.
     */
    public function sumSuccessfulForCampaignAndInstitution(int $campaignId, int $tertiaryInstitutionId): string;

    /**
     * Org-wide payment listing (not scoped to a user or campaign), for the admin Donation module.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Same filters as {@see self::paginateForAdmin()} but capped instead of paginated.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, DonationPayment>, 1: bool}
     */
    public function exportForAdmin(array $filters): array;

    public function findByUuidForAdmin(string $uuid): ?DonationPayment;

    /**
     * Org-wide sum of successful NGN payments, optionally date-scoped.
     */
    public function sumSuccessfulForAdmin(?string $from, ?string $to, ?string $campaignType = null): string;

    /**
     * Count of successful NGN payments, optionally date-scoped; feeds the dashboard's average
     * donation figure alongside {@see self::sumSuccessfulForAdmin()}.
     */
    public function countSuccessfulForAdmin(?string $from, ?string $to): int;

    /**
     * Org-wide equivalent of {@see self::distinctSuccessfulDonorUserIdsForCampaign()}; feeds
     * the Fundraising Management dashboard's "Total Donors" card.
     *
     * @return list<int>
     */
    public function distinctSuccessfulDonorUserIdsForAdmin(?string $from, ?string $to, ?string $campaignType = null): array;

    /**
     * Successful payment count and distinct donors (registered or guest) per campaign, for the campaign list.
     *
     * @param  list<int>  $campaignIds
     * @return array<int, array{donations: int, donors: int}>
     */
    public function statsByCampaigns(array $campaignIds): array;

    /**
     * Sum of goal_amount across every campaign that has ever received a successful NGN payment;
     * the org-wide equivalent of {@see self::distinctCampaignGoalTotalForUser()}.
     */
    public function distinctCampaignGoalTotalForAdmin(): string;

    /**
     * The paid_at of the earliest successful NGN payment after which this user's running
     * lifetime total first reached $thresholdAmount; null if it never does. Feeds a donor
     * tier's "Date of Upgrade" column without needing a separate tier-change history table.
     */
    public function resolveTierUpgradeDate(int $userId, string $thresholdAmount): ?CarbonInterface;

    /**
     * Successful NGN payments and distinct donors per donor's tertiary institution.
     *
     * @param  list<int>  $tertiaryInstitutionIds
     * @return array<int, array{total: string, donors: int}>
     */
    public function totalsByInstitutions(array $tertiaryInstitutionIds): array;

    /**
     * Distinct successful donors to one campaign who belong to one tertiary institution.
     */
    public function countDonorsForCampaignAndInstitution(int $campaignId, int $tertiaryInstitutionId): int;

    /**
     * Lifetime successful NGN payment count and total per donor, for the constituent list's
     * "No. of Donations" / "Total Donations" columns.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{count: int, total: string}>
     */
    public function totalsByUserIds(array $userIds): array;

    /**
     * Distinct successful NGN donors grouped by their constituent_type, optionally date-scoped;
     * feeds the institution admin dashboard's Donation Intelligence donor breakdown.
     *
     * @return array{alumni: int, non_alumni: int, organization: int}
     */
    public function donorCountsByConstituentType(?string $from, ?string $to): array;

    /**
     * Daily sum of successful NGN payments per donor constituent_type; feeds the dashboard's
     * "Capital inflow by donor segment" trend chart.
     *
     * @return Collection<int, object{date: string, constituent_type: string, total: string}>
     */
    public function dailyTotalsByConstituentType(CarbonInterface $start, CarbonInterface $end): Collection;
}

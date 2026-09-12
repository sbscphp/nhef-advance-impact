<?php

namespace App\Repositories\Contracts\Project;

use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProjectRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?Project;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Project;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data): Project;

    /**
     * @param  array<int, array{admin_id: int, role_title: ?string, is_manager?: bool}>  $members
     */
    public function syncTeamMembers(Project $project, array $members): Project;

    /**
     * @param  list<int>  $userIds
     */
    public function syncFundingDonors(Project $project, array $userIds): Project;

    /**
     * @param  list<int>  $campaignIds
     */
    public function syncFundingCampaigns(Project $project, array $campaignIds): Project;

    /**
     * @return array{all: int, draft: int, active: int, on_hold: int, completed: int, archived: int}
     */
    public function countByStatus(?CarbonInterface $start = null, ?CarbonInterface $end = null): array;

    /**
     * @return array{approved_budget: string, funding_received: string, allocated: string, utilized: string}
     */
    public function budgetSnapshot(?CarbonInterface $start = null, ?CarbonInterface $end = null): array;
}

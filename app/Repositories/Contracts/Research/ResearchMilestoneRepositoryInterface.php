<?php

namespace App\Repositories\Contracts\Research;

use App\Models\Research;
use App\Models\ResearchMilestone;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ResearchMilestoneRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForResearch(Research $research, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, ResearchMilestone>
     */
    public function allForResearch(Research $research): Collection;

    public function findByUuid(string $uuid): ?ResearchMilestone;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $assigneeAdminIds
     * @param  list<int>  $notifyRecipientAdminIds
     */
    public function create(Research $research, array $data, array $assigneeAdminIds, array $notifyRecipientAdminIds = []): ResearchMilestone;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $assigneeAdminIds
     * @param  list<int>|null  $notifyRecipientAdminIds
     */
    public function update(ResearchMilestone $milestone, array $data, ?array $assigneeAdminIds, ?array $notifyRecipientAdminIds = null): ResearchMilestone;

    public function markComplete(ResearchMilestone $milestone): ResearchMilestone;

    public function delete(ResearchMilestone $milestone): void;
}

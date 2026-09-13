<?php

namespace App\Repositories\Contracts\Research;

use App\Models\Research;
use App\Models\ResearchDeliverable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ResearchDeliverableRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForResearch(Research $research, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, ResearchDeliverable>
     */
    public function allForResearch(Research $research): Collection;

    public function findByUuid(string $uuid): ?ResearchDeliverable;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $assigneeAdminIds
     * @param  list<int>  $notifyRecipientAdminIds
     */
    public function create(Research $research, array $data, array $assigneeAdminIds, array $notifyRecipientAdminIds = []): ResearchDeliverable;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $assigneeAdminIds
     * @param  list<int>|null  $notifyRecipientAdminIds
     */
    public function update(ResearchDeliverable $deliverable, array $data, ?array $assigneeAdminIds, ?array $notifyRecipientAdminIds = null): ResearchDeliverable;

    public function markComplete(ResearchDeliverable $deliverable): ResearchDeliverable;

    public function delete(ResearchDeliverable $deliverable): void;
}

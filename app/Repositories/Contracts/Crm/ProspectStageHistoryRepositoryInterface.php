<?php

namespace App\Repositories\Contracts\Crm;

use App\Models\ProspectStageHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface ProspectStageHistoryRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProspectStageHistory;

    public function closeOpenForProspect(int $prospectId, CarbonInterface $exitedAt): void;

    /**
     * @return Collection<int, ProspectStageHistory>
     */
    public function allForProspect(int $prospectId): Collection;
}

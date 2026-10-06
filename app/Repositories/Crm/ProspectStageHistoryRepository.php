<?php

namespace App\Repositories\Crm;

use App\Models\ProspectStageHistory;
use App\Repositories\Contracts\Crm\ProspectStageHistoryRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ProspectStageHistoryRepository implements ProspectStageHistoryRepositoryInterface
{
    public function create(array $data): ProspectStageHistory
    {
        return ProspectStageHistory::create($data);
    }

    public function closeOpenForProspect(int $prospectId, CarbonInterface $exitedAt): void
    {
        ProspectStageHistory::query()
            ->where('prospect_id', $prospectId)
            ->whereNull('exited_at')
            ->update(['exited_at' => $exitedAt]);
    }

    public function allForProspect(int $prospectId): Collection
    {
        return ProspectStageHistory::query()
            ->where('prospect_id', $prospectId)
            ->orderBy('entered_at')
            ->get();
    }
}

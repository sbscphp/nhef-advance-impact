<?php

namespace App\Repositories\Crm;

use App\Models\ProspectCallLog;
use App\Repositories\Contracts\Crm\ProspectCallLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProspectCallLogRepository implements ProspectCallLogRepositoryInterface
{
    public function create(array $data): ProspectCallLog
    {
        return ProspectCallLog::create($data);
    }

    public function findByUuidForProspect(int $prospectId, string $uuid): ?ProspectCallLog
    {
        return ProspectCallLog::query()
            ->with(['logger'])
            ->where('prospect_id', $prospectId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function paginateForProspect(int $prospectId, array $filters, int $perPage): LengthAwarePaginator
    {
        return ProspectCallLog::query()
            ->with(['logger'])
            ->where('prospect_id', $prospectId)
            ->orderByDesc('call_date')
            ->paginate($perPage);
    }
}

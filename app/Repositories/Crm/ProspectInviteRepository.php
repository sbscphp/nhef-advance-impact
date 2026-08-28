<?php

namespace App\Repositories\Crm;

use App\Models\ProspectInvite;
use App\Repositories\Contracts\Crm\ProspectInviteRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProspectInviteRepository implements ProspectInviteRepositoryInterface
{
    public function create(array $data): ProspectInvite
    {
        return ProspectInvite::create($data);
    }

    public function findByUuidForProspect(int $prospectId, string $uuid): ?ProspectInvite
    {
        return ProspectInvite::query()
            ->with(['sender'])
            ->where('prospect_id', $prospectId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function paginateForProspect(int $prospectId, array $filters, int $perPage): LengthAwarePaginator
    {
        return ProspectInvite::query()
            ->with(['sender'])
            ->where('prospect_id', $prospectId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}

<?php

namespace App\Repositories\Crm;

use App\Models\ProspectMessage;
use App\Repositories\Contracts\Crm\ProspectMessageRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProspectMessageRepository implements ProspectMessageRepositoryInterface
{
    public function create(array $data): ProspectMessage
    {
        return ProspectMessage::create($data);
    }

    public function findByUuid(string $uuid): ?ProspectMessage
    {
        return ProspectMessage::query()
            ->with(['sender', 'prospect'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function findByUuidForProspect(int $prospectId, string $uuid): ?ProspectMessage
    {
        return ProspectMessage::query()
            ->with(['sender'])
            ->where('prospect_id', $prospectId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function update(ProspectMessage $message, array $data): ProspectMessage
    {
        $message->forceFill($data)->save();

        return $message;
    }

    public function paginateForProspect(int $prospectId, array $filters, int $perPage): LengthAwarePaginator
    {
        return ProspectMessage::query()
            ->with(['sender'])
            ->where('prospect_id', $prospectId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}

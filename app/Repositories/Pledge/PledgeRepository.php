<?php

namespace App\Repositories\Pledge;

use App\Models\Pledge;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class PledgeRepository implements PledgeRepositoryInterface
{
    public function create(array $data): Pledge
    {
        return Pledge::create($data);
    }

    public function findByUuid(string $uuid): ?Pledge
    {
        return Pledge::query()->where('uuid', $uuid)->first();
    }

    public function findByUuidForUser(int $userId, string $uuid): ?Pledge
    {
        return Pledge::query()
            ->with(['campaign', 'installments' => fn ($query) => $query->orderBy('sequence'), 'payments'])
            ->where('user_id', $userId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function paginateForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator
    {
        return Pledge::query()
            ->with('campaign')
            ->where('user_id', $userId)
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function update(Pledge $pledge, array $data): Pledge
    {
        $pledge->forceFill($data)->save();

        return $pledge;
    }

    public function incrementAmountPaid(Pledge $pledge, string $amount): Pledge
    {
        $pledge->forceFill(['amount_paid' => (float) $pledge->amount_paid + (float) $amount])->save();

        return $pledge;
    }

    public function loadFresh(Pledge $pledge, array $relations): Pledge
    {
        return $pledge->fresh($relations);
    }
}

<?php

namespace App\Repositories\Donation;

use App\Models\Donation;
use App\Repositories\Contracts\Donation\DonationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class DonationRepository implements DonationRepositoryInterface
{
    public function create(array $data): Donation
    {
        return Donation::create($data);
    }

    public function findByUuid(string $uuid): ?Donation
    {
        return Donation::query()->where('uuid', $uuid)->first();
    }

    public function findByUuidForUser(int $userId, string $uuid): ?Donation
    {
        return Donation::query()
            ->with(['user', 'campaign', 'payments'])
            ->where('user_id', $userId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function paginateForUser(int $userId, array $filters, int $perPage): LengthAwarePaginator
    {
        return Donation::query()
            ->with(['user', 'campaign'])
            ->where('user_id', $userId)
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function update(Donation $donation, array $data): Donation
    {
        $donation->forceFill($data)->save();

        return $donation;
    }

    public function incrementTotalReceived(Donation $donation, string $amount): Donation
    {
        $donation->forceFill(['total_received' => (float) $donation->total_received + (float) $amount])->save();

        return $donation;
    }

    public function loadFresh(Donation $donation, array $relations): Donation
    {
        return $donation->fresh($relations);
    }
}

<?php

namespace App\Repositories\Donation;

use App\Enums\DonationFrequencyEnum;
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
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->whereHas('campaign', fn ($q) => $q->where('title', 'like', '%'.$filters['search'].'%'))
            )
            ->when(
                // filled() treats `false` as a real value here (not blank), so this correctly
                // applies for both is_recurring=true and is_recurring=false, and only skips
                // when the filter is genuinely absent (null/not provided).
                filled($filters['is_recurring'] ?? null),
                fn ($query) => $filters['is_recurring']
                    ? $query->where('frequency', '!=', DonationFrequencyEnum::ONE_TIME->value)
                    : $query->where('frequency', '=', DonationFrequencyEnum::ONE_TIME->value)
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

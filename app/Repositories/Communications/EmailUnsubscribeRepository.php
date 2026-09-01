<?php

namespace App\Repositories\Communications;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\EmailUnsubscribe;
use App\Repositories\Contracts\Communications\EmailUnsubscribeRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EmailUnsubscribeRepository implements EmailUnsubscribeRepositoryInterface
{
    public function create(array $data): EmailUnsubscribe
    {
        return EmailUnsubscribe::query()->firstOrCreate(
            ['email' => $data['email']],
            $data,
        );
    }

    public function isUnsubscribed(string $email): bool
    {
        return EmailUnsubscribe::query()->where('email', $email)->exists();
    }

    public function unsubscribedEmails(array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        return EmailUnsubscribe::query()->whereIn('email', $emails)->pluck('email')->all();
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, EmailUnsubscribe>, 1: bool}
     */
    public function exportCollection(array $filters, int $maxRows): array
    {
        $query = $this->filteredQuery($filters);
        $total = (clone $query)->count();
        $truncated = $total > $maxRows;

        return [$query->limit($maxRows)->get(), $truncated];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = EmailUnsubscribe::query()
            ->with(['user', 'mail'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('email', 'like', '%'.$filters['search'].'%')
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'unsubscribed_at');
        ListingFilterRules::applySort($query, $filters, [], 'unsubscribed_at');

        return $query;
    }

    public function recent(int $limit): Collection
    {
        return EmailUnsubscribe::query()
            ->with(['user', 'mail'])
            ->latest('unsubscribed_at')
            ->limit($limit)
            ->get();
    }
}

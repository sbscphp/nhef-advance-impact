<?php

namespace App\Repositories\Communications;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Mail;
use App\Repositories\Contracts\Communications\MailRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MailRepository implements MailRepositoryInterface
{
    public function create(array $data): Mail
    {
        return Mail::create($data);
    }

    public function findByUuid(string $uuid): ?Mail
    {
        return Mail::query()->with(['creator', 'sender'])->where('uuid', $uuid)->first();
    }

    public function update(Mail $mail, array $data): Mail
    {
        $mail->forceFill($data)->save();

        // fresh(), not refresh(): refresh() would try to reload MailService's pickedRecipients
        // pseudo-relation as a real one and crash.
        return $mail->fresh(['creator', 'sender']) ?? $mail;
    }

    public function delete(Mail $mail): void
    {
        $mail->delete();
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)->withCount('recipients')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, Mail>, 1: bool}
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
        $query = Mail::query()
            ->with(['creator', 'sender'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $query->where('status', $filters['filters']['status'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');
        ListingFilterRules::applySort($query, $filters, [], 'created_at');

        return $query;
    }

    public function countByStatus(): array
    {
        return Mail::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    public function countInRange(?CarbonInterface $start, ?CarbonInterface $end): int
    {
        return (int) Mail::query()
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->count();
    }
}

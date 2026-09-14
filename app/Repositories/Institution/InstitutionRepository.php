<?php

namespace App\Repositories\Institution;

use App\Enums\InstitutionStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Institution;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class InstitutionRepository implements InstitutionRepositoryInterface
{
    public function all(bool $activeOnly): Collection
    {
        return Institution::query()
            ->with('tertiaryInstitution')
            ->when($activeOnly, fn ($query) => $query->active())
            ->orderBy('name')
            ->get();
    }

    public function count(bool $activeOnly): int
    {
        return (int) Institution::query()
            ->when($activeOnly, fn ($query) => $query->active())
            ->count();
    }

    public function findByUuid(string $uuid): ?Institution
    {
        return Institution::query()->with('tertiaryInstitution')->where('uuid', $uuid)->first();
    }

    public function nameExists(string $name): bool
    {
        return Institution::query()->where('name', $name)->exists();
    }

    public function emailExists(string $email): bool
    {
        return Institution::query()->where('email', $email)->exists();
    }

    public function existsForTertiaryInstitution(int $tertiaryInstitutionId, ?int $excludeInstitutionId = null): bool
    {
        return Institution::query()
            ->where('tertiary_institution_id', $tertiaryInstitutionId)
            ->when($excludeInstitutionId !== null, fn ($query) => $query->where('id', '!=', $excludeInstitutionId))
            ->exists();
    }

    public function create(array $data): Institution
    {
        $institution = Institution::query()->create($data);
        $institution->load('tertiaryInstitution');

        return $institution;
    }

    public function update(Institution $institution, array $data): Institution
    {
        $institution->update($data);

        return $institution->fresh('tertiaryInstitution') ?? $institution;
    }

    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Institution::query()
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where(function ($query) use ($filters) {
                    $query->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('email', 'like', '%'.$filters['search'].'%');
                })
            )
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $query->where('status', $filters['filters']['status'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('name', $direction),
        ], 'created_at');

        return $query->paginate($perPage);
    }

    public function countByStatus(?CarbonInterface $start, ?CarbonInterface $end): array
    {
        $scoped = fn () => Institution::query()
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end));

        return [
            'all' => (int) $scoped()->count(),
            'active' => (int) $scoped()->where('status', InstitutionStatusEnum::ACTIVE->value)->count(),
            'access_revoked' => (int) $scoped()->where('status', InstitutionStatusEnum::ACCESS_REVOKED->value)->count(),
        ];
    }
}

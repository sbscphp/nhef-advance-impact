<?php

namespace App\Repositories\TertiaryInstitution;

use App\Models\TertiaryInstitution;
use App\Repositories\Contracts\TertiaryInstitution\TertiaryInstitutionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class TertiaryInstitutionRepository implements TertiaryInstitutionRepositoryInterface
{
    public function findByUuid(string $uuid): ?TertiaryInstitution
    {
        return TertiaryInstitution::query()->where('uuid', $uuid)->first();
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return TertiaryInstitution::query()
            ->when(filled($filters['search'] ?? null), function ($builder) use ($filters) {
                $search = $filters['search'];
                $builder->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('abbreviation', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findOrCreateByName(string $name): TertiaryInstitution
    {
        $name = trim($name);

        $existing = TertiaryInstitution::query()->whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
        if ($existing instanceof TertiaryInstitution) {
            return $existing;
        }

        return TertiaryInstitution::create([
            'name' => $name,
            'is_verified' => false,
        ]);
    }
}

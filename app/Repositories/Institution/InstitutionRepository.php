<?php

namespace App\Repositories\Institution;

use App\Models\Institution;
use App\Repositories\Contracts\Institution\InstitutionRepositoryInterface;
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

    public function findByUuid(string $uuid): ?Institution
    {
        return Institution::query()->with('tertiaryInstitution')->where('uuid', $uuid)->first();
    }

    public function nameExists(string $name): bool
    {
        return Institution::query()->where('name', $name)->exists();
    }

    public function create(array $data): Institution
    {
        $institution = Institution::query()->create($data);
        $institution->load('tertiaryInstitution');

        return $institution;
    }
}

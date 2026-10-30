<?php

namespace App\Repositories\Admin;

use App\Enums\eRole;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Admin;
use App\Models\Institution;
use App\Models\Scopes\TenantScope;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminRepository implements AdminRepositoryInterface
{
    public function findByUuid(string $uuid): ?Admin
    {
        return Admin::query()->where('uuid', $uuid)->first();
    }

    public function listActive(): Collection
    {
        return Admin::query()
            ->when(! Institution::checkCurrent(), fn ($query) => $query->nhefStaff())
            ->where('is_active', true)
            ->where('can_login', true)
            ->orderBy('name')
            ->get();
    }

    public function uuidsForInstitutions(array $institutionIds): array
    {
        if ($institutionIds === []) {
            return [];
        }

        return Admin::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('institution_id', $institutionIds)
            ->where('is_active', true)
            ->where('can_login', true)
            ->pluck('uuid')
            ->all();
    }

    public function uuidsForInstitutionsWithRole(array $institutionIds, string $role): array
    {
        if ($institutionIds === []) {
            return [];
        }

        return Admin::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('institution_id', $institutionIds)
            ->role($role)
            ->where('is_active', true)
            ->where('can_login', true)
            ->pluck('uuid')
            ->all();
    }

    public function emailExists(string $email): bool
    {
        return Admin::query()->withoutGlobalScope(TenantScope::class)->withTrashed()->where('email', $email)->exists();
    }

    public function paginateForInstitution(int $institutionId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Admin::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('institution_id', $institutionId)
            ->with('roles:id,name');

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $status = data_get($filters, 'filters.status');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('name', $direction),
        ], 'created_at');

        return $query->paginate($perPage);
    }

    public function createInstitutionOwner(Institution $institution, string $name, string $email): Admin
    {
        $admin = Admin::query()->create([
            'name' => $name,
            'email' => $email,
            'institution_id' => $institution->id,
            'password' => bin2hex(random_bytes(16)),
            'is_active' => true,
            'can_login' => true,
            'must_reset_password' => true,
        ]);

        $admin->syncRoles([eRole::INSTITUTION_ADMIN->value]);

        return $admin;
    }
}

<?php

namespace App\Repositories\Admin;

use App\Enums\eRole;
use App\Models\Admin;
use App\Models\Institution;
use App\Models\Scopes\TenantScope;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

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

    public function emailExists(string $email): bool
    {
        return Admin::query()->withoutGlobalScope(TenantScope::class)->withTrashed()->where('email', $email)->exists();
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

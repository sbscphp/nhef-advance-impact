<?php

namespace App\Repositories\Admin;

use App\Models\Admin;
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
            ->where('is_active', true)
            ->where('can_login', true)
            ->orderBy('name')
            ->get();
    }
}

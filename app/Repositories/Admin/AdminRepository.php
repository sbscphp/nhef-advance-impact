<?php

namespace App\Repositories\Admin;

use App\Models\Admin;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;

class AdminRepository implements AdminRepositoryInterface
{
    public function findByUuid(string $uuid): ?Admin
    {
        return Admin::query()->where('uuid', $uuid)->first();
    }
}

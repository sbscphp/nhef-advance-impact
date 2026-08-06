<?php

namespace App\Repositories\Contracts\Admin;

use App\Models\Admin;

interface AdminRepositoryInterface
{
    public function findByUuid(string $uuid): ?Admin;
}

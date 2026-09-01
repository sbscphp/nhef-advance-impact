<?php

namespace App\Repositories\Contracts\Admin;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Collection;

interface AdminRepositoryInterface
{
    public function findByUuid(string $uuid): ?Admin;

    /**
     * Active, login-enabled admins for an "Assigned To" picker.
     *
     * @return Collection<int, Admin>
     */
    public function listActive(): Collection;
}

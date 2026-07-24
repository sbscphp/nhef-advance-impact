<?php

namespace App\Repositories\Contracts\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function create(array $data): User;

    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function update(User $user, array $data): User;

    public function delete(User $user): bool;

    /**
     * Admin usage only.
     */
    public function all(): Collection;
}

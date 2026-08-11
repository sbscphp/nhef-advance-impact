<?php

namespace App\Repositories\Contracts\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function create(array $data): User;

    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByUuid(string $uuid): ?User;

    public function update(User $user, array $data): User;

    public function delete(User $user): bool;

    /**
     * Admin usage only.
     */
    public function all(): Collection;

    /**
     * Alumni matching by name, email, or organisation. Used for the admin "Search & Select
     * Alumni" flow (adding people to a Networking Community/Forum) and the customer-facing
     * alumni directory used to start a direct message; pass `exclude_user_id` to omit the viewer
     * from their own results.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateAlumniSearch(array $filters, int $perPage): LengthAwarePaginator;
}

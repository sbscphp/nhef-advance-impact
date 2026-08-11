<?php

namespace App\Repositories\User;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\User;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findByUuid(string $uuid): ?User
    {
        return User::where('uuid', $uuid)->first();
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }

    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }

    public function all(): Collection
    {
        return User::all();
    }

    public function paginateAlumniSearch(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = User::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', '%'.$search.'%')
                    ->orWhere('lastname', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('organisation_name', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['exclude_user_id'])) {
            $query->where('id', '!=', $filters['exclude_user_id']);
        }

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        // AlumniSearchRequest defaults sort_by to 'name', so this sortMap branch always runs;
        // the fallback orderBy($defaultColumn) below only matters if called without that default.
        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($q, string $direction) => $q->orderBy('firstname', $direction)->orderBy('lastname', $direction),
        ], 'firstname', 'asc');

        return $query->paginate($perPage);
    }
}

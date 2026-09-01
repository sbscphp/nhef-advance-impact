<?php

namespace App\Repositories\User;

use App\Enums\ConstituentStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\User;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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

    public function emailExists(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }

    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = User::query()
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where(function ($query) use ($filters) {
                    $query->where('firstname', 'like', '%'.$filters['search'].'%')
                        ->orWhere('lastname', 'like', '%'.$filters['search'].'%')
                        ->orWhere('email', 'like', '%'.$filters['search'].'%');
                })
            )
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $query->where('status', $filters['filters']['status'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('firstname', $direction)->orderBy('lastname', $direction),
        ], 'created_at');

        return $query->paginate($perPage);
    }

    public function countByStatus(?CarbonInterface $start, ?CarbonInterface $end): array
    {
        $scoped = fn () => User::query()
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end));

        return [
            'all' => (int) $scoped()->count(),
            'active' => (int) $scoped()->where('status', ConstituentStatusEnum::ACTIVE->value)->count(),
            'access_revoked' => (int) $scoped()->where('status', ConstituentStatusEnum::ACCESS_REVOKED->value)->count(),
        ];
    }

    public function paginateForSegment(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->segmentQuery($filters)->paginate($perPage);
    }

    public function resolveSegmentMembers(array $segment): Collection
    {
        if (! $this->hasSegmentCriteria($segment)) {
            return new Collection;
        }

        return $this->segmentQuery($segment)->select(['id', 'email'])->get();
    }

    public function findManyByUuids(array $uuids): Collection
    {
        if ($uuids === []) {
            return new Collection;
        }

        return User::query()->whereIn('uuid', $uuids)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function segmentQuery(array $filters): Builder
    {
        return User::query()
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where(function ($query) use ($filters) {
                    $query->where('firstname', 'like', '%'.$filters['search'].'%')
                        ->orWhere('lastname', 'like', '%'.$filters['search'].'%')
                        ->orWhere('email', 'like', '%'.$filters['search'].'%');
                })
            )
            ->when(
                filled($filters['university'] ?? null),
                fn ($query) => $query->where('university', $filters['university'])
            )
            ->when(
                filled($filters['department'] ?? null),
                fn ($query) => $query->where('department', $filters['department'])
            )
            ->when(
                filled($filters['graduation_year_from'] ?? null),
                fn ($query) => $query->where('year_of_graduation', '>=', $filters['graduation_year_from'])
            )
            ->when(
                filled($filters['graduation_year_to'] ?? null),
                fn ($query) => $query->where('year_of_graduation', '<=', $filters['graduation_year_to'])
            );
    }

    /**
     * @param  array<string, mixed>  $segment
     */
    private function hasSegmentCriteria(array $segment): bool
    {
        return filled($segment['university'] ?? null)
            || filled($segment['department'] ?? null)
            || filled($segment['graduation_year_from'] ?? null)
            || filled($segment['graduation_year_to'] ?? null);
    }
}

<?php

namespace App\Repositories\User;

use App\Enums\ConstituentStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserRepository implements UserRepositoryInterface
{
    private const MAX_EXPORT_ROWS = 5000;

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
        $query = User::query()->with('tertiaryInstitution');

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
        return User::query()->withoutGlobalScope(TenantScope::class)->where('email', $email)->exists();
    }

    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->adminListQuery($filters)->paginate($perPage);
    }

    public function exportForAdmin(array $filters): array
    {
        $query = $this->adminListQuery($filters);
        $total = (clone $query)->count();
        $truncated = $total > self::MAX_EXPORT_ROWS;
        $rows = $query->limit(self::MAX_EXPORT_ROWS)->get();

        return [$rows, $truncated];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<User>
     */
    private function adminListQuery(array $filters): Builder
    {
        $query = User::query()
            ->with('tertiaryInstitution')
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
            )
            ->when(
                filled($filters['filters']['constituent_type'] ?? null),
                fn ($query) => $query->where('constituent_type', $filters['filters']['constituent_type'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('firstname', $direction)->orderBy('lastname', $direction),
        ], 'created_at');

        return $query;
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

    public function countVerified(?CarbonInterface $start, ?CarbonInterface $end): int
    {
        return (int) User::query()
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->whereNotNull('email_verified_at')
            ->count();
    }

    public function paginateForSegment(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->segmentQuery($filters)->with('tertiaryInstitution')->paginate($perPage);
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

    public function paginateForInstitution(int $tertiaryInstitutionId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = User::query()
            ->where('tertiary_institution_id', $tertiaryInstitutionId)
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where(function ($query) use ($filters) {
                    $query->where('firstname', 'like', '%'.$filters['search'].'%')
                        ->orWhere('lastname', 'like', '%'.$filters['search'].'%')
                        ->orWhere('email', 'like', '%'.$filters['search'].'%');
                })
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('firstname', $direction)->orderBy('lastname', $direction),
        ], 'created_at');

        return $query->paginate($perPage);
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
                filled($filters['tertiary_institution_uuid'] ?? null),
                fn ($query) => $query->whereHas('tertiaryInstitution', fn ($q) => $q->where('uuid', $filters['tertiary_institution_uuid']))
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
        return filled($segment['tertiary_institution_uuid'] ?? null)
            || filled($segment['department'] ?? null)
            || filled($segment['graduation_year_from'] ?? null)
            || filled($segment['graduation_year_to'] ?? null);
    }

    public function countByConstituentTypeForInstitutions(array $tertiaryInstitutionIds): array
    {
        if ($tertiaryInstitutionIds === []) {
            return [];
        }

        $counts = [];
        foreach ($tertiaryInstitutionIds as $id) {
            $counts[$id] = ['alumni' => 0, 'non_alumni' => 0, 'organization' => 0];
        }

        $rows = User::query()
            ->select('tertiary_institution_id', 'constituent_type', DB::raw('count(*) as total'))
            ->whereIn('tertiary_institution_id', $tertiaryInstitutionIds)
            ->groupBy('tertiary_institution_id', 'constituent_type')
            ->get();

        foreach ($rows as $row) {
            if (isset($counts[$row->tertiary_institution_id][$row->constituent_type])) {
                $counts[$row->tertiary_institution_id][$row->constituent_type] = (int) $row->total;
            }
        }

        return $counts;
    }

    public function countByConstituentType(?CarbonInterface $start, ?CarbonInterface $end): array
    {
        $rows = User::query()
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->groupBy('constituent_type')
            ->selectRaw('constituent_type, count(*) as total')
            ->pluck('total', 'constituent_type');

        return [
            'alumni' => (int) ($rows['alumni'] ?? 0),
            'non_alumni' => (int) ($rows['non_alumni'] ?? 0),
            'organization' => (int) ($rows['organization'] ?? 0),
        ];
    }

    public function rankInstitutionsByConstituents(?CarbonInterface $start, ?CarbonInterface $end, int $limit): array
    {
        return DB::table('users')
            ->join('institutions', 'institutions.tertiary_institution_id', '=', 'users.tertiary_institution_id')
            ->when($start !== null, fn ($query) => $query->where('users.created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('users.created_at', '<=', $end))
            ->groupBy('institutions.id', 'institutions.uuid', 'institutions.name')
            ->selectRaw("institutions.uuid as institution_uuid, institutions.name as name,
                sum(users.constituent_type = 'alumni') as alumni,
                sum(users.constituent_type = 'non_alumni') as non_alumni,
                sum(users.constituent_type = 'organization') as organization,
                count(*) as total")
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'institution_uuid' => (string) $row->institution_uuid,
                'name' => (string) $row->name,
                'alumni' => (int) $row->alumni,
                'non_alumni' => (int) $row->non_alumni,
                'organization' => (int) $row->organization,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    public function countActiveSince(CarbonInterface $since): int
    {
        return (int) User::query()->where('last_active_at', '>=', $since)->count();
    }

    public function dailyOnboardedByConstituentType(CarbonInterface $start, CarbonInterface $end): \Illuminate\Support\Collection
    {
        return User::query()
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date', 'constituent_type')
            ->selectRaw('DATE(created_at) as date, constituent_type, count(*) as total')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => (object) [
                'date' => (string) $row->date,
                'constituent_type' => (string) $row->constituent_type,
                'total' => (int) $row->total,
            ]);
    }
}

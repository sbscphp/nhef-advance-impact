<?php

namespace App\Repositories\Mentorship;

use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\MenteeProfile;
use App\Repositories\Contracts\Mentorship\MenteeProfileRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class MenteeProfileRepository implements MenteeProfileRepositoryInterface
{
    public function create(array $data): MenteeProfile
    {
        return MenteeProfile::create($data);
    }

    public function findByUuid(string $uuid): ?MenteeProfile
    {
        return MenteeProfile::query()->with(['user', 'activeMatch.mentorProfile.user'])->where('uuid', $uuid)->first();
    }

    public function findByUserId(int $userId): ?MenteeProfile
    {
        return MenteeProfile::query()->with(['activeMatch.mentorProfile.user'])->where('user_id', $userId)->first();
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = MenteeProfile::query()
            ->select('mentee_profiles.*')
            ->with(['user'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->whereHas('user', fn ($userQuery) => $userQuery
                    ->where('firstname', 'like', '%'.$filters['search'].'%')
                    ->orWhere('lastname', 'like', '%'.$filters['search'].'%'))
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'mentee_profiles.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query
                ->leftJoin('users', 'users.id', '=', 'mentee_profiles.user_id')
                ->orderBy('users.firstname', $direction)
                ->orderBy('users.lastname', $direction),
        ], 'mentee_profiles.created_at');

        return $query->paginate($perPage);
    }
}

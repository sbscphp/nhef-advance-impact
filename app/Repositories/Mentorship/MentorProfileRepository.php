<?php

namespace App\Repositories\Mentorship;

use App\Enums\MentorListingStatusEnum;
use App\Enums\MentorReviewStatusEnum;
use App\Enums\MentorshipMatchStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\MentorProfile;
use App\Repositories\Contracts\Mentorship\MentorProfileRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class MentorProfileRepository implements MentorProfileRepositoryInterface
{
    public function create(array $data): MentorProfile
    {
        return MentorProfile::create($data);
    }

    public function findByUuid(string $uuid): ?MentorProfile
    {
        return MentorProfile::query()->with(['user', 'reviewer', 'suspendedBy'])->where('uuid', $uuid)->first();
    }

    public function findByUserId(int $userId): ?MentorProfile
    {
        return MentorProfile::query()->where('user_id', $userId)->first();
    }

    public function paginateForMentee(int $menteeProfileId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = MentorProfile::query()
            ->select('mentor_profiles.*')
            ->with(['user'])
            ->whereHas('matches', fn ($matchQuery) => $matchQuery
                ->where('mentee_profile_id', $menteeProfileId)
                ->whereIn('status', [MentorshipMatchStatusEnum::ACTIVE->value, MentorshipMatchStatusEnum::COMPLETED->value]))
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->whereHas('user', fn ($userQuery) => $userQuery
                    ->where('firstname', 'like', '%'.$filters['search'].'%')
                    ->orWhere('lastname', 'like', '%'.$filters['search'].'%'))
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'mentor_profiles.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query
                ->leftJoin('users', 'users.id', '=', 'mentor_profiles.user_id')
                ->orderBy('users.firstname', $direction)
                ->orderBy('users.lastname', $direction),
        ], 'mentor_profiles.created_at');

        return $query->paginate($perPage);
    }

    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = MentorProfile::query()
            ->select('mentor_profiles.*')
            ->with(['user'])
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $query->where('mentor_profiles.review_status', $filters['filters']['status'])
            )
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->whereHas('user', fn ($userQuery) => $userQuery
                    ->where('firstname', 'like', '%'.$filters['search'].'%')
                    ->orWhere('lastname', 'like', '%'.$filters['search'].'%'))
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'mentor_profiles.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query
                ->leftJoin('users', 'users.id', '=', 'mentor_profiles.user_id')
                ->orderBy('users.firstname', $direction)
                ->orderBy('users.lastname', $direction),
        ], 'mentor_profiles.created_at');

        return $query->paginate($perPage);
    }

    public function candidatesForMatching(): Collection
    {
        return MentorProfile::query()
            ->where('review_status', MentorReviewStatusEnum::APPROVED->value)
            ->where('listing_status', MentorListingStatusEnum::ACTIVE->value)
            ->whereColumn('current_mentee_count', '<', 'max_capacity')
            ->get();
    }

    public function update(MentorProfile $mentor, array $data): MentorProfile
    {
        $mentor->forceFill($data)->save();

        // Refreshes any already-loaded relations (e.g. reviewer, suspendedBy) that were
        // cached as null before this update changed their underlying foreign key.
        return $mentor->refresh();
    }

    public function incrementMenteeCount(MentorProfile $mentor, int $by = 1): MentorProfile
    {
        $mentor->forceFill(['current_mentee_count' => (int) $mentor->current_mentee_count + $by])->save();

        return $mentor;
    }

    public function decrementMenteeCount(MentorProfile $mentor, int $by = 1): MentorProfile
    {
        $mentor->forceFill(['current_mentee_count' => max(0, (int) $mentor->current_mentee_count - $by)])->save();

        return $mentor;
    }
}

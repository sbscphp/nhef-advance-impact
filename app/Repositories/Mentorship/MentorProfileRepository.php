<?php

namespace App\Repositories\Mentorship;

use App\Enums\MentorListingStatusEnum;
use App\Enums\MentorReviewStatusEnum;
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
        return MentorProfile::query()->with(['user'])->where('uuid', $uuid)->first();
    }

    public function findByUserId(int $userId): ?MentorProfile
    {
        return MentorProfile::query()->where('user_id', $userId)->first();
    }

    public function paginateApproved(array $filters, int $perPage): LengthAwarePaginator
    {
        return MentorProfile::query()
            ->with(['user'])
            ->where('review_status', MentorReviewStatusEnum::APPROVED->value)
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->whereHas('user', fn ($userQuery) => $userQuery
                    ->where('firstname', 'like', '%'.$filters['search'].'%')
                    ->orWhere('lastname', 'like', '%'.$filters['search'].'%'))
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
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

        return $mentor;
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

<?php

namespace App\Repositories\Mentorship;

use App\Models\MentorshipReview;
use App\Repositories\Contracts\Mentorship\MentorshipReviewRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class MentorshipReviewRepository implements MentorshipReviewRepositoryInterface
{
    public function create(array $data): MentorshipReview
    {
        return MentorshipReview::create($data);
    }

    public function findForMatch(int $mentorshipMatchId): ?MentorshipReview
    {
        return MentorshipReview::query()->where('mentorship_match_id', $mentorshipMatchId)->first();
    }

    public function paginateForMentor(int $mentorProfileId, int $perPage): LengthAwarePaginator
    {
        return MentorshipReview::query()
            ->with(['match.menteeProfile.user'])
            ->whereHas('match', fn ($query) => $query->where('mentor_profile_id', $mentorProfileId))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}

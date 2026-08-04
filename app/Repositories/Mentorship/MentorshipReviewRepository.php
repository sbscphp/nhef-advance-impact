<?php

namespace App\Repositories\Mentorship;

use App\Http\Requests\Concerns\ListingFilterRules;
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

    public function paginateForMentor(int $mentorProfileId, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = MentorshipReview::query()
            ->select('mentorship_reviews.*')
            ->with(['match.menteeProfile.user'])
            ->whereHas('match', fn ($query) => $query->where('mentor_profile_id', $mentorProfileId));

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'mentorship_reviews.created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query
                ->leftJoin('mentorship_matches', 'mentorship_matches.id', '=', 'mentorship_reviews.mentorship_match_id')
                ->leftJoin('mentee_profiles', 'mentee_profiles.id', '=', 'mentorship_matches.mentee_profile_id')
                ->leftJoin('users', 'users.id', '=', 'mentee_profiles.user_id')
                ->orderBy('users.firstname', $direction)
                ->orderBy('users.lastname', $direction),
        ], 'mentorship_reviews.created_at');

        return $query->paginate($perPage);
    }
}

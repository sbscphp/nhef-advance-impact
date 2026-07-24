<?php

namespace App\Repositories\Contracts\Mentorship;

use App\Models\MentorshipReview;
use Illuminate\Pagination\LengthAwarePaginator;

interface MentorshipReviewRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MentorshipReview;

    public function findForMatch(int $mentorshipMatchId): ?MentorshipReview;

    public function paginateForMentor(int $mentorProfileId, int $perPage): LengthAwarePaginator;
}

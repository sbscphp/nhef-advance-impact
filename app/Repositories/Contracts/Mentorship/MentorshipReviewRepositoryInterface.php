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

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForMentor(int $mentorProfileId, array $filters, int $perPage): LengthAwarePaginator;
}

<?php

namespace App\Repositories\Contracts\Mentorship;

use App\Models\MentorProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface MentorProfileRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MentorProfile;

    public function findByUuid(string $uuid): ?MentorProfile;

    public function findByUserId(int $userId): ?MentorProfile;

    /**
     * Mentors matched (active or completed) to this mentee, for their "My Mentors" list.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForMentee(int $menteeProfileId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * All mentors regardless of review status, for the admin "Applications" listing.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Approved, not paused, and under capacity. Candidate pool for the auto-matching algorithm.
     *
     * @return Collection<int, MentorProfile>
     */
    public function candidatesForMatching(): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MentorProfile $mentor, array $data): MentorProfile;

    public function incrementMenteeCount(MentorProfile $mentor, int $by = 1): MentorProfile;

    public function decrementMenteeCount(MentorProfile $mentor, int $by = 1): MentorProfile;
}

<?php

namespace App\Repositories\Contracts\Mentorship;

use App\Models\MentorshipMatch;

interface MentorshipMatchRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MentorshipMatch;

    public function findActiveForMentee(int $menteeProfileId): ?MentorshipMatch;

    public function findByUuidForMentee(int $menteeProfileId, string $uuid): ?MentorshipMatch;

    public function findActiveForMentorAndMentee(int $mentorProfileId, int $menteeProfileId): ?MentorshipMatch;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MentorshipMatch $match, array $data): MentorshipMatch;
}

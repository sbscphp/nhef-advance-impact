<?php

namespace App\Repositories\Mentorship;

use App\Enums\MentorshipMatchStatusEnum;
use App\Models\MentorshipMatch;
use App\Repositories\Contracts\Mentorship\MentorshipMatchRepositoryInterface;

class MentorshipMatchRepository implements MentorshipMatchRepositoryInterface
{
    public function create(array $data): MentorshipMatch
    {
        return MentorshipMatch::create($data);
    }

    public function findActiveForMentee(int $menteeProfileId): ?MentorshipMatch
    {
        return MentorshipMatch::query()
            ->with(['mentorProfile.user', 'menteeProfile.user'])
            ->where('mentee_profile_id', $menteeProfileId)
            ->where('status', MentorshipMatchStatusEnum::ACTIVE->value)
            ->first();
    }

    public function findByUuidForMentee(int $menteeProfileId, string $uuid): ?MentorshipMatch
    {
        return MentorshipMatch::query()
            ->with(['mentorProfile.user', 'menteeProfile.user'])
            ->where('mentee_profile_id', $menteeProfileId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function findActiveForMentorAndMentee(int $mentorProfileId, int $menteeProfileId): ?MentorshipMatch
    {
        return MentorshipMatch::query()
            ->where('mentor_profile_id', $mentorProfileId)
            ->where('mentee_profile_id', $menteeProfileId)
            ->where('status', MentorshipMatchStatusEnum::ACTIVE->value)
            ->first();
    }

    public function update(MentorshipMatch $match, array $data): MentorshipMatch
    {
        $match->forceFill($data)->save();

        return $match;
    }
}

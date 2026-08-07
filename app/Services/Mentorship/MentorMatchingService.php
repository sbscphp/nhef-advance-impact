<?php

namespace App\Services\Mentorship;

use App\Enums\MentorshipMatchedByEnum;
use App\Enums\MentorshipMatchStatusEnum;
use App\Models\MenteeProfile;
use App\Models\MentorProfile;
use App\Models\MentorshipMatch;
use App\Repositories\Contracts\Mentorship\MentorProfileRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MentorshipMatchRepositoryInterface;

/**
 * The auto-pairing algorithm behind mentee applications: scores each eligible mentor by tag
 * overlap between interest_areas and guidance_areas and picks the best fit. Kept separate from
 * MentorshipService as a distinct, independently testable piece of logic.
 */
class MentorMatchingService
{
    public function __construct(
        private readonly MentorProfileRepositoryInterface $mentorProfileRepository,
        private readonly MentorshipMatchRepositoryInterface $matchRepository,
    ) {}

    /**
     * Returns null if no mentor shares at least one interest area with the mentee; the mentee
     * stays unmatched rather than being paired with someone unrelated to what they asked for.
     */
    public function match(MenteeProfile $mentee): ?MentorshipMatch
    {
        $mentor = $this->bestCandidate($mentee);

        if ($mentor === null) {
            return null;
        }

        $match = $this->matchRepository->create([
            'mentor_profile_id' => $mentor->id,
            'mentee_profile_id' => $mentee->id,
            'status' => MentorshipMatchStatusEnum::ACTIVE->value,
            'matched_by' => MentorshipMatchedByEnum::SYSTEM->value,
            'matched_at' => now(),
        ]);

        $this->mentorProfileRepository->incrementMenteeCount($mentor);

        return $match;
    }

    private function bestCandidate(MenteeProfile $mentee): ?MentorProfile
    {
        $interestAreas = $this->normalizeTags($mentee->interest_areas ?? []);

        $scored = $this->mentorProfileRepository->candidatesForMatching()
            ->map(fn (MentorProfile $mentor): array => [
                'mentor' => $mentor,
                'score' => count(array_intersect($interestAreas, $this->normalizeTags($mentor->guidance_areas ?? []))),
                'remaining_capacity' => (int) $mentor->max_capacity - (int) $mentor->current_mentee_count,
            ])
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->sort(function (array $a, array $b): int {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }

                if ($a['remaining_capacity'] !== $b['remaining_capacity']) {
                    return $b['remaining_capacity'] <=> $a['remaining_capacity'];
                }

                return $a['mentor']->created_at <=> $b['mentor']->created_at;
            });

        $best = $scored->first();

        return $best['mentor'] ?? null;
    }

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    private function normalizeTags(array $tags): array
    {
        return array_values(array_unique(array_map(
            static fn (string $tag): string => strtolower(trim($tag)),
            $tags
        )));
    }
}

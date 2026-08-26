<?php

namespace App\Services\Mentorship;

use App\Enums\MentorshipMatchedByEnum;
use App\Enums\MentorshipMatchStatusEnum;
use App\Models\MenteeProfile;
use App\Models\MentorProfile;
use App\Models\MentorshipMatch;
use App\Repositories\Contracts\Mentorship\MentorProfileRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MentorshipMatchRepositoryInterface;
use Illuminate\Support\Collection;

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
        $best = $this->scoredCandidates($mentee)->first();

        return $best['mentor'] ?? null;
    }

    /**
     * Top mentor candidates for a mentee, ranked by the same tag-overlap score as the
     * auto-matching algorithm, expressed as a 0-100 match percentage for the admin Matching
     * Engine's "Recommended for X" list. Unlike {@see bestCandidate()}, a mentor with zero
     * overlapping tags can still appear here (at 0%) so admins always see the full candidate pool.
     *
     * @return list<array{mentor: MentorProfile, score: int}>
     */
    public function recommendationsFor(MenteeProfile $mentee, int $limit = 5): array
    {
        return $this->scoredCandidates($mentee, includeZeroScore: true)
            ->take($limit)
            ->map(fn (array $row): array => ['mentor' => $row['mentor'], 'score' => $row['match_percentage']])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{mentor: MentorProfile, score: int, match_percentage: int, remaining_capacity: int}>
     */
    private function scoredCandidates(MenteeProfile $mentee, bool $includeZeroScore = false): Collection
    {
        $interestAreas = $this->normalizeTags($mentee->interest_areas ?? []);
        $interestCount = max(count($interestAreas), 1);

        return $this->mentorProfileRepository->candidatesForMatching()
            ->map(function (MentorProfile $mentor) use ($interestAreas, $interestCount): array {
                $guidanceAreas = $this->normalizeTags($mentor->guidance_areas ?? []);
                $overlap = count(array_intersect($interestAreas, $guidanceAreas));

                return [
                    'mentor' => $mentor,
                    'score' => $overlap,
                    'match_percentage' => (int) round(min($overlap / $interestCount, 1) * 100),
                    'remaining_capacity' => (int) $mentor->max_capacity - (int) $mentor->current_mentee_count,
                ];
            })
            ->filter(fn (array $row): bool => $includeZeroScore || $row['score'] > 0)
            ->sort(function (array $a, array $b): int {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }

                if ($a['remaining_capacity'] !== $b['remaining_capacity']) {
                    return $b['remaining_capacity'] <=> $a['remaining_capacity'];
                }

                return $a['mentor']->created_at <=> $b['mentor']->created_at;
            })
            ->values();
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

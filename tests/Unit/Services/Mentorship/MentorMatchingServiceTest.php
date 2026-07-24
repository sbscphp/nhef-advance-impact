<?php

namespace Tests\Unit\Services\Mentorship;

use App\Enums\MentorshipMatchedByEnum;
use App\Enums\MentorshipMatchStatusEnum;
use App\Models\MenteeProfile;
use App\Models\MentorProfile;
use App\Models\MentorshipMatch;
use App\Repositories\Contracts\Mentorship\MentorProfileRepositoryInterface;
use App\Repositories\Contracts\Mentorship\MentorshipMatchRepositoryInterface;
use App\Services\Mentorship\MentorMatchingService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class MentorMatchingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_matches_the_mentor_with_the_highest_tag_overlap(): void
    {
        $mentee = $this->makeMentee(['react', 'product management']);
        $weakMentor = $this->makeMentor(1, ['react'], 10, 0);
        $strongMentor = $this->makeMentor(2, ['react', 'product management'], 10, 0);

        $mentorRepository = Mockery::mock(MentorProfileRepositoryInterface::class);
        $mentorRepository->shouldReceive('candidatesForMatching')->once()
            ->andReturn(new Collection([$weakMentor, $strongMentor]));
        $mentorRepository->shouldReceive('incrementMenteeCount')->once()->with($strongMentor);

        $matchRepository = Mockery::mock(MentorshipMatchRepositoryInterface::class);
        $matchRepository->shouldReceive('create')->once()
            ->withArgs(fn (array $data): bool => $data['mentor_profile_id'] === 2
                && $data['mentee_profile_id'] === 99
                && $data['status'] === MentorshipMatchStatusEnum::ACTIVE->value
                && $data['matched_by'] === MentorshipMatchedByEnum::SYSTEM->value)
            ->andReturn(new MentorshipMatch);

        $service = new MentorMatchingService($mentorRepository, $matchRepository);

        $this->assertNotNull($service->match($mentee));
    }

    public function test_returns_null_when_no_mentor_shares_an_interest_area(): void
    {
        $mentee = $this->makeMentee(['design']);
        $unrelatedMentor = $this->makeMentor(1, ['finance'], 10, 0);

        $mentorRepository = Mockery::mock(MentorProfileRepositoryInterface::class);
        $mentorRepository->shouldReceive('candidatesForMatching')->once()
            ->andReturn(new Collection([$unrelatedMentor]));
        $mentorRepository->shouldNotReceive('incrementMenteeCount');

        $matchRepository = Mockery::mock(MentorshipMatchRepositoryInterface::class);
        $matchRepository->shouldNotReceive('create');

        $service = new MentorMatchingService($mentorRepository, $matchRepository);

        $this->assertNull($service->match($mentee));
    }

    public function test_ties_on_score_are_broken_by_remaining_capacity(): void
    {
        $mentee = $this->makeMentee(['react']);
        $lowRemaining = $this->makeMentor(1, ['react'], 5, 4);
        $highRemaining = $this->makeMentor(2, ['react'], 5, 1);

        $mentorRepository = Mockery::mock(MentorProfileRepositoryInterface::class);
        $mentorRepository->shouldReceive('candidatesForMatching')->once()
            ->andReturn(new Collection([$lowRemaining, $highRemaining]));
        $mentorRepository->shouldReceive('incrementMenteeCount')->once()->with($highRemaining);

        $matchRepository = Mockery::mock(MentorshipMatchRepositoryInterface::class);
        $matchRepository->shouldReceive('create')->once()
            ->withArgs(fn (array $data): bool => $data['mentor_profile_id'] === 2)
            ->andReturn(new MentorshipMatch);

        $service = new MentorMatchingService($mentorRepository, $matchRepository);

        $this->assertNotNull($service->match($mentee));
    }

    public function test_tag_matching_is_case_and_whitespace_insensitive(): void
    {
        $mentee = $this->makeMentee([' React ']);
        $mentor = $this->makeMentor(1, ['react'], 5, 0);

        $mentorRepository = Mockery::mock(MentorProfileRepositoryInterface::class);
        $mentorRepository->shouldReceive('candidatesForMatching')->once()
            ->andReturn(new Collection([$mentor]));
        $mentorRepository->shouldReceive('incrementMenteeCount')->once()->with($mentor);

        $matchRepository = Mockery::mock(MentorshipMatchRepositoryInterface::class);
        $matchRepository->shouldReceive('create')->once()->andReturn(new MentorshipMatch);

        $service = new MentorMatchingService($mentorRepository, $matchRepository);

        $this->assertNotNull($service->match($mentee));
    }

    /**
     * Direct attribute assignment (not constructor mass-assignment) so this stays a pure unit
     * test with no Eloquent guard/schema lookup, and therefore no real database connection.
     *
     * @param  list<string>  $interestAreas
     */
    private function makeMentee(array $interestAreas): MenteeProfile
    {
        $mentee = new MenteeProfile;
        $mentee->id = 99;
        $mentee->interest_areas = $interestAreas;

        return $mentee;
    }

    /**
     * @param  list<string>  $guidanceAreas
     */
    private function makeMentor(int $id, array $guidanceAreas, int $maxCapacity, int $currentMenteeCount): MentorProfile
    {
        $mentor = new MentorProfile;
        $mentor->id = $id;
        $mentor->guidance_areas = $guidanceAreas;
        $mentor->max_capacity = $maxCapacity;
        $mentor->current_mentee_count = $currentMenteeCount;
        $mentor->created_at = now();

        return $mentor;
    }
}

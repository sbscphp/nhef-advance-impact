<?php

namespace App\Services\Reporting\Datasets;

use App\Http\Resources\Mentorship\MentorProfileResource;
use App\Models\MentorProfile;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Matches the "All Mentors" directory table ({@see MentorProfileResource}), one row per mentor.
 */
class MentorReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Mentors';
    }

    public function nativeFields(): array
    {
        return [
            'Identity' => [
                ['key' => 'mentor_name', 'label' => 'Mentor Name', 'type' => 'string'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'string'],
                ['key' => 'phone_number', 'label' => 'Phone Number', 'type' => 'string'],
            ],
            'Institution' => [
                ['key' => 'linked_institution', 'label' => 'Linked Institution', 'type' => 'string'],
            ],
            'Professional' => [
                ['key' => 'current_job_title', 'label' => 'Current Job Title', 'type' => 'string'],
                ['key' => 'company', 'label' => 'Company', 'type' => 'string'],
                ['key' => 'years_of_experience', 'label' => 'Years of Experience', 'type' => 'number'],
            ],
            'Mentorship' => [
                ['key' => 'interest', 'label' => 'Interest', 'type' => 'string'],
                ['key' => 'no_of_mentees', 'label' => 'No. of Mentees', 'type' => 'number'],
                ['key' => 'review_status', 'label' => 'Review Status', 'type' => 'string'],
                ['key' => 'listing_status', 'label' => 'Listing Status', 'type' => 'string'],
            ],
            'Dates' => [
                ['key' => 'date_joined', 'label' => 'Date Joined', 'type' => 'date'],
                ['key' => 'last_active_at', 'label' => 'Last Active', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return MentorProfile::query()
            ->with(['user.tertiaryInstitution'])
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), fn ($query) => $query->whereHas('user', fn ($u) => $u
                ->where('firstname', 'like', '%'.$search.'%')
                ->orWhere('lastname', 'like', '%'.$search.'%')));
    }

    /**
     * @param  MentorProfile  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'mentor_name' => $record->user?->displayName(),
            'email' => $record->user?->email,
            'phone_number' => $record->user?->phone_number,
            'linked_institution' => $record->user?->tertiaryInstitution?->name,
            'current_job_title' => $record->current_job_title,
            'company' => $record->company,
            'years_of_experience' => $record->years_of_experience,
            'interest' => implode(', ', $record->guidance_areas ?? $record->expertise_tags ?? []),
            'no_of_mentees' => $record->current_mentee_count,
            'review_status' => $record->review_status,
            'listing_status' => $record->listing_status,
            'date_joined' => $record->created_at?->toIso8601String(),
            'last_active_at' => $record->user?->last_active_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new MentorProfile)->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'review_status', 'label' => 'Review Status', 'type' => 'string'],
            ['key' => 'listing_status', 'label' => 'Listing Status', 'type' => 'string'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'review_status' => 'review_status',
            'listing_status' => 'listing_status',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(MentorProfile::class, 'created_at', $start, $end);
    }
}

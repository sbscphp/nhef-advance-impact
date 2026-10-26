<?php

namespace App\Services\Reporting\Datasets;

use App\Http\Resources\Mentorship\MenteeProfileResource;
use App\Models\MenteeProfile;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Matches the "All Mentees" directory table ({@see MenteeProfileResource}), one row per mentee.
 */
class MenteeReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Mentees';
    }

    public function nativeFields(): array
    {
        return [
            'Identity' => [
                ['key' => 'mentee_name', 'label' => 'Mentee Name', 'type' => 'string'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'string'],
                ['key' => 'phone_number', 'label' => 'Phone Number', 'type' => 'string'],
            ],
            'Institution' => [
                ['key' => 'linked_institution', 'label' => 'Linked Institution', 'type' => 'string'],
            ],
            'Mentorship' => [
                ['key' => 'linked_mentor', 'label' => 'Linked Mentor', 'type' => 'string'],
                ['key' => 'interest', 'label' => 'Interest', 'type' => 'string'],
                ['key' => 'match_status', 'label' => 'Match Status', 'type' => 'string'],
            ],
            'Dates' => [
                ['key' => 'date_joined', 'label' => 'Date Joined', 'type' => 'date'],
                ['key' => 'last_active_at', 'label' => 'Last Active', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return MenteeProfile::query()
            ->with(['user.tertiaryInstitution', 'activeMatch.mentorProfile.user'])
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), fn ($query) => $query->whereHas('user', fn ($u) => $u
                ->where('firstname', 'like', '%'.$search.'%')
                ->orWhere('lastname', 'like', '%'.$search.'%')));
    }

    /**
     * @param  MenteeProfile  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'mentee_name' => $record->user?->displayName(),
            'email' => $record->user?->email,
            'phone_number' => $record->user?->phone_number,
            'linked_institution' => $record->user?->tertiaryInstitution?->name,
            'linked_mentor' => $record->activeMatch?->mentorProfile?->user?->displayName(),
            'interest' => implode(', ', $record->interest_areas ?? []),
            'match_status' => $record->activeMatch?->status,
            'date_joined' => $record->created_at?->toIso8601String(),
            'last_active_at' => $record->user?->last_active_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new MenteeProfile)->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    /** match_status lives on the related MentorshipMatch, not this table, so nothing here is a plain groupable column. */
    public function groupableFields(): array
    {
        return [];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(MenteeProfile::class, 'created_at', $start, $end);
    }
}

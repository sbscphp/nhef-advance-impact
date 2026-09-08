<?php

namespace App\Services\Reporting\Datasets;

use App\Models\MentorshipMatch;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MentorshipReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Mentorship';
    }

    public function nativeFields(): array
    {
        return [
            'Match' => [
                ['key' => 'mentor_name', 'label' => 'Mentor Name', 'type' => 'string'],
                ['key' => 'mentee_name', 'label' => 'Mentee Name', 'type' => 'string'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                ['key' => 'matched_by', 'label' => 'Matched By', 'type' => 'string'],
                ['key' => 'average_rating', 'label' => 'Average Review Rating', 'type' => 'number'],
            ],
            'Dates' => [
                ['key' => 'matched_at', 'label' => 'Matched At', 'type' => 'date'],
                ['key' => 'completed_at', 'label' => 'Completed At', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return MentorshipMatch::query()
            ->with(['mentorProfile.user', 'menteeProfile.user', 'review'])
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->whereHas('mentorProfile.user', fn ($u) => $u->where('firstname', 'like', '%'.$search.'%')->orWhere('lastname', 'like', '%'.$search.'%'))
                        ->orWhereHas('menteeProfile.user', fn ($u) => $u->where('firstname', 'like', '%'.$search.'%')->orWhere('lastname', 'like', '%'.$search.'%'));
                });
            });
    }

    /**
     * @param  MentorshipMatch  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'mentor_name' => $record->mentorProfile?->user?->displayName(),
            'mentee_name' => $record->menteeProfile?->user?->displayName(),
            'status' => $record->status,
            'matched_by' => $record->matched_by,
            'average_rating' => $record->review?->averageRating(),
            'matched_at' => $record->matched_at?->toIso8601String(),
            'completed_at' => $record->completed_at?->toIso8601String(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new MentorshipMatch())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
            ['key' => 'matched_by', 'label' => 'Matched By', 'type' => 'string'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'status' => 'status',
            'matched_by' => 'matched_by',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(MentorshipMatch::class, 'created_at', $start, $end);
    }
}

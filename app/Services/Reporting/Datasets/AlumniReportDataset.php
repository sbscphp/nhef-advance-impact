<?php

namespace App\Services\Reporting\Datasets;

use App\Models\User;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AlumniReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Alumni';
    }

    public function nativeFields(): array
    {
        return [
            'Identity' => [
                ['key' => 'alumni_id', 'label' => 'Alumni ID', 'type' => 'string'],
                ['key' => 'full_name', 'label' => 'Full Name', 'type' => 'string'],
            ],
            'Contact' => [
                ['key' => 'email', 'label' => 'Email', 'type' => 'string'],
                ['key' => 'phone_number', 'label' => 'Phone Number', 'type' => 'string'],
            ],
            'Academic' => [
                ['key' => 'department', 'label' => 'Department', 'type' => 'string'],
                ['key' => 'matric_no', 'label' => 'Matric No', 'type' => 'string'],
                ['key' => 'year_of_graduation', 'label' => 'Year of Graduation', 'type' => 'number'],
                ['key' => 'degree_earned', 'label' => 'Degree Earned', 'type' => 'string'],
                ['key' => 'tertiary_institution', 'label' => 'Tertiary Institution', 'type' => 'string'],
            ],
            'Status' => [
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                ['key' => 'last_active_at', 'label' => 'Last Active', 'type' => 'date'],
                ['key' => 'joined_at', 'label' => 'Joined Date', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return User::query()
            ->with('tertiaryInstitution')
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('firstname', 'like', '%'.$search.'%')
                        ->orWhere('lastname', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            });
    }

    /**
     * @param  User  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'alumni_id' => $record->uuid,
            'full_name' => $record->displayName(),
            'email' => $record->email,
            'phone_number' => $record->phone_number,
            'department' => $record->department,
            'matric_no' => $record->matric_no,
            'year_of_graduation' => $record->year_of_graduation,
            'degree_earned' => $record->degree_earned,
            'tertiary_institution' => $record->tertiaryInstitution?->name,
            'status' => $record->status,
            'last_active_at' => $record->last_active_at?->toIso8601String(),
            'joined_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new User())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'year_of_graduation', 'label' => 'Year of Graduation', 'type' => 'number'],
            ['key' => 'degree_earned', 'label' => 'Degree Earned', 'type' => 'string'],
            ['key' => 'department', 'label' => 'Department', 'type' => 'string'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'year_of_graduation' => 'year_of_graduation',
            'degree_earned' => 'degree_earned',
            'department' => 'department',
            'status' => 'status',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(User::class, 'created_at', $start, $end);
    }
}

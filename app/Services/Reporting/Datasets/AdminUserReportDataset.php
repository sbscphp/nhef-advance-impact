<?php

namespace App\Services\Reporting\Datasets;

use App\Models\Admin;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminUserReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Admin Users';
    }

    public function nativeFields(): array
    {
        return [
            'Identity' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'string'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'string'],
                ['key' => 'roles', 'label' => 'Roles', 'type' => 'string'],
            ],
            'Access' => [
                ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean'],
                ['key' => 'is_locked', 'label' => 'Locked', 'type' => 'boolean'],
                ['key' => 'last_login_at', 'label' => 'Last Login', 'type' => 'date'],
                ['key' => 'last_active_at', 'label' => 'Last Active', 'type' => 'date'],
            ],
            'Dates' => [
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return Admin::query()
            ->with('roles')
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            });
    }

    /**
     * @param  Admin  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'name' => $record->displayName(),
            'email' => $record->email,
            'roles' => $record->roles->pluck('name')->implode(', '),
            'is_active' => (bool) $record->is_active,
            'is_locked' => (bool) $record->is_locked,
            'last_login_at' => $record->last_login_at?->toIso8601String(),
            'last_active_at' => $record->last_active_at?->toIso8601String(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new Admin())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean'],
            ['key' => 'is_locked', 'label' => 'Locked', 'type' => 'boolean'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'is_active' => 'is_active',
            'is_locked' => 'is_locked',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(Admin::class, 'created_at', $start, $end);
    }
}

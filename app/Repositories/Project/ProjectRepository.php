<?php

namespace App\Repositories\Project;

use App\Enums\ProjectStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Repositories\Contracts\Project\ProjectRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Project::query()
            ->with(['teamMembers', 'creator'])
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where('title', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $query->where('status', $filters['filters']['status'])
            )
            ->when(
                filled($filters['filters']['category'] ?? null),
                fn ($query) => $query->where('category', $filters['filters']['category'])
            );

        ListingFilterRules::applyResolvedDateRange($query, $filters, 'created_at');

        ListingFilterRules::applySort($query, $filters, [
            'name' => fn ($query, string $direction) => $query->orderBy('title', $direction),
            'value' => fn ($query, string $direction) => $query->orderBy('approved_budget', $direction),
        ], 'created_at');

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?Project
    {
        return Project::query()
            ->with([
                'creator', 'teamMembers', 'fundingDonors', 'fundingCampaigns',
                'objectives.assignments.admin',
                'milestones.assignments.admin',
                'deliverables.milestone', 'deliverables.assignments.admin',
            ])
            ->where('uuid', $uuid)
            ->first();
    }

    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function update(Project $project, array $data): Project
    {
        $project->forceFill($data)->save();

        return $project->refresh();
    }

    public function syncTeamMembers(Project $project, array $members): Project
    {
        $syncData = [];
        foreach ($members as $member) {
            $syncData[$member['admin_id']] = [
                'role_title' => $member['role_title'] ?? null,
                'is_manager' => $member['is_manager'] ?? false,
            ];
        }

        $project->teamMembers()->sync($syncData);

        return $project->refresh()->load('teamMembers');
    }

    public function syncFundingDonors(Project $project, array $userIds): Project
    {
        $project->fundingDonors()->sync($userIds);

        return $project->refresh()->load('fundingDonors');
    }

    public function syncFundingCampaigns(Project $project, array $campaignIds): Project
    {
        $project->fundingCampaigns()->sync($campaignIds);

        return $project->refresh()->load('fundingCampaigns');
    }

    public function countByStatus(?CarbonInterface $start = null, ?CarbonInterface $end = null): array
    {
        $scoped = fn (): Builder => $this->applyDateRange(Project::query(), 'created_at', $start, $end);

        return [
            'all' => (int) $scoped()->count(),
            'draft' => (int) $scoped()->where('status', ProjectStatusEnum::DRAFT->value)->count(),
            'active' => (int) $scoped()->where('status', ProjectStatusEnum::ACTIVE->value)->count(),
            'on_hold' => (int) $scoped()->where('status', ProjectStatusEnum::ON_HOLD->value)->count(),
            'completed' => (int) $scoped()->where('status', ProjectStatusEnum::COMPLETED->value)->count(),
            'archived' => (int) $scoped()->where('status', ProjectStatusEnum::ARCHIVED->value)->count(),
        ];
    }

    public function budgetSnapshot(?CarbonInterface $start = null, ?CarbonInterface $end = null): array
    {
        $approvedBudget = (string) $this->applyDateRange(Project::query(), 'created_at', $start, $end)->sum('approved_budget');
        $fundingReceived = (string) $this->applyDateRange(Project::query(), 'created_at', $start, $end)->sum('funding_received');
        $allocated = (string) $this->applyDateRange(ProjectBudgetLine::query(), 'created_at', $start, $end)->sum('amount_allocated');
        $utilized = (string) $this->applyDateRange(
            ProjectBudgetLine::query()->join('project_expenditures', 'project_expenditures.budget_line_id', '=', 'project_budget_lines.id'),
            'project_expenditures.transaction_date',
            $start,
            $end,
        )->sum('project_expenditures.amount');

        return [
            'approved_budget' => $approvedBudget,
            'funding_received' => $fundingReceived,
            'allocated' => $allocated,
            'utilized' => $utilized,
        ];
    }

    private function applyDateRange(Builder $query, string $column, ?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        if ($start !== null) {
            $query->where($column, '>=', $start);
        }

        if ($end !== null) {
            $query->where($column, '<=', $end);
        }

        return $query;
    }
}

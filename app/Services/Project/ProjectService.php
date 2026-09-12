<?php

namespace App\Services\Project;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\ProjectBroadcastDeliveryEnum;
use App\Enums\ProjectCategoryEnum;
use App\Enums\ProjectRiskSeverityEnum;
use App\Enums\ProjectRiskStatusEnum;
use App\Enums\ProjectStatusEnum;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Admin;
use App\Models\Project;
use App\Models\ProjectBroadcast;
use App\Models\ProjectBudgetLine;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDocument;
use App\Models\ProjectExpenditure;
use App\Models\ProjectImpactReport;
use App\Models\ProjectMilestone;
use App\Models\ProjectObjective;
use App\Models\ProjectRisk;
use App\Notifications\GenericDatabaseNotification;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\Project\ProjectBroadcastRepositoryInterface;
use App\Repositories\Contracts\Project\ProjectBudgetLineRepositoryInterface;
use App\Repositories\Contracts\Project\ProjectDeliverableRepositoryInterface;
use App\Repositories\Contracts\Project\ProjectDocumentRepositoryInterface;
use App\Repositories\Contracts\Project\ProjectExpenditureRepositoryInterface;
use App\Repositories\Contracts\Project\ProjectImpactReportRepositoryInterface;
use App\Repositories\Contracts\Project\ProjectMilestoneRepositoryInterface;
use App\Repositories\Contracts\Project\ProjectObjectiveRepositoryInterface;
use App\Repositories\Contracts\Project\ProjectRepositoryInterface;
use App\Repositories\Contracts\Project\ProjectRiskRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use App\Services\Notifications\NotificationDispatchService;
use App\Support\Money;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProjectService
{
    private const MAX_EXPORT_ROWS = 5000;

    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly ProjectObjectiveRepositoryInterface $objectiveRepository,
        private readonly ProjectMilestoneRepositoryInterface $milestoneRepository,
        private readonly ProjectDeliverableRepositoryInterface $deliverableRepository,
        private readonly ProjectBudgetLineRepositoryInterface $budgetLineRepository,
        private readonly ProjectExpenditureRepositoryInterface $expenditureRepository,
        private readonly ProjectImpactReportRepositoryInterface $impactReportRepository,
        private readonly ProjectDocumentRepositoryInterface $documentRepository,
        private readonly ProjectBroadcastRepositoryInterface $broadcastRepository,
        private readonly ProjectRiskRepositoryInterface $riskRepository,
        private readonly AdminRepositoryInterface $adminRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly NotificationDispatchService $notificationDispatchService,
    ) {}

    /**
     * @param  list<string>  $campaignUuids
     * @return list<int>
     */
    private function resolveCampaignIds(array $campaignUuids): array
    {
        return array_map(function (string $uuid) {
            $campaign = $this->campaignRepository->findByUuid($uuid);
            if ($campaign === null) {
                throw new ApiException('Campaign not found.', 404);
            }

            return $campaign->id;
        }, $campaignUuids);
    }

    /**
     * @return list<int>
     */
    private function resolveUserIds(array $userUuids): array
    {
        return array_map(function (string $uuid) {
            $user = $this->userRepository->findByUuid($uuid);
            if ($user === null) {
                throw new ApiException('User not found.', 404);
            }

            return $user->id;
        }, $userUuids);
    }

    private function resolveAdminId(string $adminUuid): int
    {
        $admin = $this->adminRepository->findByUuid($adminUuid);
        if (! $admin instanceof Admin) {
            throw new ApiException('Admin not found.', 404);
        }

        return $admin->id;
    }

    /**
     * @param  list<string>  $adminUuids
     * @return list<int>
     */
    private function resolveAdminIds(array $adminUuids): array
    {
        return array_map(fn (string $uuid) => $this->resolveAdminId($uuid), $adminUuids);
    }

    /**
     * Merges the invited team roster with the separately-selected manager(s): anyone in
     * $managerUuids is flagged is_manager, whether or not they were already in $members.
     *
     * @param  list<array{admin_uuid: string, role_title: ?string}>  $members
     * @param  list<string>  $managerUuids
     * @return list<array{admin_id: int, role_title: ?string, is_manager: bool}>
     */
    private function mapTeamMembers(array $members, array $managerUuids = []): array
    {
        $mapped = [];
        foreach ($members as $member) {
            $mapped[$member['admin_uuid']] = [
                'admin_id' => $this->resolveAdminId($member['admin_uuid']),
                'role_title' => $member['role_title'] ?? null,
                'is_manager' => in_array($member['admin_uuid'], $managerUuids, true),
            ];
        }

        foreach ($managerUuids as $managerUuid) {
            if (! isset($mapped[$managerUuid])) {
                $mapped[$managerUuid] = [
                    'admin_id' => $this->resolveAdminId($managerUuid),
                    'role_title' => null,
                    'is_manager' => true,
                ];
            }
        }

        return array_values($mapped);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->projectRepository->paginateAdmin($filters, $perPage);
    }

    public function findForAdmin(string $uuid): Project
    {
        $project = $this->projectRepository->findByUuid($uuid);

        if (! $project instanceof Project) {
            throw new ApiException('Project not found.', 404);
        }

        return $project;
    }

    /**
     * @return array{all: int, draft: int, active: int, on_hold: int, completed: int, archived: int}
     */
    /**
     * @return array{all: int, draft: int, active: int, on_hold: int, completed: int, archived: int, at_risk: int}
     */
    /**
     * @param  array<string, mixed>  $filters
     */
    public function statusOverview(array $filters = []): array
    {
        $window = ListingFilterRules::resolveDateWindow($filters);

        return [
            ...$this->projectRepository->countByStatus($window['start'], $window['end']),
            'at_risk' => $this->riskRepository->countDistinctProjectsWithOpenRisk($window['start'], $window['end']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{approved_budget: string, funding_received: string, allocated: string, utilized: string, remaining: string}
     */
    public function budgetOverview(array $filters = []): array
    {
        $window = ListingFilterRules::resolveDateWindow($filters);
        $snapshot = $this->projectRepository->budgetSnapshot($window['start'], $window['end']);

        return [
            ...$snapshot,
            'remaining' => bcsub($snapshot['allocated'], $snapshot['utilized'], 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function issuesOverview(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 10), 100));
        $paginator = $this->riskRepository->paginateOpenAcrossProjects($filters, $perPage);

        $paginator->setCollection($paginator->getCollection()->map(fn (ProjectRisk $risk): array => [
            'project_uuid' => $risk->project->uuid,
            'project_name' => $risk->project->title,
            'issue_type' => $risk->title,
            'project_manager' => $risk->project->managers->first()?->displayName(),
            'severity' => $risk->severity,
            'severity_label' => ProjectRiskSeverityEnum::from($risk->severity)->label(),
            'raised_by' => $risk->raisedByAdmin?->displayName(),
            'created_at' => $risk->created_at?->toIso8601String(),
        ]));

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, Admin $actor, Request $request): Project
    {
        $members = $validated['team_members'] ?? [];
        $managerUuids = $validated['manager_uuids'] ?? [];
        $objectives = $validated['objectives'] ?? [];
        $milestonesInput = $validated['milestones'] ?? [];
        $deliverablesInput = $validated['deliverables'] ?? [];
        $budgetLinesInput = $validated['budget_lines'] ?? [];
        $donorUuids = $validated['donor_uuids'] ?? [];
        $campaignUuids = $validated['campaign_uuids'] ?? [];
        unset(
            $validated['team_members'], $validated['manager_uuids'], $validated['objectives'],
            $validated['milestones'], $validated['deliverables'], $validated['budget_lines'],
            $validated['donor_uuids'], $validated['campaign_uuids'],
        );

        if (isset($validated['budget_max'])) {
            $validated['approved_budget'] = $validated['budget_max'];
        }

        $project = $this->projectRepository->create([
            'currency' => 'NGN',
            'approved_budget' => 0,
            'funding_received' => 0,
            ...$validated,
            'status' => ProjectStatusEnum::DRAFT->value,
            'created_by' => $actor->uuid,
        ]);

        if ($members !== [] || $managerUuids !== []) {
            $project = $this->projectRepository->syncTeamMembers($project, $this->mapTeamMembers($members, $managerUuids));
        }

        if ($donorUuids !== []) {
            $project = $this->projectRepository->syncFundingDonors($project, $this->resolveUserIds($donorUuids));
        }

        if ($campaignUuids !== []) {
            $project = $this->projectRepository->syncFundingCampaigns($project, $this->resolveCampaignIds($campaignUuids));
        }

        foreach ($objectives as $objective) {
            $allTeamMembers = (bool) ($objective['all_team_members'] ?? false);
            $assignees = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $objective['assignee_uuids'] ?? []);
            $this->objectiveRepository->create($project, [
                'title' => $objective['title'],
                'description' => $objective['description'] ?? null,
                'all_team_members' => $allTeamMembers,
            ], $assignees);
        }

        // milestone_index on a deliverable refers to its position in $milestonesInput, since a
        // milestone created in this same request has no uuid yet to link against.
        $createdMilestonesByIndex = [];
        foreach ($milestonesInput as $index => $milestone) {
            $allTeamMembers = (bool) ($milestone['all_team_members'] ?? false);
            $assignees = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $milestone['assignee_uuids'] ?? []);
            $notifyAllTeamMembers = (bool) ($milestone['notify_all_team_members'] ?? false);
            $notifyRecipients = $this->resolveNotifyRecipientIds($project, $notifyAllTeamMembers, $milestone['notify_admin_uuids'] ?? []);
            $created = $this->milestoneRepository->create($project, [
                'title' => $milestone['title'],
                'starts_at' => $milestone['starts_at'] ?? null,
                'due_at' => $milestone['due_at'],
                'completion_percentage' => $milestone['completion_percentage'] ?? 0,
                'all_team_members' => $allTeamMembers,
                'notify_all_team_members' => $notifyAllTeamMembers,
            ], $assignees, $notifyRecipients);
            $this->dispatchMilestoneNotification($project, $created, $notifyRecipients);
            $createdMilestonesByIndex[$index] = $created;
        }

        foreach ($deliverablesInput as $deliverable) {
            $allTeamMembers = (bool) ($deliverable['all_team_members'] ?? false);
            $assignees = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $deliverable['assignee_uuids'] ?? []);
            $notifyAllTeamMembers = (bool) ($deliverable['notify_all_team_members'] ?? false);
            $notifyRecipients = $this->resolveNotifyRecipientIds($project, $notifyAllTeamMembers, $deliverable['notify_admin_uuids'] ?? []);
            $milestoneIndex = $deliverable['milestone_index'] ?? null;
            $created = $this->deliverableRepository->create($project, [
                'title' => $deliverable['title'],
                'starts_at' => $deliverable['starts_at'] ?? null,
                'due_at' => $deliverable['due_at'],
                'milestone_id' => $milestoneIndex !== null ? ($createdMilestonesByIndex[$milestoneIndex]->id ?? null) : null,
                'all_team_members' => $allTeamMembers,
                'notify_all_team_members' => $notifyAllTeamMembers,
            ], $assignees, $notifyRecipients);
            $this->dispatchDeliverableNotification($project, $created, $notifyRecipients);
        }

        foreach ($budgetLinesInput as $budgetLine) {
            $allTeamMembers = (bool) ($budgetLine['all_team_members'] ?? false);
            $recipients = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $budgetLine['recipient_admin_uuids'] ?? []);
            $created = $this->budgetLineRepository->create($project, [
                'title' => $budgetLine['title'],
                'reference_id' => $this->resolveBudgetLineReferenceId($budgetLine['reference_id'] ?? null),
                'amount_allocated' => $budgetLine['amount_allocated'],
                'starts_at' => $budgetLine['starts_at'] ?? null,
                'due_at' => $budgetLine['due_at'] ?? null,
                'all_team_members' => $allTeamMembers,
            ], $recipients);
            $this->dispatchBudgetLineNotification($project, $created, $recipients);
        }

        $project = $this->findForAdmin($project->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_CREATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid, 'title' => $project->title],
            $actor->displayName().' created a project: '.$project->title.'.',
            Project::class,
            $project->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $project;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(string $uuid, array $validated, Admin $actor, Request $request): Project
    {
        $project = $this->findForAdmin($uuid);

        $members = $validated['team_members'] ?? null;
        $managerUuids = $validated['manager_uuids'] ?? null;
        $objectivesInput = $validated['objectives'] ?? null;
        $milestonesInput = $validated['milestones'] ?? null;
        $deliverablesInput = $validated['deliverables'] ?? null;
        $budgetLinesInput = $validated['budget_lines'] ?? null;
        $donorUuids = $validated['donor_uuids'] ?? null;
        $campaignUuids = $validated['campaign_uuids'] ?? null;
        unset(
            $validated['team_members'], $validated['manager_uuids'],
            $validated['objectives'], $validated['milestones'], $validated['deliverables'], $validated['budget_lines'],
            $validated['donor_uuids'], $validated['campaign_uuids'],
        );

        if (isset($validated['budget_max'])) {
            $validated['approved_budget'] = $validated['budget_max'];
        }

        $project = $this->projectRepository->update($project, $validated);

        if ($members !== null || $managerUuids !== null) {
            $project = $this->projectRepository->syncTeamMembers($project, $this->mapTeamMembers($members ?? [], $managerUuids ?? []));
        }

        if ($donorUuids !== null) {
            $project = $this->projectRepository->syncFundingDonors($project, $this->resolveUserIds($donorUuids));
        }

        if ($campaignUuids !== null) {
            $project = $this->projectRepository->syncFundingCampaigns($project, $this->resolveCampaignIds($campaignUuids));
        }

        if ($objectivesInput !== null) {
            $this->syncObjectives($project, $objectivesInput);
        }

        if ($milestonesInput !== null || $deliverablesInput !== null) {
            $this->syncMilestonesAndDeliverables($project, $milestonesInput ?? [], $deliverablesInput ?? []);
        }

        if ($budgetLinesInput !== null) {
            $this->syncBudgetLines($project, $budgetLinesInput);
        }

        $project = $this->findForAdmin($project->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_UPDATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid],
            $actor->displayName().' updated a project: '.$project->title.'.',
            Project::class,
            $project->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $project;
    }

    /**
     * Full replace-set sync for the Edit Project wizard: items with a uuid are updated, items
     * without one are created, and any existing objective not present in $objectivesInput is
     * deleted, matching removing it from the list in the wizard before Save Changes.
     *
     * @param  list<array<string, mixed>>  $objectivesInput
     */
    private function syncObjectives(Project $project, array $objectivesInput): void
    {
        $keptUuids = [];

        foreach ($objectivesInput as $objectiveData) {
            $allTeamMembers = (bool) ($objectiveData['all_team_members'] ?? false);
            $assignees = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $objectiveData['assignee_uuids'] ?? []);
            $data = [
                'title' => $objectiveData['title'],
                'description' => $objectiveData['description'] ?? null,
                'all_team_members' => $allTeamMembers,
            ];

            if (isset($objectiveData['uuid'])) {
                $objective = $this->objectiveRepository->findByUuid($objectiveData['uuid']);
                if ($objective instanceof ProjectObjective) {
                    $this->objectiveRepository->update($objective, $data, $assignees);
                    $keptUuids[] = $objective->uuid;

                    continue;
                }
            }

            $created = $this->objectiveRepository->create($project, $data, $assignees);
            $keptUuids[] = $created->uuid;
        }

        foreach ($this->objectiveRepository->allForProject($project) as $existing) {
            if (! in_array($existing->uuid, $keptUuids, true)) {
                $this->objectiveRepository->delete($existing);
            }
        }
    }

    /**
     * Same replace-set sync as {@see self::syncObjectives()}, for a project's disbursement-phase
     * budget lines. Notifications are only dispatched for newly-created lines, not updates.
     *
     * @param  list<array<string, mixed>>  $budgetLinesInput
     */
    private function syncBudgetLines(Project $project, array $budgetLinesInput): void
    {
        $keptUuids = [];

        foreach ($budgetLinesInput as $budgetLineData) {
            $allTeamMembers = (bool) ($budgetLineData['all_team_members'] ?? false);
            $recipients = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $budgetLineData['recipient_admin_uuids'] ?? []);

            if (isset($budgetLineData['uuid'])) {
                $budgetLine = $this->budgetLineRepository->findByUuid($budgetLineData['uuid']);
                if ($budgetLine instanceof ProjectBudgetLine) {
                    $this->budgetLineRepository->update($budgetLine, [
                        'title' => $budgetLineData['title'],
                        'amount_allocated' => $budgetLineData['amount_allocated'],
                        'starts_at' => $budgetLineData['starts_at'] ?? null,
                        'due_at' => $budgetLineData['due_at'] ?? null,
                        'all_team_members' => $allTeamMembers,
                    ], $recipients);
                    $keptUuids[] = $budgetLine->uuid;

                    continue;
                }
            }

            $created = $this->budgetLineRepository->create($project, [
                'title' => $budgetLineData['title'],
                'reference_id' => $this->resolveBudgetLineReferenceId($budgetLineData['reference_id'] ?? null),
                'amount_allocated' => $budgetLineData['amount_allocated'],
                'starts_at' => $budgetLineData['starts_at'] ?? null,
                'due_at' => $budgetLineData['due_at'] ?? null,
                'all_team_members' => $allTeamMembers,
            ], $recipients);
            $this->dispatchBudgetLineNotification($project, $created, $recipients);
            $keptUuids[] = $created->uuid;
        }

        foreach ($this->budgetLineRepository->allForProject($project) as $existing) {
            if (in_array($existing->uuid, $keptUuids, true)) {
                continue;
            }

            // Deleting a budget line cascades to its expenditures at the DB level; refuse rather
            // than silently destroying recorded spend when a phase is dropped from the wizard.
            if ((float) $this->budgetLineRepository->sumUtilized($existing) > 0) {
                throw new ApiException(
                    'Cannot remove budget line "'.$existing->title.'": it already has expenditures recorded against it.',
                    422,
                );
            }

            $this->budgetLineRepository->delete($existing);
        }
    }

    /**
     * Same replace-set sync as {@see self::syncObjectives()}, for milestones and their
     * deliverables together, since a deliverable can link to a milestone created in the same
     * request (milestone_index) or an already-existing one (milestone_uuid).
     *
     * @param  list<array<string, mixed>>  $milestonesInput
     * @param  list<array<string, mixed>>  $deliverablesInput
     */
    private function syncMilestonesAndDeliverables(Project $project, array $milestonesInput, array $deliverablesInput): void
    {
        $keptMilestoneUuids = [];
        $milestonesByIndex = [];

        foreach ($milestonesInput as $index => $milestoneData) {
            $allTeamMembers = (bool) ($milestoneData['all_team_members'] ?? false);
            $assignees = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $milestoneData['assignee_uuids'] ?? []);
            $notifyAllTeamMembers = (bool) ($milestoneData['notify_all_team_members'] ?? false);
            $notifyRecipients = $this->resolveNotifyRecipientIds($project, $notifyAllTeamMembers, $milestoneData['notify_admin_uuids'] ?? []);
            $data = [
                'title' => $milestoneData['title'],
                'starts_at' => $milestoneData['starts_at'] ?? null,
                'due_at' => $milestoneData['due_at'],
                'all_team_members' => $allTeamMembers,
                'notify_all_team_members' => $notifyAllTeamMembers,
            ];
            if (isset($milestoneData['completion_percentage'])) {
                $data['completion_percentage'] = $milestoneData['completion_percentage'];
            }

            if (isset($milestoneData['uuid'])) {
                $milestone = $this->milestoneRepository->findByUuid($milestoneData['uuid']);
                if ($milestone instanceof ProjectMilestone) {
                    $milestone = $this->milestoneRepository->update($milestone, $data, $assignees, $notifyRecipients);
                    $milestonesByIndex[$index] = $milestone;
                    $keptMilestoneUuids[] = $milestone->uuid;

                    continue;
                }
            }

            $data['completion_percentage'] ??= 0;
            $milestone = $this->milestoneRepository->create($project, $data, $assignees, $notifyRecipients);
            $this->dispatchMilestoneNotification($project, $milestone, $notifyRecipients);
            $milestonesByIndex[$index] = $milestone;
            $keptMilestoneUuids[] = $milestone->uuid;
        }

        foreach ($this->milestoneRepository->allForProject($project) as $existing) {
            if (! in_array($existing->uuid, $keptMilestoneUuids, true)) {
                $this->milestoneRepository->delete($existing);
            }
        }

        $keptDeliverableUuids = [];

        foreach ($deliverablesInput as $deliverableData) {
            $allTeamMembers = (bool) ($deliverableData['all_team_members'] ?? false);
            $assignees = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $deliverableData['assignee_uuids'] ?? []);
            $notifyAllTeamMembers = (bool) ($deliverableData['notify_all_team_members'] ?? false);
            $notifyRecipients = $this->resolveNotifyRecipientIds($project, $notifyAllTeamMembers, $deliverableData['notify_admin_uuids'] ?? []);
            $milestoneId = null;
            if (isset($deliverableData['milestone_index'], $milestonesByIndex[$deliverableData['milestone_index']])) {
                $milestoneId = $milestonesByIndex[$deliverableData['milestone_index']]->id;
            } elseif (isset($deliverableData['milestone_uuid'])) {
                $milestoneId = $this->milestoneRepository->findByUuid($deliverableData['milestone_uuid'])?->id;
            }

            $data = [
                'title' => $deliverableData['title'],
                'starts_at' => $deliverableData['starts_at'] ?? null,
                'due_at' => $deliverableData['due_at'],
                'milestone_id' => $milestoneId,
                'all_team_members' => $allTeamMembers,
                'notify_all_team_members' => $notifyAllTeamMembers,
            ];

            if (isset($deliverableData['uuid'])) {
                $deliverable = $this->deliverableRepository->findByUuid($deliverableData['uuid']);
                if ($deliverable instanceof ProjectDeliverable) {
                    $deliverable = $this->deliverableRepository->update($deliverable, $data, $assignees, $notifyRecipients);
                    $keptDeliverableUuids[] = $deliverable->uuid;

                    continue;
                }
            }

            $deliverable = $this->deliverableRepository->create($project, $data, $assignees, $notifyRecipients);
            $this->dispatchDeliverableNotification($project, $deliverable, $notifyRecipients);
            $keptDeliverableUuids[] = $deliverable->uuid;
        }

        foreach ($this->deliverableRepository->allForProject($project) as $existing) {
            if (! in_array($existing->uuid, $keptDeliverableUuids, true)) {
                $this->deliverableRepository->delete($existing);
            }
        }
    }

    public function activate(string $uuid, Admin $actor, Request $request): Project
    {
        $project = $this->findForAdmin($uuid);

        if (! in_array($project->status, [ProjectStatusEnum::DRAFT->value, ProjectStatusEnum::ON_HOLD->value], true)) {
            throw new ApiException('Only a draft or on-hold project can be activated.', 422);
        }

        $wasOnHold = $project->status === ProjectStatusEnum::ON_HOLD->value;
        $project = $this->projectRepository->update($project, [
            'status' => ProjectStatusEnum::ACTIVE->value,
            'on_hold_at' => null,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            $wasOnHold ? AuditActionEnum::PROJECT_RESUMED : AuditActionEnum::PROJECT_UPDATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid],
            $actor->displayName().' '.($wasOnHold ? 'resumed' : 'activated').' a project: '.$project->title.'.',
            Project::class,
            $project->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $project;
    }

    public function putOnHold(string $uuid, Admin $actor, Request $request): Project
    {
        $project = $this->findForAdmin($uuid);

        if ($project->status !== ProjectStatusEnum::ACTIVE->value) {
            throw new ApiException('Only an active project can be put on hold.', 422);
        }

        $project = $this->projectRepository->update($project, [
            'status' => ProjectStatusEnum::ON_HOLD->value,
            'on_hold_at' => now(),
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_PUT_ON_HOLD,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid],
            $actor->displayName().' put a project on hold: '.$project->title.'.',
            Project::class,
            $project->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $project;
    }

    public function archive(string $uuid, Admin $actor, Request $request): Project
    {
        $project = $this->findForAdmin($uuid);

        if ($project->status === ProjectStatusEnum::ARCHIVED->value) {
            throw new ApiException('Project is already archived.', 422);
        }

        $project = $this->projectRepository->update($project, [
            'status' => ProjectStatusEnum::ARCHIVED->value,
            'archived_at' => now(),
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_ARCHIVED,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid],
            $actor->displayName().' archived a project: '.$project->title.'.',
            Project::class,
            $project->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $project;
    }

    // Objectives

    /**
     * @return \Illuminate\Support\Collection<int, ProjectObjective>
     */
    /**
     * @param  array<string, mixed>  $filters
     */
    public function objectives(string $projectUuid, array $filters): LengthAwarePaginator
    {
        $project = $this->findForAdmin($projectUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->objectiveRepository->paginateForProject($project, $filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function addObjective(string $projectUuid, array $validated, Admin $actor, Request $request): ProjectObjective
    {
        $project = $this->findForAdmin($projectUuid);
        $allTeamMembers = (bool) ($validated['all_team_members'] ?? false);
        $assignees = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $validated['assignee_uuids'] ?? []);
        $validated['all_team_members'] = $allTeamMembers;
        unset($validated['assignee_uuids']);

        $objective = $this->objectiveRepository->create($project, $validated, $assignees);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_OBJECTIVE_CREATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid, 'objective_uuid' => $objective->uuid],
            $actor->displayName().' added an objective to project: '.$project->title.'.',
            ProjectObjective::class,
            $objective->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $objective;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateObjective(string $projectUuid, string $objectiveUuid, array $validated, Admin $actor, Request $request): ProjectObjective
    {
        $project = $this->findForAdmin($projectUuid);
        $objective = $this->objectiveRepository->findByUuid($objectiveUuid);
        if (! $objective instanceof ProjectObjective) {
            throw new ApiException('Project objective not found.', 404);
        }
        $this->assertOwnedByProject($objective->project_id, $project->id, 'Project objective not found.');

        $assignees = null;
        if (array_key_exists('all_team_members', $validated) || array_key_exists('assignee_uuids', $validated)) {
            $allTeamMembers = (bool) ($validated['all_team_members'] ?? $objective->all_team_members);
            $assignees = $this->resolveNotifyRecipientIds($objective->project, $allTeamMembers, $validated['assignee_uuids'] ?? []);
            $validated['all_team_members'] = $allTeamMembers;
        }
        unset($validated['assignee_uuids']);

        $objective = $this->objectiveRepository->update($objective, $validated, $assignees);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_OBJECTIVE_UPDATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $objective->project->uuid, 'objective_uuid' => $objective->uuid],
            $actor->displayName().' updated a project objective: '.$objective->title.'.',
            ProjectObjective::class,
            $objective->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $objective;
    }

    // Milestones

    /**
     * @param  array<string, mixed>  $filters
     */
    public function milestones(string $projectUuid, array $filters): LengthAwarePaginator
    {
        $project = $this->findForAdmin($projectUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->milestoneRepository->paginateForProject($project, $filters, $perPage);
    }

    public function findMilestone(string $uuid): ProjectMilestone
    {
        $milestone = $this->milestoneRepository->findByUuid($uuid);
        if (! $milestone instanceof ProjectMilestone) {
            throw new ApiException('Project milestone not found.', 404);
        }

        return $milestone;
    }

    public function showMilestone(string $projectUuid, string $milestoneUuid): ProjectMilestone
    {
        $project = $this->findForAdmin($projectUuid);
        $milestone = $this->findMilestone($milestoneUuid);
        $this->assertOwnedByProject($milestone->project_id, $project->id, 'Project milestone not found.');

        return $milestone;
    }

    /**
     * Guards against linking a record to a milestone/deliverable/budget line that belongs to a
     * different project (the uuid-only `exists:` validation rule can't catch this by itself).
     */
    private function assertBelongsToProject(int $entityProjectId, int $projectId, string $label): void
    {
        if ($entityProjectId !== $projectId) {
            throw new ApiException("The selected {$label} does not belong to this project.", 422);
        }
    }

    /**
     * Guards a nested route (/projects/{uuid}/{childType}/{childUuid}) against a mismatched pair:
     * a real child uuid combined with a project uuid it doesn't actually belong to. Responds 404,
     * not 422/403, so it reads identically to "doesn't exist" rather than confirming the child
     * exists under some other project.
     */
    private function assertOwnedByProject(int $entityProjectId, int $projectId, string $notFoundMessage): void
    {
        if ($entityProjectId !== $projectId) {
            throw new ApiException($notFoundMessage, 404);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function addMilestone(string $projectUuid, array $validated, Admin $actor, Request $request): ProjectMilestone
    {
        $project = $this->findForAdmin($projectUuid);
        $allTeamMembers = (bool) ($validated['all_team_members'] ?? false);
        $assignees = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $validated['assignee_uuids'] ?? []);
        $notifyAllTeamMembers = (bool) ($validated['notify_all_team_members'] ?? false);
        $notifyRecipients = $this->resolveNotifyRecipientIds($project, $notifyAllTeamMembers, $validated['notify_admin_uuids'] ?? []);
        unset($validated['assignee_uuids'], $validated['notify_admin_uuids']);
        $validated['all_team_members'] = $allTeamMembers;
        $validated['notify_all_team_members'] = $notifyAllTeamMembers;
        $validated['completion_percentage'] ??= 0;

        $milestone = $this->milestoneRepository->create($project, $validated, $assignees, $notifyRecipients);
        $this->dispatchMilestoneNotification($project, $milestone, $notifyRecipients);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_MILESTONE_CREATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid, 'milestone_uuid' => $milestone->uuid],
            $actor->displayName().' added a milestone to project: '.$project->title.'.',
            ProjectMilestone::class,
            $milestone->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $milestone;
    }

    /**
     * @param  list<int>  $recipientAdminIds
     */
    private function dispatchMilestoneNotification(Project $project, ProjectMilestone $milestone, array $recipientAdminIds): void
    {
        if ($recipientAdminIds === []) {
            return;
        }

        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::project_management->value,
            event: 'project_milestone_added',
            title: $milestone->title,
            message: 'A new milestone was added: '.$milestone->title.'.',
            meta: ['project_uuid' => $project->uuid, 'milestone_uuid' => $milestone->uuid],
        );

        $this->notificationDispatchService->notifyAdminsByUuids(
            Admin::query()->whereIn('id', $recipientAdminIds)->pluck('uuid')->all(),
            $notification,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateMilestone(string $projectUuid, string $milestoneUuid, array $validated, Admin $actor, Request $request): ProjectMilestone
    {
        $project = $this->findForAdmin($projectUuid);
        $milestone = $this->findMilestone($milestoneUuid);
        $this->assertOwnedByProject($milestone->project_id, $project->id, 'Project milestone not found.');

        $assignees = null;
        if (array_key_exists('all_team_members', $validated) || array_key_exists('assignee_uuids', $validated)) {
            $allTeamMembers = (bool) ($validated['all_team_members'] ?? $milestone->all_team_members);
            $assignees = $this->resolveNotifyRecipientIds($milestone->project, $allTeamMembers, $validated['assignee_uuids'] ?? []);
            $validated['all_team_members'] = $allTeamMembers;
        }
        unset($validated['assignee_uuids']);

        $notifyRecipients = null;
        if (array_key_exists('notify_all_team_members', $validated) || array_key_exists('notify_admin_uuids', $validated)) {
            $notifyAllTeamMembers = (bool) ($validated['notify_all_team_members'] ?? $milestone->notify_all_team_members);
            $notifyRecipients = $this->resolveNotifyRecipientIds($milestone->project, $notifyAllTeamMembers, $validated['notify_admin_uuids'] ?? []);
            $validated['notify_all_team_members'] = $notifyAllTeamMembers;
        }
        unset($validated['notify_admin_uuids']);

        $milestone = $this->milestoneRepository->update($milestone, $validated, $assignees, $notifyRecipients);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_MILESTONE_UPDATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $milestone->project->uuid, 'milestone_uuid' => $milestone->uuid],
            $actor->displayName().' updated a project milestone: '.$milestone->title.'.',
            ProjectMilestone::class,
            $milestone->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $milestone;
    }

    public function completeMilestone(string $projectUuid, string $milestoneUuid, Admin $actor, Request $request): ProjectMilestone
    {
        $project = $this->findForAdmin($projectUuid);
        $milestone = $this->findMilestone($milestoneUuid);
        $this->assertOwnedByProject($milestone->project_id, $project->id, 'Project milestone not found.');
        $milestone = $this->milestoneRepository->markComplete($milestone);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_MILESTONE_COMPLETED,
            $request,
            $actor->uuid,
            ['project_uuid' => $milestone->project->uuid, 'milestone_uuid' => $milestone->uuid],
            $actor->displayName().' marked a project milestone as complete: '.$milestone->title.'.',
            ProjectMilestone::class,
            $milestone->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $milestone;
    }

    public function deleteMilestone(string $projectUuid, string $milestoneUuid, Admin $actor, Request $request): void
    {
        $project = $this->findForAdmin($projectUuid);
        $milestone = $this->findMilestone($milestoneUuid);
        $this->assertOwnedByProject($milestone->project_id, $project->id, 'Project milestone not found.');
        $this->milestoneRepository->delete($milestone);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_MILESTONE_DELETED,
            $request,
            $actor->uuid,
            ['project_uuid' => $milestone->project->uuid, 'milestone_uuid' => $milestone->uuid],
            $actor->displayName().' deleted a project milestone: '.$milestone->title.'.',
            ProjectMilestone::class,
            $milestone->uuid,
            ModuleEnums::project_management,
            200,
        );
    }

    // Deliverables

    /**
     * @param  array<string, mixed>  $filters
     */
    public function deliverables(string $projectUuid, array $filters): LengthAwarePaginator
    {
        $project = $this->findForAdmin($projectUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->deliverableRepository->paginateForProject($project, $filters, $perPage);
    }

    public function findDeliverable(string $uuid): ProjectDeliverable
    {
        $deliverable = $this->deliverableRepository->findByUuid($uuid);
        if (! $deliverable instanceof ProjectDeliverable) {
            throw new ApiException('Project deliverable not found.', 404);
        }

        return $deliverable;
    }

    public function showDeliverable(string $projectUuid, string $deliverableUuid): ProjectDeliverable
    {
        $project = $this->findForAdmin($projectUuid);
        $deliverable = $this->findDeliverable($deliverableUuid);
        $this->assertOwnedByProject($deliverable->project_id, $project->id, 'Project deliverable not found.');

        return $deliverable;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function addDeliverable(string $projectUuid, array $validated, Admin $actor, Request $request): ProjectDeliverable
    {
        $project = $this->findForAdmin($projectUuid);
        $allTeamMembers = (bool) ($validated['all_team_members'] ?? false);
        $assignees = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $validated['assignee_uuids'] ?? []);
        $notifyAllTeamMembers = (bool) ($validated['notify_all_team_members'] ?? false);
        $notifyRecipients = $this->resolveNotifyRecipientIds($project, $notifyAllTeamMembers, $validated['notify_admin_uuids'] ?? []);
        unset($validated['assignee_uuids'], $validated['notify_admin_uuids']);
        $validated['all_team_members'] = $allTeamMembers;
        $validated['notify_all_team_members'] = $notifyAllTeamMembers;

        if (array_key_exists('milestone_uuid', $validated)) {
            if ($validated['milestone_uuid'] === null) {
                $validated['milestone_id'] = null;
            } else {
                $milestone = $this->findMilestone($validated['milestone_uuid']);
                $this->assertBelongsToProject($milestone->project_id, $project->id, 'milestone');
                $validated['milestone_id'] = $milestone->id;
            }
            unset($validated['milestone_uuid']);
        }

        $deliverable = $this->deliverableRepository->create($project, $validated, $assignees, $notifyRecipients);
        $this->dispatchDeliverableNotification($project, $deliverable, $notifyRecipients);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_DELIVERABLE_CREATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid, 'deliverable_uuid' => $deliverable->uuid],
            $actor->displayName().' added a deliverable to project: '.$project->title.'.',
            ProjectDeliverable::class,
            $deliverable->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $deliverable;
    }

    /**
     * @param  list<int>  $recipientAdminIds
     */
    private function dispatchDeliverableNotification(Project $project, ProjectDeliverable $deliverable, array $recipientAdminIds): void
    {
        if ($recipientAdminIds === []) {
            return;
        }

        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::project_management->value,
            event: 'project_deliverable_added',
            title: $deliverable->title,
            message: 'A new deliverable was added: '.$deliverable->title.'.',
            meta: ['project_uuid' => $project->uuid, 'deliverable_uuid' => $deliverable->uuid],
        );

        $this->notificationDispatchService->notifyAdminsByUuids(
            Admin::query()->whereIn('id', $recipientAdminIds)->pluck('uuid')->all(),
            $notification,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateDeliverable(string $projectUuid, string $deliverableUuid, array $validated, Admin $actor, Request $request): ProjectDeliverable
    {
        $project = $this->findForAdmin($projectUuid);
        $deliverable = $this->findDeliverable($deliverableUuid);
        $this->assertOwnedByProject($deliverable->project_id, $project->id, 'Project deliverable not found.');

        $assignees = null;
        if (array_key_exists('all_team_members', $validated) || array_key_exists('assignee_uuids', $validated)) {
            $allTeamMembers = (bool) ($validated['all_team_members'] ?? $deliverable->all_team_members);
            $assignees = $this->resolveNotifyRecipientIds($deliverable->project, $allTeamMembers, $validated['assignee_uuids'] ?? []);
            $validated['all_team_members'] = $allTeamMembers;
        }
        unset($validated['assignee_uuids']);

        $notifyRecipients = null;
        if (array_key_exists('notify_all_team_members', $validated) || array_key_exists('notify_admin_uuids', $validated)) {
            $notifyAllTeamMembers = (bool) ($validated['notify_all_team_members'] ?? $deliverable->notify_all_team_members);
            $notifyRecipients = $this->resolveNotifyRecipientIds($deliverable->project, $notifyAllTeamMembers, $validated['notify_admin_uuids'] ?? []);
            $validated['notify_all_team_members'] = $notifyAllTeamMembers;
        }
        unset($validated['notify_admin_uuids']);

        if (array_key_exists('milestone_uuid', $validated)) {
            if ($validated['milestone_uuid'] === null) {
                $validated['milestone_id'] = null;
            } else {
                $milestone = $this->findMilestone($validated['milestone_uuid']);
                $this->assertBelongsToProject($milestone->project_id, $deliverable->project_id, 'milestone');
                $validated['milestone_id'] = $milestone->id;
            }
            unset($validated['milestone_uuid']);
        }

        $deliverable = $this->deliverableRepository->update($deliverable, $validated, $assignees, $notifyRecipients);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_DELIVERABLE_UPDATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $deliverable->project->uuid, 'deliverable_uuid' => $deliverable->uuid],
            $actor->displayName().' updated a project deliverable: '.$deliverable->title.'.',
            ProjectDeliverable::class,
            $deliverable->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $deliverable;
    }

    public function completeDeliverable(string $projectUuid, string $deliverableUuid, Admin $actor, Request $request): ProjectDeliverable
    {
        $project = $this->findForAdmin($projectUuid);
        $deliverable = $this->findDeliverable($deliverableUuid);
        $this->assertOwnedByProject($deliverable->project_id, $project->id, 'Project deliverable not found.');
        $deliverable = $this->deliverableRepository->markComplete($deliverable);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_DELIVERABLE_COMPLETED,
            $request,
            $actor->uuid,
            ['project_uuid' => $deliverable->project->uuid, 'deliverable_uuid' => $deliverable->uuid],
            $actor->displayName().' marked a project deliverable as complete: '.$deliverable->title.'.',
            ProjectDeliverable::class,
            $deliverable->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $deliverable;
    }

    public function deleteDeliverable(string $projectUuid, string $deliverableUuid, Admin $actor, Request $request): void
    {
        $project = $this->findForAdmin($projectUuid);
        $deliverable = $this->findDeliverable($deliverableUuid);
        $this->assertOwnedByProject($deliverable->project_id, $project->id, 'Project deliverable not found.');
        $this->deliverableRepository->delete($deliverable);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_DELIVERABLE_DELETED,
            $request,
            $actor->uuid,
            ['project_uuid' => $deliverable->project->uuid, 'deliverable_uuid' => $deliverable->uuid],
            $actor->displayName().' deleted a project deliverable: '.$deliverable->title.'.',
            ProjectDeliverable::class,
            $deliverable->uuid,
            ModuleEnums::project_management,
            200,
        );
    }

    // Budget lines

    /**
     * @return \Illuminate\Support\Collection<int, ProjectBudgetLine>
     */
    /**
     * @param  array<string, mixed>  $filters
     */
    public function budgetLines(string $projectUuid, array $filters): LengthAwarePaginator
    {
        $project = $this->findForAdmin($projectUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $paginator = $this->budgetLineRepository->paginateForProject($project, $filters, $perPage);

        $paginator->setCollection($paginator->getCollection()->map(
            fn (ProjectBudgetLine $budgetLine): ProjectBudgetLine => $this->attachBudgetLineSummary($budgetLine)
        ));

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, ProjectBudgetLine>, 1: bool, 2: string}
     */
    public function exportBudgetLines(string $projectUuid, array $filters): array
    {
        $project = $this->findForAdmin($projectUuid);
        [$rows, $truncated] = $this->budgetLineRepository->exportForProject($project, $filters, self::MAX_EXPORT_ROWS);

        $rows = $rows->map(fn (ProjectBudgetLine $budgetLine): ProjectBudgetLine => $this->attachBudgetLineSummary($budgetLine));

        return [$rows, $truncated, $project->currency];
    }

    private function attachBudgetLineSummary(ProjectBudgetLine $budgetLine): ProjectBudgetLine
    {
        $summary = $this->budgetLineSummary($budgetLine);
        $budgetLine->setAttribute('amount_utilized', $summary['amount_utilized']);
        $budgetLine->setAttribute('remaining_amount', $summary['remaining_amount']);
        $budgetLine->setAttribute('percent_remaining', $summary['percent_remaining']);

        return $budgetLine;
    }

    public function findBudgetLine(string $uuid): ProjectBudgetLine
    {
        $budgetLine = $this->budgetLineRepository->findByUuid($uuid);
        if (! $budgetLine instanceof ProjectBudgetLine) {
            throw new ApiException('Project budget line not found.', 404);
        }

        return $budgetLine;
    }

    public function showBudgetLine(string $projectUuid, string $budgetLineUuid): ProjectBudgetLine
    {
        $project = $this->findForAdmin($projectUuid);
        $budgetLine = $this->findBudgetLine($budgetLineUuid);
        $this->assertOwnedByProject($budgetLine->project_id, $project->id, 'Project budget line not found.');

        return $this->attachBudgetLineSummary($budgetLine);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function addBudgetLine(string $projectUuid, array $validated, Admin $actor, Request $request): ProjectBudgetLine
    {
        $project = $this->findForAdmin($projectUuid);
        $allTeamMembers = (bool) ($validated['all_team_members'] ?? false);
        $recipients = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $validated['recipient_admin_uuids'] ?? []);
        $validated['all_team_members'] = $allTeamMembers;
        $validated['reference_id'] = $this->resolveBudgetLineReferenceId($validated['reference_id'] ?? null);
        unset($validated['recipient_admin_uuids']);

        $budgetLine = $this->budgetLineRepository->create($project, $validated, $recipients);
        $this->dispatchBudgetLineNotification($project, $budgetLine, $recipients);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_BUDGET_LINE_CREATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid, 'budget_line_uuid' => $budgetLine->uuid],
            $actor->displayName().' added a budget line to project: '.$project->title.'.',
            ProjectBudgetLine::class,
            $budgetLine->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $budgetLine;
    }

    /**
     * @param  list<int>  $recipientAdminIds
     */
    private function dispatchBudgetLineNotification(Project $project, ProjectBudgetLine $budgetLine, array $recipientAdminIds): void
    {
        if ($recipientAdminIds === []) {
            return;
        }

        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::project_management->value,
            event: 'project_budget_line_added',
            title: $budgetLine->title,
            message: 'A new disbursement phase was added: '.$budgetLine->title.'.',
            meta: ['project_uuid' => $project->uuid, 'budget_line_uuid' => $budgetLine->uuid],
        );

        $this->notificationDispatchService->notifyAdminsByUuids(
            Admin::query()->whereIn('id', $recipientAdminIds)->pluck('uuid')->all(),
            $notification,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateBudgetLine(string $projectUuid, string $budgetLineUuid, array $validated, Admin $actor, Request $request): ProjectBudgetLine
    {
        $project = $this->findForAdmin($projectUuid);
        $budgetLine = $this->findBudgetLine($budgetLineUuid);
        $this->assertOwnedByProject($budgetLine->project_id, $project->id, 'Project budget line not found.');

        $recipients = null;
        if (array_key_exists('all_team_members', $validated) || array_key_exists('recipient_admin_uuids', $validated)) {
            $allTeamMembers = (bool) ($validated['all_team_members'] ?? $budgetLine->all_team_members);
            $recipients = $this->resolveNotifyRecipientIds($budgetLine->project, $allTeamMembers, $validated['recipient_admin_uuids'] ?? []);
            $validated['all_team_members'] = $allTeamMembers;
        }
        unset($validated['recipient_admin_uuids']);

        $budgetLine = $this->budgetLineRepository->update($budgetLine, $validated, $recipients);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_BUDGET_LINE_UPDATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $budgetLine->project->uuid, 'budget_line_uuid' => $budgetLine->uuid],
            $actor->displayName().' updated a project budget line: '.$budgetLine->title.'.',
            ProjectBudgetLine::class,
            $budgetLine->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $budgetLine;
    }

    /**
     * @return array{title: string, amount_allocated: string, amount_utilized: string, remaining_amount: string, percent_remaining: float}
     */
    public function budgetLineSummary(ProjectBudgetLine $budgetLine): array
    {
        $allocated = (string) $budgetLine->amount_allocated;
        $utilized = $this->budgetLineRepository->sumUtilized($budgetLine);
        $remaining = bcsub($allocated, $utilized, 2);
        $percentRemaining = (float) $allocated > 0 ? round(((float) $remaining / (float) $allocated) * 100, 2) : 0.0;

        return [
            'title' => $budgetLine->title,
            'amount_allocated' => $allocated,
            'amount_utilized' => $utilized,
            'remaining_amount' => $remaining,
            'percent_remaining' => $percentRemaining,
        ];
    }

    /**
     * Feeds a single project's "Budget & Funding" overview cards (Approved Budget, Funding
     * Received, Total Utilized, Remaining Budget), as opposed to {@see self::budgetOverview()}
     * which aggregates across every project.
     *
     * @return array{approved_budget: string, funding_received: string, total_utilized: string, remaining_budget: string}
     */
    public function projectBudgetOverview(Project $project): array
    {
        $approvedBudget = (string) $project->approved_budget;
        $totalUtilized = $this->expenditureRepository->sumForProject($project);

        return [
            'approved_budget' => $approvedBudget,
            'funding_received' => (string) $project->funding_received,
            'total_utilized' => $totalUtilized,
            'remaining_budget' => bcsub($approvedBudget, $totalUtilized, 2),
        ];
    }

    /**
     * Public-facing version of {@see self::projectBudgetOverview()} for the "Budget & Funding"
     * tab's overview cards: resolves the project, adds formatted amounts and the percent/over-budget
     * figures that are honestly derivable from current state. Does NOT include a "vs last 30 days"
     * style trend, since that needs a historical snapshot this schema doesn't keep.
     *
     * @return array{
     *     approved_budget: string, approved_budget_formatted: string,
     *     funding_received: string, funding_received_formatted: string,
     *     total_utilized: string, total_utilized_formatted: string,
     *     remaining_budget: string, remaining_budget_formatted: string,
     *     percent_utilized: float, percent_remaining: float, is_over_budget: bool,
     * }
     */
    public function budgetOverviewForProject(string $projectUuid): array
    {
        $project = $this->findForAdmin($projectUuid);
        $raw = $this->projectBudgetOverview($project);
        $currency = $project->currency;

        $approvedBudget = (float) $raw['approved_budget'];
        $percentUtilized = $approvedBudget > 0 ? round(((float) $raw['total_utilized'] / $approvedBudget) * 100, 2) : 0.0;
        $percentRemaining = $approvedBudget > 0 ? round(((float) $raw['remaining_budget'] / $approvedBudget) * 100, 2) : 0.0;

        return [
            'approved_budget' => $raw['approved_budget'],
            'approved_budget_formatted' => Money::format($raw['approved_budget'], $currency),
            'funding_received' => $raw['funding_received'],
            'funding_received_formatted' => Money::format($raw['funding_received'], $currency),
            'total_utilized' => $raw['total_utilized'],
            'total_utilized_formatted' => Money::format($raw['total_utilized'], $currency),
            'remaining_budget' => $raw['remaining_budget'],
            'remaining_budget_formatted' => Money::format($raw['remaining_budget'], $currency),
            'percent_utilized' => $percentUtilized,
            'percent_remaining' => $percentRemaining,
            'is_over_budget' => bccomp($raw['total_utilized'], $raw['approved_budget'], 2) > 0,
        ];
    }

    // Expenditures

    /**
     * @param  array<string, mixed>  $filters
     */
    public function expenditures(string $projectUuid, array $filters): LengthAwarePaginator
    {
        $project = $this->findForAdmin($projectUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->expenditureRepository->paginateForProject($project, $filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, ProjectExpenditure>, 1: bool, 2: string}
     */
    public function exportExpenditures(string $projectUuid, array $filters): array
    {
        $project = $this->findForAdmin($projectUuid);
        [$rows, $truncated] = $this->expenditureRepository->exportForProject($project, $filters, self::MAX_EXPORT_ROWS);

        return [$rows, $truncated, $project->currency];
    }

    public function findExpenditure(string $uuid): ProjectExpenditure
    {
        $expenditure = $this->expenditureRepository->findByUuid($uuid);
        if (! $expenditure instanceof ProjectExpenditure) {
            throw new ApiException('Project expenditure not found.', 404);
        }

        return $expenditure;
    }

    public function showExpenditure(string $projectUuid, string $expenditureUuid): ProjectExpenditure
    {
        $project = $this->findForAdmin($projectUuid);
        $expenditure = $this->findExpenditure($expenditureUuid);
        $this->assertOwnedByProject($expenditure->project_id, $project->id, 'Project expenditure not found.');

        return $expenditure;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function recordExpenditure(string $projectUuid, array $validated, Admin $actor, Request $request): ProjectExpenditure
    {
        $project = $this->findForAdmin($projectUuid);
        $budgetLine = $this->findBudgetLine($validated['budget_line_uuid']);
        $this->assertBelongsToProject($budgetLine->project_id, $project->id, 'budget line');
        unset($validated['budget_line_uuid']);

        if (array_key_exists('evidence_url', $validated)) {
            $validated['evidence_url'] = FileUploadHelper::smartSingleFileUpload($validated['evidence_url'], 'projects/expenditure-evidence');
        }

        $validated['recorded_by'] = $actor->uuid;

        $expenditure = $this->expenditureRepository->create($project, $budgetLine, $validated);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_EXPENDITURE_RECORDED,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid, 'expenditure_uuid' => $expenditure->uuid, 'amount' => $expenditure->amount],
            $actor->displayName().' recorded an expenditure on project: '.$project->title.'.',
            ProjectExpenditure::class,
            $expenditure->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $expenditure;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateExpenditure(string $projectUuid, string $expenditureUuid, array $validated, Admin $actor, Request $request): ProjectExpenditure
    {
        $project = $this->findForAdmin($projectUuid);
        $expenditure = $this->findExpenditure($expenditureUuid);
        $this->assertOwnedByProject($expenditure->project_id, $project->id, 'Project expenditure not found.');

        if (isset($validated['budget_line_uuid'])) {
            $budgetLine = $this->findBudgetLine($validated['budget_line_uuid']);
            $this->assertBelongsToProject($budgetLine->project_id, $project->id, 'budget line');
            $validated['budget_line_id'] = $budgetLine->id;
            unset($validated['budget_line_uuid']);
        }

        if (array_key_exists('evidence_url', $validated)) {
            if ($validated['evidence_url'] === null || $validated['evidence_url'] === '') {
                // Laravel's ConvertEmptyStringsToNull middleware turns a present-but-blank
                // multipart field into null before it ever reaches here, making that
                // indistinguishable from an intentional "clear this" request. Since nothing
                // needs to explicitly clear evidence_url, treat either as "left untouched"
                // rather than risk silently wiping real evidence on an unrelated field update.
                unset($validated['evidence_url']);
            } else {
                $validated['evidence_url'] = FileUploadHelper::smartSingleFileUpload($validated['evidence_url'], 'projects/expenditure-evidence');
            }
        }

        $expenditure = $this->expenditureRepository->update($expenditure, $validated);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_EXPENDITURE_UPDATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $expenditure->project->uuid, 'expenditure_uuid' => $expenditure->uuid],
            $actor->displayName().' updated a project expenditure: '.$expenditure->title.'.',
            ProjectExpenditure::class,
            $expenditure->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $expenditure;
    }

    public function deleteExpenditure(string $projectUuid, string $expenditureUuid, Admin $actor, Request $request): void
    {
        $project = $this->findForAdmin($projectUuid);
        $expenditure = $this->findExpenditure($expenditureUuid);
        $this->assertOwnedByProject($expenditure->project_id, $project->id, 'Project expenditure not found.');
        $this->expenditureRepository->delete($expenditure);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_EXPENDITURE_UPDATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $expenditure->project->uuid, 'expenditure_uuid' => $expenditure->uuid],
            $actor->displayName().' removed a project expenditure: '.$expenditure->title.'.',
            ProjectExpenditure::class,
            $expenditure->uuid,
            ModuleEnums::project_management,
            200,
        );
    }

    private function resolveBudgetLineReferenceId(?string $provided): string
    {
        return $this->generateUniqueReferenceId($provided, fn (string $id) => $this->budgetLineRepository->referenceIdExists($id));
    }

    /**
     * @param  \Closure(string): bool  $exists
     */
    private function generateUniqueReferenceId(?string $provided, \Closure $exists): string
    {
        if ($provided !== null && $provided !== '' && ! $exists($provided)) {
            return $provided;
        }

        do {
            $candidate = strtoupper(Str::random(10));
        } while ($exists($candidate));

        return $candidate;
    }

    // Impact reports

    /**
     * @param  array<string, mixed>  $filters
     */
    public function impactReports(string $projectUuid, array $filters): LengthAwarePaginator
    {
        $project = $this->findForAdmin($projectUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->impactReportRepository->paginateForProject($project, $filters, $perPage);
    }

    public function findImpactReport(string $uuid): ProjectImpactReport
    {
        $report = $this->impactReportRepository->findByUuid($uuid);
        if (! $report instanceof ProjectImpactReport) {
            throw new ApiException('Project impact report not found.', 404);
        }

        return $report;
    }

    public function showImpactReport(string $projectUuid, string $reportUuid): ProjectImpactReport
    {
        $project = $this->findForAdmin($projectUuid);
        $report = $this->findImpactReport($reportUuid);
        $this->assertOwnedByProject($report->project_id, $project->id, 'Project impact report not found.');

        return $report;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function addImpactReport(string $projectUuid, array $validated, Admin $actor, Request $request): ProjectImpactReport
    {
        $project = $this->findForAdmin($projectUuid);

        if (isset($validated['deliverable_uuid'])) {
            $deliverable = $this->findDeliverable($validated['deliverable_uuid']);
            $this->assertBelongsToProject($deliverable->project_id, $project->id, 'deliverable');
            $validated['deliverable_id'] = $deliverable->id;
            unset($validated['deliverable_uuid']);
        }
        if (isset($validated['budget_line_uuid'])) {
            $budgetLine = $this->findBudgetLine($validated['budget_line_uuid']);
            $this->assertBelongsToProject($budgetLine->project_id, $project->id, 'budget line');
            $validated['budget_line_id'] = $budgetLine->id;
            unset($validated['budget_line_uuid']);
        }

        if (array_key_exists('evidence_urls', $validated)) {
            $validated['evidence_urls'] = FileUploadHelper::smartMultipleFileUpload($validated['evidence_urls'], 'projects/impact-report-evidence');
        }

        $validated['created_by'] = $actor->uuid;
        $report = $this->impactReportRepository->create($project, $validated);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_IMPACT_REPORT_ADDED,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid, 'impact_report_uuid' => $report->uuid],
            $actor->displayName().' added an impact report to project: '.$project->title.'.',
            ProjectImpactReport::class,
            $report->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $report;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateImpactReport(string $projectUuid, string $reportUuid, array $validated, Admin $actor, Request $request): ProjectImpactReport
    {
        $project = $this->findForAdmin($projectUuid);
        $report = $this->findImpactReport($reportUuid);
        $this->assertOwnedByProject($report->project_id, $project->id, 'Project impact report not found.');

        if (isset($validated['deliverable_uuid'])) {
            $deliverable = $this->findDeliverable($validated['deliverable_uuid']);
            $this->assertBelongsToProject($deliverable->project_id, $report->project_id, 'deliverable');
            $validated['deliverable_id'] = $deliverable->id;
            unset($validated['deliverable_uuid']);
        }
        if (isset($validated['budget_line_uuid'])) {
            $budgetLine = $this->findBudgetLine($validated['budget_line_uuid']);
            $this->assertBelongsToProject($budgetLine->project_id, $report->project_id, 'budget line');
            $validated['budget_line_id'] = $budgetLine->id;
            unset($validated['budget_line_uuid']);
        }

        if (array_key_exists('evidence_urls', $validated)) {
            // Unlike the single-file evidence_url on expenditures, this is an array field: an
            // explicit [] unambiguously means "clear all evidence" (arrays aren't touched by
            // ConvertEmptyStringsToNull), and omitting the key entirely leaves the list as-is.
            $validated['evidence_urls'] = FileUploadHelper::smartMultipleFileUpload($validated['evidence_urls'], 'projects/impact-report-evidence');
        }

        $report = $this->impactReportRepository->update($report, $validated);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_IMPACT_REPORT_UPDATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $report->project->uuid, 'impact_report_uuid' => $report->uuid],
            $actor->displayName().' updated a project impact report: '.$report->title.'.',
            ProjectImpactReport::class,
            $report->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $report;
    }

    public function deleteImpactReport(string $projectUuid, string $reportUuid, Admin $actor, Request $request): void
    {
        $project = $this->findForAdmin($projectUuid);
        $report = $this->findImpactReport($reportUuid);
        $this->assertOwnedByProject($report->project_id, $project->id, 'Project impact report not found.');
        $this->impactReportRepository->delete($report);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_IMPACT_REPORT_DELETED,
            $request,
            $actor->uuid,
            ['project_uuid' => $report->project->uuid, 'impact_report_uuid' => $report->uuid],
            $actor->displayName().' deleted a project impact report: '.$report->title.'.',
            ProjectImpactReport::class,
            $report->uuid,
            ModuleEnums::project_management,
            200,
        );
    }

    // Documents

    /**
     * @param  array<string, mixed>  $filters
     */
    public function documents(string $projectUuid, array $filters): LengthAwarePaginator
    {
        $project = $this->findForAdmin($projectUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->documentRepository->paginateForProject($project, $filters, $perPage);
    }

    public function findDocument(string $uuid): ProjectDocument
    {
        $document = $this->documentRepository->findByUuid($uuid);
        if (! $document instanceof ProjectDocument) {
            throw new ApiException('Project document not found.', 404);
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    /**
     * The upload form has no name field (only a category and up to 3 files), so the document's
     * display name is derived from the first file's own filename.
     */
    private function deriveDocumentNameFromUrl(string $url): string
    {
        $filename = pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_FILENAME);

        return Str::of($filename)->replace(['-', '_'], ' ')->squish()->title()->toString();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return \Illuminate\Database\Eloquent\Collection<int, ProjectDocument>
     */
    public function uploadDocument(string $projectUuid, array $validated, Admin $actor, Request $request): \Illuminate\Database\Eloquent\Collection
    {
        $project = $this->findForAdmin($projectUuid);
        $category = $validated['category'] ?? null;

        $documents = new \Illuminate\Database\Eloquent\Collection;

        foreach ($validated['file_urls'] as $rawFile) {
            $documentName = $rawFile instanceof UploadedFile
                ? Str::of(pathinfo($rawFile->getClientOriginalName(), PATHINFO_FILENAME))->replace(['-', '_'], ' ')->squish()->title()->toString()
                : null;

            $fileUrl = FileUploadHelper::smartSingleFileUpload($rawFile, 'projects/documents');

            $document = $this->documentRepository->create($project, [
                'name' => $documentName ?? $this->deriveDocumentNameFromUrl((string) $fileUrl),
                'category' => $category,
                'file_url' => $fileUrl,
                'created_by' => $actor->uuid,
            ]);

            GeneralHelper::storeAuditLog(
                UserTypeEnum::ADMIN,
                AuditActionEnum::PROJECT_DOCUMENT_UPLOADED,
                $request,
                $actor->uuid,
                ['project_uuid' => $project->uuid, 'document_uuid' => $document->uuid],
                $actor->displayName().' uploaded a document to project: '.$project->title.'.',
                ProjectDocument::class,
                $document->uuid,
                ModuleEnums::project_management,
                200,
            );

            $documents->push($document);
        }

        return $documents;
    }

    public function deleteDocument(string $projectUuid, string $documentUuid, Admin $actor, Request $request): void
    {
        $project = $this->findForAdmin($projectUuid);
        $document = $this->findDocument($documentUuid);
        $this->assertOwnedByProject($document->project_id, $project->id, 'Project document not found.');
        $this->documentRepository->delete($document);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_DOCUMENT_DELETED,
            $request,
            $actor->uuid,
            ['project_uuid' => $document->project->uuid, 'document_uuid' => $document->uuid],
            $actor->displayName().' deleted a project document: '.$document->name.'.',
            ProjectDocument::class,
            $document->uuid,
            ModuleEnums::project_management,
            200,
        );
    }

    // Broadcasts

    /**
     * @param  array<string, mixed>  $filters
     */
    public function broadcasts(string $projectUuid, array $filters): LengthAwarePaginator
    {
        $project = $this->findForAdmin($projectUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->broadcastRepository->paginateForProject($project, $filters, $perPage);
    }

    public function findBroadcast(string $uuid): ProjectBroadcast
    {
        $broadcast = $this->broadcastRepository->findByUuid($uuid);
        if (! $broadcast instanceof ProjectBroadcast) {
            throw new ApiException('Project broadcast not found.', 404);
        }

        return $broadcast;
    }

    public function showBroadcast(string $projectUuid, string $broadcastUuid): ProjectBroadcast
    {
        $project = $this->findForAdmin($projectUuid);
        $broadcast = $this->findBroadcast($broadcastUuid);
        $this->assertOwnedByProject($broadcast->project_id, $project->id, 'Project broadcast not found.');

        return $broadcast;
    }

    /**
     * Resolves the final recipient admin ids: every current team member when $allTeamMembers is
     * true (regardless of $recipientUuids), otherwise exactly the hand-picked admins.
     *
     * @param  list<string>  $recipientUuids
     * @return list<int>
     */
    private function resolveNotifyRecipientIds(Project $project, bool $allTeamMembers, array $recipientUuids): array
    {
        if ($allTeamMembers) {
            return $project->teamMembers()->pluck('admins.id')->all();
        }

        return $this->resolveAdminIds($recipientUuids);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function sendBroadcast(string $projectUuid, array $validated, Admin $actor, Request $request): ProjectBroadcast
    {
        $project = $this->findForAdmin($projectUuid);
        $allTeamMembers = (bool) ($validated['all_team_members'] ?? true);
        $recipientUuids = $validated['recipient_admin_uuids'] ?? [];
        unset($validated['all_team_members'], $validated['recipient_admin_uuids']);

        $recipientIds = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $recipientUuids);

        if (array_key_exists('attachment_urls', $validated)) {
            $validated['attachment_urls'] = FileUploadHelper::smartMultipleFileUpload($validated['attachment_urls'], 'projects/broadcast-attachments');
        }

        $broadcast = $this->broadcastRepository->create($project, [
            ...$validated,
            'all_team_members' => $allTeamMembers,
            'number_of_reach' => count($recipientIds),
            'created_by' => $actor->uuid,
        ], $recipientIds);

        $this->dispatchBroadcastNotification($project, $broadcast, $recipientIds);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_BROADCAST_SENT,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid, 'broadcast_uuid' => $broadcast->uuid],
            $actor->displayName().' sent a broadcast on project: '.$project->title.'.',
            ProjectBroadcast::class,
            $broadcast->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $broadcast;
    }

    /**
     * @param  list<int>  $recipientAdminIds
     */
    private function dispatchBroadcastNotification(Project $project, ProjectBroadcast $broadcast, array $recipientAdminIds): void
    {
        if ($recipientAdminIds === []) {
            return;
        }

        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::project_management->value,
            event: 'project_broadcast_sent',
            title: $broadcast->title,
            message: $broadcast->message,
            meta: ['project_uuid' => $project->uuid, 'broadcast_uuid' => $broadcast->uuid],
            sendMail: $broadcast->delivery_via === ProjectBroadcastDeliveryEnum::EMAIL->value,
        );

        $this->notificationDispatchService->notifyAdminsByUuids(
            Admin::query()->whereIn('id', $recipientAdminIds)->pluck('uuid')->all(),
            $notification,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateBroadcast(string $projectUuid, string $broadcastUuid, array $validated, Admin $actor, Request $request): ProjectBroadcast
    {
        $project = $this->findForAdmin($projectUuid);
        $broadcast = $this->findBroadcast($broadcastUuid);
        $this->assertOwnedByProject($broadcast->project_id, $project->id, 'Project broadcast not found.');

        $recipientIds = null;
        if (array_key_exists('all_team_members', $validated) || array_key_exists('recipient_admin_uuids', $validated)) {
            $allTeamMembers = (bool) ($validated['all_team_members'] ?? $broadcast->all_team_members);
            $recipientUuids = $validated['recipient_admin_uuids'] ?? [];
            $recipientIds = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $recipientUuids);
            $validated['all_team_members'] = $allTeamMembers;
            $validated['number_of_reach'] = count($recipientIds);
        }
        unset($validated['recipient_admin_uuids']);

        if (array_key_exists('attachment_urls', $validated)) {
            $validated['attachment_urls'] = FileUploadHelper::smartMultipleFileUpload($validated['attachment_urls'], 'projects/broadcast-attachments');
        }

        $broadcast = $this->broadcastRepository->update($broadcast, $validated, $recipientIds);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_BROADCAST_UPDATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid, 'broadcast_uuid' => $broadcast->uuid],
            $actor->displayName().' updated a project broadcast: '.$broadcast->title.'.',
            ProjectBroadcast::class,
            $broadcast->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $broadcast;
    }

    // Risks

    /**
     * @param  array<string, mixed>  $filters
     */
    public function risks(string $projectUuid, array $filters): LengthAwarePaginator
    {
        $project = $this->findForAdmin($projectUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->riskRepository->paginateForProject($project, $filters, $perPage);
    }

    public function findRisk(string $uuid): ProjectRisk
    {
        $risk = $this->riskRepository->findByUuid($uuid);
        if (! $risk instanceof ProjectRisk) {
            throw new ApiException('Project risk not found.', 404);
        }

        return $risk;
    }

    public function showRisk(string $projectUuid, string $riskUuid): ProjectRisk
    {
        $project = $this->findForAdmin($projectUuid);
        $risk = $this->findRisk($riskUuid);
        $this->assertOwnedByProject($risk->project_id, $project->id, 'Project risk not found.');

        return $risk;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function addRisk(string $projectUuid, array $validated, Admin $actor, Request $request): ProjectRisk
    {
        $project = $this->findForAdmin($projectUuid);
        $allTeamMembers = (bool) ($validated['all_team_members'] ?? true);
        $recipientUuids = $validated['recipient_admin_uuids'] ?? [];
        unset($validated['all_team_members'], $validated['recipient_admin_uuids']);

        if (isset($validated['milestone_uuid'])) {
            $milestone = $this->findMilestone($validated['milestone_uuid']);
            $this->assertBelongsToProject($milestone->project_id, $project->id, 'milestone');
            $validated['milestone_id'] = $milestone->id;
        }
        unset($validated['milestone_uuid']);

        $recipientIds = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $recipientUuids);

        $risk = $this->riskRepository->create($project, [
            ...$validated,
            'all_team_members' => $allTeamMembers,
            'raised_by' => $actor->uuid,
            'status' => ProjectRiskStatusEnum::OPEN->value,
        ], $recipientIds);

        $this->dispatchRiskNotification($project, $risk, $recipientIds);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_RISK_CREATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid, 'risk_uuid' => $risk->uuid],
            $actor->displayName().' raised a risk on project: '.$project->title.'.',
            ProjectRisk::class,
            $risk->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $risk;
    }

    /**
     * @param  list<int>  $recipientAdminIds
     */
    private function dispatchRiskNotification(Project $project, ProjectRisk $risk, array $recipientAdminIds): void
    {
        if ($recipientAdminIds === []) {
            return;
        }

        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::project_management->value,
            event: 'project_risk_raised',
            title: $risk->title,
            message: $risk->description,
            meta: ['project_uuid' => $project->uuid, 'risk_uuid' => $risk->uuid],
        );

        $this->notificationDispatchService->notifyAdminsByUuids(
            Admin::query()->whereIn('id', $recipientAdminIds)->pluck('uuid')->all(),
            $notification,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateRisk(string $projectUuid, string $riskUuid, array $validated, Admin $actor, Request $request): ProjectRisk
    {
        $project = $this->findForAdmin($projectUuid);
        $risk = $this->findRisk($riskUuid);
        $this->assertOwnedByProject($risk->project_id, $project->id, 'Project risk not found.');

        if (array_key_exists('milestone_uuid', $validated)) {
            if ($validated['milestone_uuid'] === null) {
                $validated['milestone_id'] = null;
            } else {
                $milestone = $this->findMilestone($validated['milestone_uuid']);
                $this->assertBelongsToProject($milestone->project_id, $project->id, 'milestone');
                $validated['milestone_id'] = $milestone->id;
            }
        }
        unset($validated['milestone_uuid']);

        $recipientIds = null;
        if (array_key_exists('all_team_members', $validated) || array_key_exists('recipient_admin_uuids', $validated)) {
            $allTeamMembers = (bool) ($validated['all_team_members'] ?? $risk->all_team_members);
            $recipientUuids = $validated['recipient_admin_uuids'] ?? [];
            $recipientIds = $this->resolveNotifyRecipientIds($project, $allTeamMembers, $recipientUuids);
            $validated['all_team_members'] = $allTeamMembers;
        }
        unset($validated['recipient_admin_uuids']);

        $risk = $this->riskRepository->update($risk, $validated, $recipientIds);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_RISK_UPDATED,
            $request,
            $actor->uuid,
            ['project_uuid' => $project->uuid, 'risk_uuid' => $risk->uuid],
            $actor->displayName().' updated a project risk: '.$risk->title.'.',
            ProjectRisk::class,
            $risk->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $risk;
    }

    public function resolveRisk(string $projectUuid, string $riskUuid, Admin $actor, Request $request): ProjectRisk
    {
        $project = $this->findForAdmin($projectUuid);
        $risk = $this->findRisk($riskUuid);
        $this->assertOwnedByProject($risk->project_id, $project->id, 'Project risk not found.');
        $risk = $this->riskRepository->markResolved($risk, $actor->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROJECT_RISK_RESOLVED,
            $request,
            $actor->uuid,
            ['project_uuid' => $risk->project->uuid, 'risk_uuid' => $risk->uuid],
            $actor->displayName().' marked a project risk as resolved: '.$risk->title.'.',
            ProjectRisk::class,
            $risk->uuid,
            ModuleEnums::project_management,
            200,
        );

        return $risk;
    }

    // Report

    public function generateSummaryReport(string $uuid, ?string $startDate, ?string $endDate): Response
    {
        $project = $this->findForAdmin($uuid);
        $start = $startDate !== null ? Carbon::parse($startDate)->startOfDay() : null;
        $end = $endDate !== null ? Carbon::parse($endDate)->endOfDay() : null;
        $currency = $project->currency;

        $rawBudgetOverview = $this->projectBudgetOverview($project);
        $budgetOverview = [
            'approved_budget_formatted' => Money::format($rawBudgetOverview['approved_budget'], $currency),
            'funding_received_formatted' => Money::format($rawBudgetOverview['funding_received'], $currency),
            'total_utilized_formatted' => Money::format($rawBudgetOverview['total_utilized'], $currency),
            'remaining_budget_formatted' => Money::format($rawBudgetOverview['remaining_budget'], $currency),
        ];

        $location = trim(implode(', ', array_filter([$project->state_lga, $project->country])));

        $html = view('pdf.admin-project-summary-report', [
            'project' => [
                'title' => $project->title,
                'status_label' => ProjectStatusEnum::from($project->status)->label(),
                'category_label' => $project->category !== null ? ProjectCategoryEnum::from($project->category)->label() : null,
                'location' => $location !== '' ? $location : null,
                'starts_at' => $project->starts_at?->toDateString(),
                'ends_at' => $project->ends_at?->toDateString(),
            ],
            'budgetOverview' => $budgetOverview,
            'team' => $this->reportTeamRows($project),
            'objectives' => $this->reportObjectiveRows($project),
            'milestones' => $this->reportMilestoneRows($project, $start, $end),
            'deliverables' => $this->reportDeliverableRows($project, $start, $end),
            'budgetLines' => $this->reportBudgetLineRows($project),
            'expenditures' => $this->reportExpenditureRows($project, $start, $end),
            'risks' => $this->reportRiskRows($project, $start, $end),
            'periodLabel' => $start !== null && $end !== null ? $start->toDateString().' to '.$end->toDateString() : 'All dates',
            'generatedAt' => now((string) config('app.timezone')),
            'logoBase64' => $this->reportLogoBase64(),
        ])->render();

        $options = new Options;
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'sans-serif');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($project->title).'-summary-report.pdf"',
        ]);
    }

    private function reportLogoBase64(): ?string
    {
        $logoPath = public_path('assets/logo/quiva-logo-black.png');

        return File::exists($logoPath) ? 'data:image/png;base64,'.base64_encode(File::get($logoPath)) : null;
    }

    /**
     * @return list<array{name: string, role_title: ?string, is_manager: bool}>
     */
    private function reportTeamRows(Project $project): array
    {
        return $project->teamMembers()->get()->map(fn (Admin $admin): array => [
            'name' => $admin->displayName(),
            'role_title' => $admin->pivot->role_title,
            'is_manager' => (bool) $admin->pivot->is_manager,
        ])->all();
    }

    /**
     * @return list<array{title: string, description: string, is_completed: bool}>
     */
    private function reportObjectiveRows(Project $project): array
    {
        return $this->objectiveRepository->allForProject($project)->map(fn (ProjectObjective $objective): array => [
            'title' => $objective->title,
            // description is rich text (HTML) from the admin's editor; stripped to plain text here
            // since this is a compact tabular row, not a full rich-content view.
            'description' => trim(strip_tags((string) $objective->description)),
            'is_completed' => (bool) $objective->is_completed,
        ])->all();
    }

    /**
     * @return list<array{title: string, due_at: string, completion_percentage: int, status: string}>
     */
    private function reportMilestoneRows(Project $project, ?Carbon $start, ?Carbon $end): array
    {
        return $this->milestoneRepository->allForProject($project)
            ->filter(fn (ProjectMilestone $milestone): bool => $this->withinReportRange($milestone->due_at, $start, $end))
            ->map(fn (ProjectMilestone $milestone): array => [
                'title' => $milestone->title,
                'due_at' => $milestone->due_at?->toDateString() ?? 'No due date',
                'completion_percentage' => $milestone->completion_percentage,
                'status' => ucfirst(str_replace('_', ' ', $milestone->status())),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, milestone_title: ?string, due_at: string, is_completed: bool}>
     */
    private function reportDeliverableRows(Project $project, ?Carbon $start, ?Carbon $end): array
    {
        return $this->deliverableRepository->allForProject($project)
            ->filter(fn (ProjectDeliverable $deliverable): bool => $this->withinReportRange($deliverable->due_at, $start, $end))
            ->map(fn (ProjectDeliverable $deliverable): array => [
                'title' => $deliverable->title,
                'milestone_title' => $deliverable->milestone?->title,
                'due_at' => $deliverable->due_at?->toDateString() ?? 'No due date',
                'is_completed' => (bool) $deliverable->is_completed,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, reference_id: string, amount_allocated_formatted: string}>
     */
    private function reportBudgetLineRows(Project $project): array
    {
        return $this->budgetLineRepository->allForProject($project)->map(fn (ProjectBudgetLine $line): array => [
            'title' => $line->title,
            'reference_id' => $line->reference_id,
            'amount_allocated_formatted' => Money::format($line->amount_allocated, $project->currency),
        ])->all();
    }

    /**
     * @return list<array{title: string, budget_line_title: ?string, amount_formatted: string, transaction_date: string}>
     */
    private function reportExpenditureRows(Project $project, ?Carbon $start, ?Carbon $end): array
    {
        return $this->expenditureRepository->allForProject($project)
            ->filter(fn (ProjectExpenditure $expenditure): bool => $this->withinReportRange($expenditure->transaction_date, $start, $end))
            ->map(fn (ProjectExpenditure $expenditure): array => [
                'title' => $expenditure->title,
                'budget_line_title' => $expenditure->budgetLine?->title,
                'amount_formatted' => Money::format($expenditure->amount, $project->currency),
                'transaction_date' => $expenditure->transaction_date?->toDateString() ?? 'No date',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, severity_label: string, status_label: string, raised_by: ?string}>
     */
    private function reportRiskRows(Project $project, ?Carbon $start, ?Carbon $end): array
    {
        return $this->riskRepository->allForProject($project)
            ->filter(fn (ProjectRisk $risk): bool => $this->withinReportRange($risk->created_at, $start, $end))
            ->map(fn (ProjectRisk $risk): array => [
                'title' => $risk->title,
                'severity_label' => ProjectRiskSeverityEnum::from($risk->severity)->label(),
                'status_label' => ProjectRiskStatusEnum::from($risk->status)->label(),
                'raised_by' => $risk->raisedByAdmin?->displayName(),
            ])
            ->values()
            ->all();
    }

    /**
     * A null $start/$end means "all time", so every row passes. Otherwise a row with no date of
     * its own can't be placed within a bounded range and is excluded.
     */
    private function withinReportRange(?\Carbon\CarbonInterface $date, ?Carbon $start, ?Carbon $end): bool
    {
        if ($start === null && $end === null) {
            return true;
        }

        if ($date === null) {
            return false;
        }

        if ($start !== null && $date->lt($start)) {
            return false;
        }

        if ($end !== null && $date->gt($end)) {
            return false;
        }

        return true;
    }

    // Upcoming Deadlines

    /**
     * Not-yet-completed milestones and deliverables for the project, soonest due first.
     * "active" means due within the next 7 days; anything further out is "upcoming".
     *
     * @return array{milestones: list<array<string, mixed>>, deliverables: list<array<string, mixed>>}
     */
    public function upcomingDeadlines(string $projectUuid, int $limit = 10): array
    {
        $project = $this->findForAdmin($projectUuid);
        $now = Carbon::now()->startOfDay();

        $milestones = $this->milestoneRepository->allForProject($project)
            ->filter(fn (ProjectMilestone $milestone): bool => ! $milestone->is_completed)
            ->sortBy('due_at')
            ->take($limit)
            ->map(fn (ProjectMilestone $milestone): array => $this->deadlineRow($milestone->uuid, $milestone->title, $milestone->due_at, $now))
            ->values()
            ->all();

        $deliverables = $this->deliverableRepository->allForProject($project)
            ->filter(fn (ProjectDeliverable $deliverable): bool => ! $deliverable->is_completed)
            ->sortBy('due_at')
            ->take($limit)
            ->map(fn (ProjectDeliverable $deliverable): array => $this->deadlineRow(
                $deliverable->uuid,
                $deliverable->title,
                $deliverable->due_at,
                $now,
                includeMilestone: true,
                milestone: $deliverable->milestone === null ? null : ['uuid' => $deliverable->milestone->uuid, 'title' => $deliverable->milestone->title],
            ))
            ->values()
            ->all();

        return ['milestones' => $milestones, 'deliverables' => $deliverables];
    }

    /**
     * @param  array{uuid: string, title: string}|null  $milestone
     * @return array{uuid: string, title: string, due_at: ?string, days_until_due: ?int, status: string, due_label: string, milestone?: array{uuid: string, title: string}|null}
     */
    private function deadlineRow(string $uuid, string $title, ?\Carbon\CarbonInterface $dueAt, Carbon $now, bool $includeMilestone = false, ?array $milestone = null): array
    {
        $daysUntilDue = $dueAt !== null ? (int) $now->diffInDays($dueAt->copy()->startOfDay(), false) : null;

        $status = match (true) {
            $daysUntilDue === null => 'upcoming',
            $daysUntilDue < 0 => 'overdue',
            $daysUntilDue <= 7 => 'active',
            default => 'upcoming',
        };

        $dueLabel = match (true) {
            $daysUntilDue === null => 'No due date',
            $daysUntilDue < 0 => 'Overdue by '.abs($daysUntilDue).' day'.(abs($daysUntilDue) === 1 ? '' : 's'),
            $daysUntilDue === 0 => 'Due today',
            default => 'Due in '.$daysUntilDue.' day'.($daysUntilDue === 1 ? '' : 's'),
        };

        $row = [
            'uuid' => $uuid,
            'title' => $title,
            'due_at' => $dueAt?->toDateString(),
            'days_until_due' => $daysUntilDue,
            'status' => $status,
            'due_label' => $dueLabel,
        ];

        if ($includeMilestone) {
            $row['milestone'] = $milestone;
        }

        return $row;
    }
}

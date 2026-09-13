<?php

namespace App\Services\Research;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\ResearchStatusEnum;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\Admin;
use App\Models\Research;
use App\Models\ResearchDeliverable;
use App\Models\ResearchMilestone;
use App\Models\ResearchObjective;
use App\Notifications\GenericDatabaseNotification;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;
use App\Repositories\Contracts\Research\ResearchDeliverableRepositoryInterface;
use App\Repositories\Contracts\Research\ResearchMilestoneRepositoryInterface;
use App\Repositories\Contracts\Research\ResearchObjectiveRepositoryInterface;
use App\Repositories\Contracts\Research\ResearchRepositoryInterface;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ResearchService
{
    public function __construct(
        private readonly ResearchRepositoryInterface $researchRepository,
        private readonly ResearchObjectiveRepositoryInterface $objectiveRepository,
        private readonly ResearchMilestoneRepositoryInterface $milestoneRepository,
        private readonly ResearchDeliverableRepositoryInterface $deliverableRepository,
        private readonly AdminRepositoryInterface $adminRepository,
        private readonly NotificationDispatchService $notificationDispatchService,
    ) {}

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
     * Research has no dedicated team roster of its own, so "all team members" resolves against
     * every active, login-enabled admin instead of a per-research membership list.
     *
     * @param  list<string>  $recipientUuids
     * @return list<int>
     */
    private function resolveNotifyRecipientIds(bool $allTeamMembers, array $recipientUuids): array
    {
        if ($allTeamMembers) {
            return $this->adminRepository->listActive()->pluck('id')->all();
        }

        return $this->resolveAdminIds($recipientUuids);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->researchRepository->paginateAdmin($filters, $perPage);
    }

    public function findForAdmin(string $uuid): Research
    {
        $research = $this->researchRepository->findByUuid($uuid);

        if (! $research instanceof Research) {
            throw new ApiException('Research not found.', 404);
        }

        return $research;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{all: int, open: int, completed: int}
     */
    public function statusOverview(array $filters = []): array
    {
        $window = ListingFilterRules::resolveDateWindow($filters);

        return $this->researchRepository->countByStatus($window['start'], $window['end']);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, Admin $actor, Request $request): Research
    {
        $objectives = $validated['objectives'] ?? [];
        $milestonesInput = $validated['milestones'] ?? [];
        $deliverablesInput = $validated['deliverables'] ?? [];
        unset($validated['objectives'], $validated['milestones'], $validated['deliverables']);

        $research = $this->researchRepository->create([
            ...$validated,
            'status' => ResearchStatusEnum::OPEN->value,
            'created_by' => $actor->uuid,
        ]);

        foreach ($objectives as $objective) {
            $allTeamMembers = (bool) ($objective['all_team_members'] ?? false);
            $assignees = $this->resolveNotifyRecipientIds($allTeamMembers, $objective['assignee_uuids'] ?? []);
            $this->objectiveRepository->create($research, [
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
            $assignees = $this->resolveNotifyRecipientIds($allTeamMembers, $milestone['assignee_uuids'] ?? []);
            $notifyAllTeamMembers = (bool) ($milestone['notify_all_team_members'] ?? false);
            $notifyRecipients = $this->resolveNotifyRecipientIds($notifyAllTeamMembers, $milestone['notify_admin_uuids'] ?? []);
            $created = $this->milestoneRepository->create($research, [
                'title' => $milestone['title'],
                'starts_at' => $milestone['starts_at'] ?? null,
                'due_at' => $milestone['due_at'],
                'completion_percentage' => $milestone['completion_percentage'] ?? 0,
                'all_team_members' => $allTeamMembers,
                'notify_all_team_members' => $notifyAllTeamMembers,
            ], $assignees, $notifyRecipients);
            $this->dispatchMilestoneNotification($research, $created, $notifyRecipients);
            $createdMilestonesByIndex[$index] = $created;
        }

        foreach ($deliverablesInput as $deliverable) {
            $allTeamMembers = (bool) ($deliverable['all_team_members'] ?? false);
            $assignees = $this->resolveNotifyRecipientIds($allTeamMembers, $deliverable['assignee_uuids'] ?? []);
            $notifyAllTeamMembers = (bool) ($deliverable['notify_all_team_members'] ?? false);
            $notifyRecipients = $this->resolveNotifyRecipientIds($notifyAllTeamMembers, $deliverable['notify_admin_uuids'] ?? []);
            $milestoneIndex = $deliverable['milestone_index'] ?? null;
            $created = $this->deliverableRepository->create($research, [
                'title' => $deliverable['title'],
                'starts_at' => $deliverable['starts_at'] ?? null,
                'due_at' => $deliverable['due_at'],
                'milestone_id' => $milestoneIndex !== null ? ($createdMilestonesByIndex[$milestoneIndex]->id ?? null) : null,
                'all_team_members' => $allTeamMembers,
                'notify_all_team_members' => $notifyAllTeamMembers,
            ], $assignees, $notifyRecipients);
            $this->dispatchDeliverableNotification($research, $created, $notifyRecipients);
        }

        $research = $this->findForAdmin($research->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::RESEARCH_CREATED,
            $request,
            $actor->uuid,
            ['research_uuid' => $research->uuid, 'title' => $research->title],
            $actor->displayName().' created a research: '.$research->title.'.',
            Research::class,
            $research->uuid,
            ModuleEnums::research_management,
            200,
        );

        return $research;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(string $uuid, array $validated, Admin $actor, Request $request): Research
    {
        $research = $this->findForAdmin($uuid);

        $objectivesInput = $validated['objectives'] ?? null;
        $milestonesInput = $validated['milestones'] ?? null;
        $deliverablesInput = $validated['deliverables'] ?? null;
        unset($validated['objectives'], $validated['milestones'], $validated['deliverables']);

        $research = $this->researchRepository->update($research, $validated);

        if ($objectivesInput !== null) {
            $this->syncObjectives($research, $objectivesInput);
        }

        if ($milestonesInput !== null || $deliverablesInput !== null) {
            $this->syncMilestonesAndDeliverables($research, $milestonesInput ?? [], $deliverablesInput ?? []);
        }

        $research = $this->findForAdmin($research->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::RESEARCH_UPDATED,
            $request,
            $actor->uuid,
            ['research_uuid' => $research->uuid],
            $actor->displayName().' updated a research: '.$research->title.'.',
            Research::class,
            $research->uuid,
            ModuleEnums::research_management,
            200,
        );

        return $research;
    }

    /**
     * Replace-set sync: items with a uuid are updated, items without one are created, and any
     * existing objective not present in $objectivesInput is deleted.
     *
     * @param  list<array<string, mixed>>  $objectivesInput
     */
    private function syncObjectives(Research $research, array $objectivesInput): void
    {
        $keptUuids = [];

        foreach ($objectivesInput as $objectiveData) {
            $allTeamMembers = (bool) ($objectiveData['all_team_members'] ?? false);
            $assignees = $this->resolveNotifyRecipientIds($allTeamMembers, $objectiveData['assignee_uuids'] ?? []);
            $data = [
                'title' => $objectiveData['title'],
                'description' => $objectiveData['description'] ?? null,
                'all_team_members' => $allTeamMembers,
            ];

            if (isset($objectiveData['uuid'])) {
                $objective = $this->objectiveRepository->findByUuid($objectiveData['uuid']);
                if ($objective instanceof ResearchObjective) {
                    $this->objectiveRepository->update($objective, $data, $assignees);
                    $keptUuids[] = $objective->uuid;

                    continue;
                }
            }

            $created = $this->objectiveRepository->create($research, $data, $assignees);
            $keptUuids[] = $created->uuid;
        }

        foreach ($this->objectiveRepository->allForResearch($research) as $existing) {
            if (! in_array($existing->uuid, $keptUuids, true)) {
                $this->objectiveRepository->delete($existing);
            }
        }
    }

    /**
     * Same replace-set sync as {@see self::syncObjectives()}, for milestones and deliverables
     * together, since a deliverable can link to a milestone by index (new) or uuid (existing).
     *
     * @param  list<array<string, mixed>>  $milestonesInput
     * @param  list<array<string, mixed>>  $deliverablesInput
     */
    private function syncMilestonesAndDeliverables(Research $research, array $milestonesInput, array $deliverablesInput): void
    {
        $keptMilestoneUuids = [];
        $milestonesByIndex = [];

        foreach ($milestonesInput as $index => $milestoneData) {
            $allTeamMembers = (bool) ($milestoneData['all_team_members'] ?? false);
            $assignees = $this->resolveNotifyRecipientIds($allTeamMembers, $milestoneData['assignee_uuids'] ?? []);
            $notifyAllTeamMembers = (bool) ($milestoneData['notify_all_team_members'] ?? false);
            $notifyRecipients = $this->resolveNotifyRecipientIds($notifyAllTeamMembers, $milestoneData['notify_admin_uuids'] ?? []);
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
                if ($milestone instanceof ResearchMilestone) {
                    $milestone = $this->milestoneRepository->update($milestone, $data, $assignees, $notifyRecipients);
                    $milestonesByIndex[$index] = $milestone;
                    $keptMilestoneUuids[] = $milestone->uuid;

                    continue;
                }
            }

            $data['completion_percentage'] ??= 0;
            $milestone = $this->milestoneRepository->create($research, $data, $assignees, $notifyRecipients);
            $this->dispatchMilestoneNotification($research, $milestone, $notifyRecipients);
            $milestonesByIndex[$index] = $milestone;
            $keptMilestoneUuids[] = $milestone->uuid;
        }

        foreach ($this->milestoneRepository->allForResearch($research) as $existing) {
            if (! in_array($existing->uuid, $keptMilestoneUuids, true)) {
                $this->milestoneRepository->delete($existing);
            }
        }

        $keptDeliverableUuids = [];

        foreach ($deliverablesInput as $deliverableData) {
            $allTeamMembers = (bool) ($deliverableData['all_team_members'] ?? false);
            $assignees = $this->resolveNotifyRecipientIds($allTeamMembers, $deliverableData['assignee_uuids'] ?? []);
            $notifyAllTeamMembers = (bool) ($deliverableData['notify_all_team_members'] ?? false);
            $notifyRecipients = $this->resolveNotifyRecipientIds($notifyAllTeamMembers, $deliverableData['notify_admin_uuids'] ?? []);
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
                if ($deliverable instanceof ResearchDeliverable) {
                    $deliverable = $this->deliverableRepository->update($deliverable, $data, $assignees, $notifyRecipients);
                    $keptDeliverableUuids[] = $deliverable->uuid;

                    continue;
                }
            }

            $deliverable = $this->deliverableRepository->create($research, $data, $assignees, $notifyRecipients);
            $this->dispatchDeliverableNotification($research, $deliverable, $notifyRecipients);
            $keptDeliverableUuids[] = $deliverable->uuid;
        }

        foreach ($this->deliverableRepository->allForResearch($research) as $existing) {
            if (! in_array($existing->uuid, $keptDeliverableUuids, true)) {
                $this->deliverableRepository->delete($existing);
            }
        }
    }

    public function markComplete(string $uuid, Admin $actor, Request $request): Research
    {
        $research = $this->findForAdmin($uuid);

        if ($research->status === ResearchStatusEnum::COMPLETED->value) {
            throw new ApiException('Research is already marked as complete.', 422);
        }

        $research = $this->researchRepository->update($research, [
            'status' => ResearchStatusEnum::COMPLETED->value,
            'completed_at' => now(),
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::RESEARCH_COMPLETED,
            $request,
            $actor->uuid,
            ['research_uuid' => $research->uuid],
            $actor->displayName().' marked a research as complete: '.$research->title.'.',
            Research::class,
            $research->uuid,
            ModuleEnums::research_management,
            200,
        );

        return $research;
    }

    // Objectives

    /**
     * @param  array<string, mixed>  $filters
     */
    public function objectives(string $researchUuid, array $filters): LengthAwarePaginator
    {
        $research = $this->findForAdmin($researchUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->objectiveRepository->paginateForResearch($research, $filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function addObjective(string $researchUuid, array $validated, Admin $actor, Request $request): ResearchObjective
    {
        $research = $this->findForAdmin($researchUuid);
        $allTeamMembers = (bool) ($validated['all_team_members'] ?? false);
        $assignees = $this->resolveNotifyRecipientIds($allTeamMembers, $validated['assignee_uuids'] ?? []);
        $validated['all_team_members'] = $allTeamMembers;
        unset($validated['assignee_uuids']);

        $objective = $this->objectiveRepository->create($research, $validated, $assignees);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::RESEARCH_OBJECTIVE_CREATED,
            $request,
            $actor->uuid,
            ['research_uuid' => $research->uuid, 'objective_uuid' => $objective->uuid],
            $actor->displayName().' added an objective to research: '.$research->title.'.',
            ResearchObjective::class,
            $objective->uuid,
            ModuleEnums::research_management,
            200,
        );

        return $objective;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateObjective(string $researchUuid, string $objectiveUuid, array $validated, Admin $actor, Request $request): ResearchObjective
    {
        $research = $this->findForAdmin($researchUuid);
        $objective = $this->objectiveRepository->findByUuid($objectiveUuid);
        if (! $objective instanceof ResearchObjective) {
            throw new ApiException('Research objective not found.', 404);
        }
        $this->assertOwnedByResearch($objective->research_id, $research->id, 'Research objective not found.');

        $assignees = null;
        if (array_key_exists('all_team_members', $validated) || array_key_exists('assignee_uuids', $validated)) {
            $allTeamMembers = (bool) ($validated['all_team_members'] ?? $objective->all_team_members);
            $assignees = $this->resolveNotifyRecipientIds($allTeamMembers, $validated['assignee_uuids'] ?? []);
            $validated['all_team_members'] = $allTeamMembers;
        }
        unset($validated['assignee_uuids']);

        $objective = $this->objectiveRepository->update($objective, $validated, $assignees);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::RESEARCH_OBJECTIVE_UPDATED,
            $request,
            $actor->uuid,
            ['research_uuid' => $objective->research->uuid, 'objective_uuid' => $objective->uuid],
            $actor->displayName().' updated a research objective: '.$objective->title.'.',
            ResearchObjective::class,
            $objective->uuid,
            ModuleEnums::research_management,
            200,
        );

        return $objective;
    }

    // Milestones

    /**
     * @param  array<string, mixed>  $filters
     */
    public function milestones(string $researchUuid, array $filters): LengthAwarePaginator
    {
        $research = $this->findForAdmin($researchUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->milestoneRepository->paginateForResearch($research, $filters, $perPage);
    }

    public function findMilestone(string $uuid): ResearchMilestone
    {
        $milestone = $this->milestoneRepository->findByUuid($uuid);
        if (! $milestone instanceof ResearchMilestone) {
            throw new ApiException('Research milestone not found.', 404);
        }

        return $milestone;
    }

    public function showMilestone(string $researchUuid, string $milestoneUuid): ResearchMilestone
    {
        $research = $this->findForAdmin($researchUuid);
        $milestone = $this->findMilestone($milestoneUuid);
        $this->assertOwnedByResearch($milestone->research_id, $research->id, 'Research milestone not found.');

        return $milestone;
    }

    /**
     * Guards against linking a record to a milestone that belongs to a different research (the
     * uuid-only `exists:` validation rule can't catch this by itself).
     */
    private function assertBelongsToResearch(int $entityResearchId, int $researchId, string $label): void
    {
        if ($entityResearchId !== $researchId) {
            throw new ApiException("The selected {$label} does not belong to this research.", 422);
        }
    }

    /**
     * Guards a nested route against a real child uuid paired with a research it doesn't belong
     * to. Responds 404 rather than 422/403, so it reads as "doesn't exist" instead of confirming
     * the child exists under a different research.
     */
    private function assertOwnedByResearch(int $entityResearchId, int $researchId, string $notFoundMessage): void
    {
        if ($entityResearchId !== $researchId) {
            throw new ApiException($notFoundMessage, 404);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function addMilestone(string $researchUuid, array $validated, Admin $actor, Request $request): ResearchMilestone
    {
        $research = $this->findForAdmin($researchUuid);
        $allTeamMembers = (bool) ($validated['all_team_members'] ?? false);
        $assignees = $this->resolveNotifyRecipientIds($allTeamMembers, $validated['assignee_uuids'] ?? []);
        $notifyAllTeamMembers = (bool) ($validated['notify_all_team_members'] ?? false);
        $notifyRecipients = $this->resolveNotifyRecipientIds($notifyAllTeamMembers, $validated['notify_admin_uuids'] ?? []);
        unset($validated['assignee_uuids'], $validated['notify_admin_uuids']);
        $validated['all_team_members'] = $allTeamMembers;
        $validated['notify_all_team_members'] = $notifyAllTeamMembers;
        $validated['completion_percentage'] ??= 0;

        $milestone = $this->milestoneRepository->create($research, $validated, $assignees, $notifyRecipients);
        $this->dispatchMilestoneNotification($research, $milestone, $notifyRecipients);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::RESEARCH_MILESTONE_CREATED,
            $request,
            $actor->uuid,
            ['research_uuid' => $research->uuid, 'milestone_uuid' => $milestone->uuid],
            $actor->displayName().' added a milestone to research: '.$research->title.'.',
            ResearchMilestone::class,
            $milestone->uuid,
            ModuleEnums::research_management,
            200,
        );

        return $milestone;
    }

    /**
     * @param  list<int>  $recipientAdminIds
     */
    private function dispatchMilestoneNotification(Research $research, ResearchMilestone $milestone, array $recipientAdminIds): void
    {
        if ($recipientAdminIds === []) {
            return;
        }

        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::research_management->value,
            event: 'research_milestone_added',
            title: $milestone->title,
            message: 'A new milestone was added: '.$milestone->title.'.',
            meta: ['research_uuid' => $research->uuid, 'milestone_uuid' => $milestone->uuid],
        );

        $this->notificationDispatchService->notifyAdminsByUuids(
            Admin::query()->whereIn('id', $recipientAdminIds)->pluck('uuid')->all(),
            $notification,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateMilestone(string $researchUuid, string $milestoneUuid, array $validated, Admin $actor, Request $request): ResearchMilestone
    {
        $research = $this->findForAdmin($researchUuid);
        $milestone = $this->findMilestone($milestoneUuid);
        $this->assertOwnedByResearch($milestone->research_id, $research->id, 'Research milestone not found.');

        $assignees = null;
        if (array_key_exists('all_team_members', $validated) || array_key_exists('assignee_uuids', $validated)) {
            $allTeamMembers = (bool) ($validated['all_team_members'] ?? $milestone->all_team_members);
            $assignees = $this->resolveNotifyRecipientIds($allTeamMembers, $validated['assignee_uuids'] ?? []);
            $validated['all_team_members'] = $allTeamMembers;
        }
        unset($validated['assignee_uuids']);

        $notifyRecipients = null;
        if (array_key_exists('notify_all_team_members', $validated) || array_key_exists('notify_admin_uuids', $validated)) {
            $notifyAllTeamMembers = (bool) ($validated['notify_all_team_members'] ?? $milestone->notify_all_team_members);
            $notifyRecipients = $this->resolveNotifyRecipientIds($notifyAllTeamMembers, $validated['notify_admin_uuids'] ?? []);
            $validated['notify_all_team_members'] = $notifyAllTeamMembers;
        }
        unset($validated['notify_admin_uuids']);

        $milestone = $this->milestoneRepository->update($milestone, $validated, $assignees, $notifyRecipients);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::RESEARCH_MILESTONE_UPDATED,
            $request,
            $actor->uuid,
            ['research_uuid' => $milestone->research->uuid, 'milestone_uuid' => $milestone->uuid],
            $actor->displayName().' updated a research milestone: '.$milestone->title.'.',
            ResearchMilestone::class,
            $milestone->uuid,
            ModuleEnums::research_management,
            200,
        );

        return $milestone;
    }

    public function completeMilestone(string $researchUuid, string $milestoneUuid, Admin $actor, Request $request): ResearchMilestone
    {
        $research = $this->findForAdmin($researchUuid);
        $milestone = $this->findMilestone($milestoneUuid);
        $this->assertOwnedByResearch($milestone->research_id, $research->id, 'Research milestone not found.');
        $milestone = $this->milestoneRepository->markComplete($milestone);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::RESEARCH_MILESTONE_COMPLETED,
            $request,
            $actor->uuid,
            ['research_uuid' => $milestone->research->uuid, 'milestone_uuid' => $milestone->uuid],
            $actor->displayName().' marked a research milestone as complete: '.$milestone->title.'.',
            ResearchMilestone::class,
            $milestone->uuid,
            ModuleEnums::research_management,
            200,
        );

        return $milestone;
    }

    public function deleteMilestone(string $researchUuid, string $milestoneUuid, Admin $actor, Request $request): void
    {
        $research = $this->findForAdmin($researchUuid);
        $milestone = $this->findMilestone($milestoneUuid);
        $this->assertOwnedByResearch($milestone->research_id, $research->id, 'Research milestone not found.');
        $this->milestoneRepository->delete($milestone);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::RESEARCH_MILESTONE_DELETED,
            $request,
            $actor->uuid,
            ['research_uuid' => $milestone->research->uuid, 'milestone_uuid' => $milestone->uuid],
            $actor->displayName().' deleted a research milestone: '.$milestone->title.'.',
            ResearchMilestone::class,
            $milestone->uuid,
            ModuleEnums::research_management,
            200,
        );
    }

    // Deliverables

    /**
     * @param  array<string, mixed>  $filters
     */
    public function deliverables(string $researchUuid, array $filters): LengthAwarePaginator
    {
        $research = $this->findForAdmin($researchUuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->deliverableRepository->paginateForResearch($research, $filters, $perPage);
    }

    public function findDeliverable(string $uuid): ResearchDeliverable
    {
        $deliverable = $this->deliverableRepository->findByUuid($uuid);
        if (! $deliverable instanceof ResearchDeliverable) {
            throw new ApiException('Research deliverable not found.', 404);
        }

        return $deliverable;
    }

    public function showDeliverable(string $researchUuid, string $deliverableUuid): ResearchDeliverable
    {
        $research = $this->findForAdmin($researchUuid);
        $deliverable = $this->findDeliverable($deliverableUuid);
        $this->assertOwnedByResearch($deliverable->research_id, $research->id, 'Research deliverable not found.');

        return $deliverable;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function addDeliverable(string $researchUuid, array $validated, Admin $actor, Request $request): ResearchDeliverable
    {
        $research = $this->findForAdmin($researchUuid);
        $allTeamMembers = (bool) ($validated['all_team_members'] ?? false);
        $assignees = $this->resolveNotifyRecipientIds($allTeamMembers, $validated['assignee_uuids'] ?? []);
        $notifyAllTeamMembers = (bool) ($validated['notify_all_team_members'] ?? false);
        $notifyRecipients = $this->resolveNotifyRecipientIds($notifyAllTeamMembers, $validated['notify_admin_uuids'] ?? []);
        unset($validated['assignee_uuids'], $validated['notify_admin_uuids']);
        $validated['all_team_members'] = $allTeamMembers;
        $validated['notify_all_team_members'] = $notifyAllTeamMembers;

        if (array_key_exists('milestone_uuid', $validated)) {
            if ($validated['milestone_uuid'] === null) {
                $validated['milestone_id'] = null;
            } else {
                $milestone = $this->findMilestone($validated['milestone_uuid']);
                $this->assertBelongsToResearch($milestone->research_id, $research->id, 'milestone');
                $validated['milestone_id'] = $milestone->id;
            }
            unset($validated['milestone_uuid']);
        }

        $deliverable = $this->deliverableRepository->create($research, $validated, $assignees, $notifyRecipients);
        $this->dispatchDeliverableNotification($research, $deliverable, $notifyRecipients);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::RESEARCH_DELIVERABLE_CREATED,
            $request,
            $actor->uuid,
            ['research_uuid' => $research->uuid, 'deliverable_uuid' => $deliverable->uuid],
            $actor->displayName().' added a deliverable to research: '.$research->title.'.',
            ResearchDeliverable::class,
            $deliverable->uuid,
            ModuleEnums::research_management,
            200,
        );

        return $deliverable;
    }

    /**
     * @param  list<int>  $recipientAdminIds
     */
    private function dispatchDeliverableNotification(Research $research, ResearchDeliverable $deliverable, array $recipientAdminIds): void
    {
        if ($recipientAdminIds === []) {
            return;
        }

        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::research_management->value,
            event: 'research_deliverable_added',
            title: $deliverable->title,
            message: 'A new deliverable was added: '.$deliverable->title.'.',
            meta: ['research_uuid' => $research->uuid, 'deliverable_uuid' => $deliverable->uuid],
        );

        $this->notificationDispatchService->notifyAdminsByUuids(
            Admin::query()->whereIn('id', $recipientAdminIds)->pluck('uuid')->all(),
            $notification,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateDeliverable(string $researchUuid, string $deliverableUuid, array $validated, Admin $actor, Request $request): ResearchDeliverable
    {
        $research = $this->findForAdmin($researchUuid);
        $deliverable = $this->findDeliverable($deliverableUuid);
        $this->assertOwnedByResearch($deliverable->research_id, $research->id, 'Research deliverable not found.');

        $assignees = null;
        if (array_key_exists('all_team_members', $validated) || array_key_exists('assignee_uuids', $validated)) {
            $allTeamMembers = (bool) ($validated['all_team_members'] ?? $deliverable->all_team_members);
            $assignees = $this->resolveNotifyRecipientIds($allTeamMembers, $validated['assignee_uuids'] ?? []);
            $validated['all_team_members'] = $allTeamMembers;
        }
        unset($validated['assignee_uuids']);

        $notifyRecipients = null;
        if (array_key_exists('notify_all_team_members', $validated) || array_key_exists('notify_admin_uuids', $validated)) {
            $notifyAllTeamMembers = (bool) ($validated['notify_all_team_members'] ?? $deliverable->notify_all_team_members);
            $notifyRecipients = $this->resolveNotifyRecipientIds($notifyAllTeamMembers, $validated['notify_admin_uuids'] ?? []);
            $validated['notify_all_team_members'] = $notifyAllTeamMembers;
        }
        unset($validated['notify_admin_uuids']);

        if (array_key_exists('milestone_uuid', $validated)) {
            if ($validated['milestone_uuid'] === null) {
                $validated['milestone_id'] = null;
            } else {
                $milestone = $this->findMilestone($validated['milestone_uuid']);
                $this->assertBelongsToResearch($milestone->research_id, $deliverable->research_id, 'milestone');
                $validated['milestone_id'] = $milestone->id;
            }
            unset($validated['milestone_uuid']);
        }

        $deliverable = $this->deliverableRepository->update($deliverable, $validated, $assignees, $notifyRecipients);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::RESEARCH_DELIVERABLE_UPDATED,
            $request,
            $actor->uuid,
            ['research_uuid' => $deliverable->research->uuid, 'deliverable_uuid' => $deliverable->uuid],
            $actor->displayName().' updated a research deliverable: '.$deliverable->title.'.',
            ResearchDeliverable::class,
            $deliverable->uuid,
            ModuleEnums::research_management,
            200,
        );

        return $deliverable;
    }

    public function completeDeliverable(string $researchUuid, string $deliverableUuid, Admin $actor, Request $request): ResearchDeliverable
    {
        $research = $this->findForAdmin($researchUuid);
        $deliverable = $this->findDeliverable($deliverableUuid);
        $this->assertOwnedByResearch($deliverable->research_id, $research->id, 'Research deliverable not found.');
        $deliverable = $this->deliverableRepository->markComplete($deliverable);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::RESEARCH_DELIVERABLE_COMPLETED,
            $request,
            $actor->uuid,
            ['research_uuid' => $deliverable->research->uuid, 'deliverable_uuid' => $deliverable->uuid],
            $actor->displayName().' marked a research deliverable as complete: '.$deliverable->title.'.',
            ResearchDeliverable::class,
            $deliverable->uuid,
            ModuleEnums::research_management,
            200,
        );

        return $deliverable;
    }

    public function deleteDeliverable(string $researchUuid, string $deliverableUuid, Admin $actor, Request $request): void
    {
        $research = $this->findForAdmin($researchUuid);
        $deliverable = $this->findDeliverable($deliverableUuid);
        $this->assertOwnedByResearch($deliverable->research_id, $research->id, 'Research deliverable not found.');
        $this->deliverableRepository->delete($deliverable);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::RESEARCH_DELIVERABLE_DELETED,
            $request,
            $actor->uuid,
            ['research_uuid' => $deliverable->research->uuid, 'deliverable_uuid' => $deliverable->uuid],
            $actor->displayName().' deleted a research deliverable: '.$deliverable->title.'.',
            ResearchDeliverable::class,
            $deliverable->uuid,
            ModuleEnums::research_management,
            200,
        );
    }
}

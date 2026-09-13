<?php

namespace App\Http\Controllers\v1\Admin\Research;

use App\Enums\ResearchStatusEnum;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Research\CreateResearchDeliverableRequest;
use App\Http\Requests\Admin\Research\CreateResearchMilestoneRequest;
use App\Http\Requests\Admin\Research\CreateResearchObjectiveRequest;
use App\Http\Requests\Admin\Research\CreateResearchRequest;
use App\Http\Requests\Admin\Research\ResearchDeliverableListRequest;
use App\Http\Requests\Admin\Research\ResearchListRequest;
use App\Http\Requests\Admin\Research\ResearchMilestoneListRequest;
use App\Http\Requests\Admin\Research\ResearchObjectiveListRequest;
use App\Http\Requests\Admin\Research\ResearchOverviewRequest;
use App\Http\Requests\Admin\Research\UpdateResearchDeliverableRequest;
use App\Http\Requests\Admin\Research\UpdateResearchMilestoneRequest;
use App\Http\Requests\Admin\Research\UpdateResearchObjectiveRequest;
use App\Http\Requests\Admin\Research\UpdateResearchRequest;
use App\Http\Resources\Admin\Research\ResearchDeliverableResource;
use App\Http\Resources\Admin\Research\ResearchMilestoneResource;
use App\Http\Resources\Admin\Research\ResearchObjectiveResource;
use App\Http\Resources\Admin\Research\ResearchResource;
use App\Models\Admin;
use App\Responser\JsonResponser;
use App\Services\Research\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ResearchController extends Controller
{
    public function __construct(
        private readonly ResearchService $researchService,
    ) {}

    public function metadata()
    {
        try {
            $statuses = array_map(fn (ResearchStatusEnum $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ], ResearchStatusEnum::cases());

            $milestoneStatuses = [
                ['value' => 'in_progress', 'label' => 'In Progress'],
                ['value' => 'overdue', 'label' => 'Overdue'],
                ['value' => 'completed', 'label' => 'Completed'],
            ];

            $deliverableStatuses = [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'overdue', 'label' => 'Overdue'],
                ['value' => 'completed', 'label' => 'Completed'],
            ];

            return JsonResponser::send(false, 'Research metadata retrieved.', [
                'statuses' => $statuses,
                'milestone_statuses' => $milestoneStatuses,
                'deliverable_statuses' => $deliverableStatuses,
            ]);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@metadata');
        }
    }

    public function index(ResearchListRequest $request)
    {
        try {
            $paginator = $this->researchService->paginate($request->validated());

            return JsonResponser::send(false, 'Research retrieved.', $this->paginatedPayload($paginator, ResearchResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@index');
        }
    }

    public function overview(ResearchOverviewRequest $request)
    {
        try {
            $overview = $this->researchService->statusOverview($request->validated());

            return JsonResponser::send(false, 'Research overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@overview');
        }
    }

    public function store(CreateResearchRequest $request)
    {
        try {
            $actor = $this->requireAdmin($request);
            $research = $this->researchService->create($request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Research created.', ResearchResource::make($research)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@store');
        }
    }

    public function show(string $uuid)
    {
        try {
            $research = $this->researchService->findForAdmin($uuid);

            return JsonResponser::send(false, 'Research retrieved.', ResearchResource::make($research)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@show');
        }
    }

    public function update(UpdateResearchRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $research = $this->researchService->update($uuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Research updated.', ResearchResource::make($research)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@update');
        }
    }

    public function complete(Request $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $research = $this->researchService->markComplete($uuid, $actor, $request);

            return JsonResponser::send(false, 'Research marked as complete.', ResearchResource::make($research)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@complete');
        }
    }

    // Objectives

    public function objectives(ResearchObjectiveListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->researchService->objectives($uuid, $request->validated());

            return JsonResponser::send(false, 'Research objectives retrieved.', $this->paginatedPayload($paginator, ResearchObjectiveResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@objectives');
        }
    }

    public function storeObjective(CreateResearchObjectiveRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $objective = $this->researchService->addObjective($uuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Research objective added.', ResearchObjectiveResource::make($objective)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@storeObjective');
        }
    }

    public function updateObjective(UpdateResearchObjectiveRequest $request, string $uuid, string $objectiveUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $objective = $this->researchService->updateObjective($uuid, $objectiveUuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Research objective updated.', ResearchObjectiveResource::make($objective)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@updateObjective');
        }
    }

    // Milestones

    public function milestones(ResearchMilestoneListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->researchService->milestones($uuid, $request->validated());

            return JsonResponser::send(false, 'Research milestones retrieved.', $this->paginatedPayload($paginator, ResearchMilestoneResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@milestones');
        }
    }

    public function storeMilestone(CreateResearchMilestoneRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $milestone = $this->researchService->addMilestone($uuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Research milestone added.', ResearchMilestoneResource::make($milestone)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@storeMilestone');
        }
    }

    public function showMilestone(string $uuid, string $milestoneUuid)
    {
        try {
            $milestone = $this->researchService->showMilestone($uuid, $milestoneUuid);

            return JsonResponser::send(false, 'Research milestone retrieved.', ResearchMilestoneResource::make($milestone)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@showMilestone');
        }
    }

    public function updateMilestone(UpdateResearchMilestoneRequest $request, string $uuid, string $milestoneUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $milestone = $this->researchService->updateMilestone($uuid, $milestoneUuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Research milestone updated.', ResearchMilestoneResource::make($milestone)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@updateMilestone');
        }
    }

    public function completeMilestone(Request $request, string $uuid, string $milestoneUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $milestone = $this->researchService->completeMilestone($uuid, $milestoneUuid, $actor, $request);

            return JsonResponser::send(false, 'Research milestone marked as complete.', ResearchMilestoneResource::make($milestone)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@completeMilestone');
        }
    }

    public function destroyMilestone(Request $request, string $uuid, string $milestoneUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $this->researchService->deleteMilestone($uuid, $milestoneUuid, $actor, $request);

            return JsonResponser::send(false, 'Research milestone deleted.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@destroyMilestone');
        }
    }

    // Deliverables

    public function deliverables(ResearchDeliverableListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->researchService->deliverables($uuid, $request->validated());

            return JsonResponser::send(false, 'Research deliverables retrieved.', $this->paginatedPayload($paginator, ResearchDeliverableResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@deliverables');
        }
    }

    public function storeDeliverable(CreateResearchDeliverableRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $deliverable = $this->researchService->addDeliverable($uuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Research deliverable added.', ResearchDeliverableResource::make($deliverable)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@storeDeliverable');
        }
    }

    public function showDeliverable(string $uuid, string $deliverableUuid)
    {
        try {
            $deliverable = $this->researchService->showDeliverable($uuid, $deliverableUuid);

            return JsonResponser::send(false, 'Research deliverable retrieved.', ResearchDeliverableResource::make($deliverable)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@showDeliverable');
        }
    }

    public function updateDeliverable(UpdateResearchDeliverableRequest $request, string $uuid, string $deliverableUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $deliverable = $this->researchService->updateDeliverable($uuid, $deliverableUuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Research deliverable updated.', ResearchDeliverableResource::make($deliverable)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@updateDeliverable');
        }
    }

    public function completeDeliverable(Request $request, string $uuid, string $deliverableUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $deliverable = $this->researchService->completeDeliverable($uuid, $deliverableUuid, $actor, $request);

            return JsonResponser::send(false, 'Research deliverable marked as complete.', ResearchDeliverableResource::make($deliverable)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@completeDeliverable');
        }
    }

    public function destroyDeliverable(Request $request, string $uuid, string $deliverableUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $this->researchService->deleteDeliverable($uuid, $deliverableUuid, $actor, $request);

            return JsonResponser::send(false, 'Research deliverable deleted.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Research\ResearchController@destroyDeliverable');
        }
    }

    private function paginatedPayload(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        $payload = $paginator->toArray();
        $resource = $resourceClass::collection($paginator);
        $payload['data'] = $resource->resolve();

        return $payload;
    }

    private function requireAdmin(Request $request): Admin
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            abort(403, 'Forbidden.');
        }

        return $admin;
    }
}

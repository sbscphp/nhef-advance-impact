<?php

namespace App\Http\Controllers\v1\Admin\Projects;

use App\Enums\AuditActionEnum;
use App\Enums\ProjectBroadcastDeliveryEnum;
use App\Enums\ProjectCategoryEnum;
use App\Enums\ProjectDocumentCategoryEnum;
use App\Enums\ProjectRiskSeverityEnum;
use App\Enums\ProjectRiskStatusEnum;
use App\Enums\ProjectStatusEnum;
use App\Helpers\GeneralHelper;
use App\Helpers\PDFReportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Http\Requests\Admin\Projects\AddProjectImpactReportRequest;
use App\Http\Requests\Admin\Projects\AddProjectRiskRequest;
use App\Http\Requests\Admin\Projects\CreateProjectBudgetLineRequest;
use App\Http\Requests\Admin\Projects\CreateProjectDeliverableRequest;
use App\Http\Requests\Admin\Projects\CreateProjectMilestoneRequest;
use App\Http\Requests\Admin\Projects\CreateProjectObjectiveRequest;
use App\Http\Requests\Admin\Projects\CreateProjectRequest;
use App\Http\Requests\Admin\Projects\GenerateProjectReportRequest;
use App\Http\Requests\Admin\Projects\ProjectAuditLogListRequest;
use App\Http\Requests\Admin\Projects\ProjectBroadcastListRequest;
use App\Http\Requests\Admin\Projects\ProjectBudgetLineListRequest;
use App\Http\Requests\Admin\Projects\ProjectDeliverableListRequest;
use App\Http\Requests\Admin\Projects\ProjectDocumentListRequest;
use App\Http\Requests\Admin\Projects\ProjectExpenditureListRequest;
use App\Http\Requests\Admin\Projects\ProjectImpactReportListRequest;
use App\Http\Requests\Admin\Projects\ProjectIssueListRequest;
use App\Http\Requests\Admin\Projects\ProjectListRequest;
use App\Http\Requests\Admin\Projects\ProjectMilestoneListRequest;
use App\Http\Requests\Admin\Projects\ProjectObjectiveListRequest;
use App\Http\Requests\Admin\Projects\ProjectOverviewRequest;
use App\Http\Requests\Admin\Projects\ProjectRiskListRequest;
use App\Http\Requests\Admin\Projects\RecordProjectExpenditureRequest;
use App\Http\Requests\Admin\Projects\SendProjectBroadcastRequest;
use App\Http\Requests\Admin\Projects\UpdateProjectBroadcastRequest;
use App\Http\Requests\Admin\Projects\UpdateProjectBudgetLineRequest;
use App\Http\Requests\Admin\Projects\UpdateProjectDeliverableRequest;
use App\Http\Requests\Admin\Projects\UpdateProjectExpenditureRequest;
use App\Http\Requests\Admin\Projects\UpdateProjectImpactReportRequest;
use App\Http\Requests\Admin\Projects\UpdateProjectMilestoneRequest;
use App\Http\Requests\Admin\Projects\UpdateProjectObjectiveRequest;
use App\Http\Requests\Admin\Projects\UpdateProjectRequest;
use App\Http\Requests\Admin\Projects\UpdateProjectRiskRequest;
use App\Http\Requests\Admin\Projects\UploadProjectDocumentRequest;
use App\Http\Resources\Admin\AuditLogResource;
use App\Http\Resources\Admin\Projects\ProjectBroadcastResource;
use App\Http\Resources\Admin\Projects\ProjectBudgetLineResource;
use App\Http\Resources\Admin\Projects\ProjectDeliverableResource;
use App\Http\Resources\Admin\Projects\ProjectDocumentResource;
use App\Http\Resources\Admin\Projects\ProjectExpenditureResource;
use App\Http\Resources\Admin\Projects\ProjectImpactReportResource;
use App\Http\Resources\Admin\Projects\ProjectMilestoneResource;
use App\Http\Resources\Admin\Projects\ProjectObjectiveResource;
use App\Http\Resources\Admin\Projects\ProjectResource;
use App\Http\Resources\Admin\Projects\ProjectRiskResource;
use App\Models\Admin;
use App\Models\ProjectBudgetLine;
use App\Models\ProjectExpenditure;
use App\Responser\JsonResponser;
use App\Services\Audit\AuditTrailQueryService;
use App\Services\Project\ProjectService;
use App\Support\ListingQuery;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly AuditTrailQueryService $auditTrailQuery,
        private readonly PDFReportHelper $pdfReportHelper,
    ) {}

    public function metadata()
    {
        try {
            $statuses = array_map(fn (ProjectStatusEnum $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ], ProjectStatusEnum::cases());

            $categories = array_map(fn (ProjectCategoryEnum $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
            ], ProjectCategoryEnum::cases());

            $documentCategories = array_map(fn (ProjectDocumentCategoryEnum $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
            ], ProjectDocumentCategoryEnum::cases());

            $broadcastDeliveryOptions = array_map(fn (ProjectBroadcastDeliveryEnum $option): array => [
                'value' => $option->value,
                'label' => $option->label(),
            ], ProjectBroadcastDeliveryEnum::cases());

            $riskSeverities = array_map(fn (ProjectRiskSeverityEnum $severity): array => [
                'value' => $severity->value,
                'label' => $severity->label(),
            ], ProjectRiskSeverityEnum::cases());

            $riskStatuses = array_map(fn (ProjectRiskStatusEnum $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ], ProjectRiskStatusEnum::cases());

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

            $auditActions = array_values(array_map(
                fn (AuditActionEnum $action): array => [
                    'value' => $action->value,
                    'label' => ucfirst(strtolower(str_replace('_', ' ', $action->value))),
                ],
                array_filter(AuditActionEnum::cases(), fn (AuditActionEnum $action): bool => str_starts_with($action->value, 'PROJECT_'))
            ));

            return JsonResponser::send(false, 'Project metadata retrieved.', [
                'statuses' => $statuses,
                'categories' => $categories,
                'document_categories' => $documentCategories,
                'broadcast_delivery_options' => $broadcastDeliveryOptions,
                'risk_severities' => $riskSeverities,
                'risk_statuses' => $riskStatuses,
                'milestone_statuses' => $milestoneStatuses,
                'deliverable_statuses' => $deliverableStatuses,
                'audit_actions' => $auditActions,
                'periods' => ListingFilterRules::periodOptions(),
            ]);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@metadata');
        }
    }

    public function index(ProjectListRequest $request)
    {
        try {
            $paginator = $this->projectService->paginate($request->validated());

            return JsonResponser::send(false, 'Projects retrieved.', $this->paginatedPayload($paginator, ProjectResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@index');
        }
    }

    public function overview(ProjectOverviewRequest $request)
    {
        try {
            $filters = $request->validated();
            $overview = [
                'status' => $this->projectService->statusOverview($filters),
                'budget' => $this->projectService->budgetOverview($filters),
            ];

            return JsonResponser::send(false, 'Project overview retrieved.', $overview);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@overview');
        }
    }

    public function issues(ProjectIssueListRequest $request)
    {
        try {
            $paginator = $this->projectService->issuesOverview($request->validated());

            return JsonResponser::send(false, 'Project issues retrieved.', $paginator->toArray());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@issues');
        }
    }

    public function store(CreateProjectRequest $request)
    {
        try {
            $actor = $this->requireAdmin($request);
            $project = $this->projectService->create($request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Project created.', ProjectResource::make($project)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@store');
        }
    }

    public function show(string $uuid)
    {
        try {
            $project = $this->projectService->findForAdmin($uuid);
            $project->setAttribute('budget_overview', $this->projectService->projectBudgetOverview($project));

            return JsonResponser::send(false, 'Project retrieved.', ProjectResource::make($project)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@show');
        }
    }

    public function update(UpdateProjectRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $project = $this->projectService->update($uuid, $request->validated(), $actor, $request);
            $project->load([
                'creator', 'teamMembers', 'fundingDonors', 'fundingCampaigns',
                'objectives.assignments.admin', 'milestones.assignments.admin',
                'deliverables.milestone', 'deliverables.assignments.admin',
            ]);

            return JsonResponser::send(false, 'Project updated.', ProjectResource::make($project)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@update');
        }
    }

    public function activate(Request $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $project = $this->projectService->activate($uuid, $actor, $request);

            return JsonResponser::send(false, 'Project activated.', ProjectResource::make($project)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@activate');
        }
    }

    public function putOnHold(Request $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $project = $this->projectService->putOnHold($uuid, $actor, $request);

            return JsonResponser::send(false, 'Project put on hold.', ProjectResource::make($project)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@putOnHold');
        }
    }

    public function archive(Request $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $project = $this->projectService->archive($uuid, $actor, $request);

            return JsonResponser::send(false, 'Project archived.', ProjectResource::make($project)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@archive');
        }
    }

    // Objectives

    public function objectives(ProjectObjectiveListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->objectives($uuid, $request->validated());

            return JsonResponser::send(false, 'Project objectives retrieved.', $this->paginatedPayload($paginator, ProjectObjectiveResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@objectives');
        }
    }

    public function storeObjective(CreateProjectObjectiveRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $objective = $this->projectService->addObjective($uuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Project objective added.', ProjectObjectiveResource::make($objective)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@storeObjective');
        }
    }

    public function updateObjective(UpdateProjectObjectiveRequest $request, string $uuid, string $objectiveUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $objective = $this->projectService->updateObjective($uuid, $objectiveUuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Project objective updated.', ProjectObjectiveResource::make($objective)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@updateObjective');
        }
    }

    // Milestones

    public function milestones(ProjectMilestoneListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->milestones($uuid, $request->validated());

            return JsonResponser::send(false, 'Project milestones retrieved.', $this->paginatedPayload($paginator, ProjectMilestoneResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@milestones');
        }
    }

    public function storeMilestone(CreateProjectMilestoneRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $milestone = $this->projectService->addMilestone($uuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Project milestone added.', ProjectMilestoneResource::make($milestone)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@storeMilestone');
        }
    }

    public function showMilestone(string $uuid, string $milestoneUuid)
    {
        try {
            $milestone = $this->projectService->showMilestone($uuid, $milestoneUuid);

            return JsonResponser::send(false, 'Project milestone retrieved.', ProjectMilestoneResource::make($milestone)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showMilestone');
        }
    }

    public function updateMilestone(UpdateProjectMilestoneRequest $request, string $uuid, string $milestoneUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $milestone = $this->projectService->updateMilestone($uuid, $milestoneUuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Project milestone updated.', ProjectMilestoneResource::make($milestone)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@updateMilestone');
        }
    }

    public function completeMilestone(Request $request, string $uuid, string $milestoneUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $milestone = $this->projectService->completeMilestone($uuid, $milestoneUuid, $actor, $request);

            return JsonResponser::send(false, 'Project milestone marked as complete.', ProjectMilestoneResource::make($milestone)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@completeMilestone');
        }
    }

    public function destroyMilestone(Request $request, string $uuid, string $milestoneUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $this->projectService->deleteMilestone($uuid, $milestoneUuid, $actor, $request);

            return JsonResponser::send(false, 'Project milestone deleted.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@destroyMilestone');
        }
    }

    // Deliverables

    public function deliverables(ProjectDeliverableListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->deliverables($uuid, $request->validated());

            return JsonResponser::send(false, 'Project deliverables retrieved.', $this->paginatedPayload($paginator, ProjectDeliverableResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@deliverables');
        }
    }

    public function storeDeliverable(CreateProjectDeliverableRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $deliverable = $this->projectService->addDeliverable($uuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Project deliverable added.', ProjectDeliverableResource::make($deliverable)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@storeDeliverable');
        }
    }

    public function showDeliverable(string $uuid, string $deliverableUuid)
    {
        try {
            $deliverable = $this->projectService->showDeliverable($uuid, $deliverableUuid);

            return JsonResponser::send(false, 'Project deliverable retrieved.', ProjectDeliverableResource::make($deliverable)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showDeliverable');
        }
    }

    public function updateDeliverable(UpdateProjectDeliverableRequest $request, string $uuid, string $deliverableUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $deliverable = $this->projectService->updateDeliverable($uuid, $deliverableUuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Project deliverable updated.', ProjectDeliverableResource::make($deliverable)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@updateDeliverable');
        }
    }

    public function completeDeliverable(Request $request, string $uuid, string $deliverableUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $deliverable = $this->projectService->completeDeliverable($uuid, $deliverableUuid, $actor, $request);

            return JsonResponser::send(false, 'Project deliverable marked as complete.', ProjectDeliverableResource::make($deliverable)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@completeDeliverable');
        }
    }

    public function destroyDeliverable(Request $request, string $uuid, string $deliverableUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $this->projectService->deleteDeliverable($uuid, $deliverableUuid, $actor, $request);

            return JsonResponser::send(false, 'Project deliverable deleted.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@destroyDeliverable');
        }
    }

    // Budget lines

    public function budgetOverview(string $uuid)
    {
        try {
            return JsonResponser::send(false, 'Project budget overview retrieved.', $this->projectService->budgetOverviewForProject($uuid));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@budgetOverview');
        }
    }

    public function budgetLines(ProjectBudgetLineListRequest $request, string $uuid)
    {
        try {
            $validated = $request->validated();
            $export = $validated['export'] ?? null;
            unset($validated['export']);

            return match ($export) {
                'csv' => $this->respondBudgetLinesCsv($uuid, $validated),
                'pdf' => $this->respondBudgetLinesPdf($uuid, $validated),
                default => JsonResponser::send(false, 'Project budget lines retrieved.', $this->paginatedPayload($this->projectService->budgetLines($uuid, $validated), ProjectBudgetLineResource::class)),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@budgetLines');
        }
    }

    public function storeBudgetLine(CreateProjectBudgetLineRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $budgetLine = $this->projectService->addBudgetLine($uuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Project budget line added.', ProjectBudgetLineResource::make($budgetLine)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@storeBudgetLine');
        }
    }

    public function showBudgetLine(string $uuid, string $budgetLineUuid)
    {
        try {
            $budgetLine = $this->projectService->showBudgetLine($uuid, $budgetLineUuid);

            return JsonResponser::send(false, 'Project budget line retrieved.', ProjectBudgetLineResource::make($budgetLine)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showBudgetLine');
        }
    }

    public function updateBudgetLine(UpdateProjectBudgetLineRequest $request, string $uuid, string $budgetLineUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $budgetLine = $this->projectService->updateBudgetLine($uuid, $budgetLineUuid, $request->validated(), $actor, $request);
            $summary = $this->projectService->budgetLineSummary($budgetLine);
            $budgetLine->setAttribute('amount_utilized', $summary['amount_utilized']);
            $budgetLine->setAttribute('remaining_amount', $summary['remaining_amount']);
            $budgetLine->setAttribute('percent_remaining', $summary['percent_remaining']);

            return JsonResponser::send(false, 'Project budget line updated.', ProjectBudgetLineResource::make($budgetLine)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@updateBudgetLine');
        }
    }

    // Expenditures

    public function expenditures(ProjectExpenditureListRequest $request, string $uuid)
    {
        try {
            $validated = $request->validated();
            $export = $validated['export'] ?? null;
            unset($validated['export']);

            return match ($export) {
                'csv' => $this->respondExpendituresCsv($uuid, $validated),
                'pdf' => $this->respondExpendituresPdf($uuid, $validated),
                default => JsonResponser::send(false, 'Project expenditures retrieved.', $this->paginatedPayload($this->projectService->expenditures($uuid, $validated), ProjectExpenditureResource::class)),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@expenditures');
        }
    }

    public function storeExpenditure(RecordProjectExpenditureRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $expenditure = $this->projectService->recordExpenditure($uuid, $request->validated(), $actor, $request);
            $expenditure->load('budgetLine');

            return JsonResponser::send(false, 'Project expenditure recorded.', ProjectExpenditureResource::make($expenditure)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@storeExpenditure');
        }
    }

    public function showExpenditure(string $uuid, string $expenditureUuid)
    {
        try {
            $expenditure = $this->projectService->showExpenditure($uuid, $expenditureUuid);

            return JsonResponser::send(false, 'Project expenditure retrieved.', ProjectExpenditureResource::make($expenditure)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showExpenditure');
        }
    }

    public function updateExpenditure(UpdateProjectExpenditureRequest $request, string $uuid, string $expenditureUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $expenditure = $this->projectService->updateExpenditure($uuid, $expenditureUuid, $request->validated(), $actor, $request);
            $expenditure->load('budgetLine');

            return JsonResponser::send(false, 'Project expenditure updated.', ProjectExpenditureResource::make($expenditure)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@updateExpenditure');
        }
    }

    public function destroyExpenditure(Request $request, string $uuid, string $expenditureUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $this->projectService->deleteExpenditure($uuid, $expenditureUuid, $actor, $request);

            return JsonResponser::send(false, 'Project expenditure removed.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@destroyExpenditure');
        }
    }

    // Impact reports

    public function impactReports(ProjectImpactReportListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->impactReports($uuid, $request->validated());

            return JsonResponser::send(false, 'Project impact reports retrieved.', $this->paginatedPayload($paginator, ProjectImpactReportResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@impactReports');
        }
    }

    public function storeImpactReport(AddProjectImpactReportRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $report = $this->projectService->addImpactReport($uuid, $request->validated(), $actor, $request);
            $report->load(['deliverable', 'budgetLine', 'creator']);

            return JsonResponser::send(false, 'Project impact report added.', ProjectImpactReportResource::make($report)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@storeImpactReport');
        }
    }

    public function showImpactReport(string $uuid, string $reportUuid)
    {
        try {
            $report = $this->projectService->showImpactReport($uuid, $reportUuid);

            return JsonResponser::send(false, 'Project impact report retrieved.', ProjectImpactReportResource::make($report)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showImpactReport');
        }
    }

    public function updateImpactReport(UpdateProjectImpactReportRequest $request, string $uuid, string $reportUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $report = $this->projectService->updateImpactReport($uuid, $reportUuid, $request->validated(), $actor, $request);
            $report->load(['deliverable', 'budgetLine', 'creator']);

            return JsonResponser::send(false, 'Project impact report updated.', ProjectImpactReportResource::make($report)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@updateImpactReport');
        }
    }

    public function destroyImpactReport(Request $request, string $uuid, string $reportUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $this->projectService->deleteImpactReport($uuid, $reportUuid, $actor, $request);

            return JsonResponser::send(false, 'Project impact report deleted.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@destroyImpactReport');
        }
    }

    // Documents

    public function documents(ProjectDocumentListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->documents($uuid, $request->validated());

            return JsonResponser::send(false, 'Project documents retrieved.', $this->paginatedPayload($paginator, ProjectDocumentResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@documents');
        }
    }

    public function storeDocument(UploadProjectDocumentRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $documents = $this->projectService->uploadDocument($uuid, $request->validated(), $actor, $request);
            $documents->load('creator');

            return JsonResponser::send(false, 'Project documents uploaded.', ProjectDocumentResource::collection($documents)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@storeDocument');
        }
    }

    public function destroyDocument(Request $request, string $uuid, string $documentUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $this->projectService->deleteDocument($uuid, $documentUuid, $actor, $request);

            return JsonResponser::send(false, 'Project document deleted.', null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@destroyDocument');
        }
    }

    // Communication (Broadcasts)

    public function broadcasts(ProjectBroadcastListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->broadcasts($uuid, $request->validated());

            return JsonResponser::send(false, 'Project broadcasts retrieved.', $this->paginatedPayload($paginator, ProjectBroadcastResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@broadcasts');
        }
    }

    public function storeBroadcast(SendProjectBroadcastRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $broadcast = $this->projectService->sendBroadcast($uuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Broadcast sent.', ProjectBroadcastResource::make($broadcast)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@storeBroadcast');
        }
    }

    public function showBroadcast(string $uuid, string $broadcastUuid)
    {
        try {
            $broadcast = $this->projectService->showBroadcast($uuid, $broadcastUuid);

            return JsonResponser::send(false, 'Project broadcast retrieved.', ProjectBroadcastResource::make($broadcast)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showBroadcast');
        }
    }

    public function updateBroadcast(UpdateProjectBroadcastRequest $request, string $uuid, string $broadcastUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $broadcast = $this->projectService->updateBroadcast($uuid, $broadcastUuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Broadcast updated.', ProjectBroadcastResource::make($broadcast)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@updateBroadcast');
        }
    }

    // Risks

    public function risks(ProjectRiskListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->risks($uuid, $request->validated());

            return JsonResponser::send(false, 'Project risks retrieved.', $this->paginatedPayload($paginator, ProjectRiskResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@risks');
        }
    }

    public function showRisk(string $uuid, string $riskUuid)
    {
        try {
            $risk = $this->projectService->showRisk($uuid, $riskUuid);

            return JsonResponser::send(false, 'Project risk retrieved.', ProjectRiskResource::make($risk)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showRisk');
        }
    }

    public function storeRisk(AddProjectRiskRequest $request, string $uuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $risk = $this->projectService->addRisk($uuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Risk raised.', ProjectRiskResource::make($risk)->resolve(), 201);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@storeRisk');
        }
    }

    public function updateRisk(UpdateProjectRiskRequest $request, string $uuid, string $riskUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $risk = $this->projectService->updateRisk($uuid, $riskUuid, $request->validated(), $actor, $request);

            return JsonResponser::send(false, 'Risk updated.', ProjectRiskResource::make($risk)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@updateRisk');
        }
    }

    public function resolveRisk(Request $request, string $uuid, string $riskUuid)
    {
        try {
            $actor = $this->requireAdmin($request);
            $risk = $this->projectService->resolveRisk($uuid, $riskUuid, $actor, $request);

            return JsonResponser::send(false, 'Risk marked as resolved.', ProjectRiskResource::make($risk)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@resolveRisk');
        }
    }

    // Report

    public function generateReport(GenerateProjectReportRequest $request, string $uuid)
    {
        try {
            $validated = $request->validated();

            return $this->projectService->generateSummaryReport($uuid, $validated['start_date'] ?? null, $validated['end_date'] ?? null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@generateReport');
        }
    }

    public function upcomingDeadlines(Request $request, string $uuid)
    {
        try {
            $limit = max(1, min((int) $request->query('limit', 10), 50));

            return JsonResponser::send(false, 'Upcoming deadlines retrieved.', $this->projectService->upcomingDeadlines($uuid, $limit));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@upcomingDeadlines');
        }
    }

    // Audit log

    public function auditLog(ProjectAuditLogListRequest $request, string $uuid)
    {
        try {
            $this->projectService->findForAdmin($uuid);

            $validated = $request->validated();
            $validated['filters']['project_uuid'] = $uuid;
            $listing = ListingQuery::fromValidated($validated);

            $paginator = $this->auditTrailQuery
                ->queryForListing($listing)
                ->paginate(perPage: $listing->perPage, page: $listing->page);

            $payload = $paginator->toArray();
            $payload['data'] = AuditLogResource::collection($paginator)->resolve();

            return JsonResponser::send(false, 'Project audit log retrieved.', $payload);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@auditLog');
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function respondBudgetLinesCsv(string $uuid, array $filters): StreamedResponse
    {
        [$rows, , $currency] = $this->projectService->exportBudgetLines($uuid, $filters);

        $filename = 'budget-lines-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows, $currency): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Title', 'Reference ID', 'Amount Allocated', 'Amount Utilized', 'Remaining Amount', '% Remaining', 'Starts At', 'Due At', 'Created At']);

            /** @var ProjectBudgetLine $budgetLine */
            foreach ($rows as $budgetLine) {
                fputcsv($out, [
                    $budgetLine->title,
                    $budgetLine->reference_id,
                    Money::format($budgetLine->amount_allocated, $currency),
                    Money::format($budgetLine->amount_utilized, $currency),
                    Money::format($budgetLine->remaining_amount, $currency),
                    $budgetLine->percent_remaining.'%',
                    $budgetLine->starts_at?->toDateString() ?? '',
                    $budgetLine->due_at?->toDateString() ?? '',
                    $budgetLine->created_at?->toIso8601String() ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function respondBudgetLinesPdf(string $uuid, array $filters)
    {
        [$rows, $truncated, $currency] = $this->projectService->exportBudgetLines($uuid, $filters);

        $tableRows = $rows->values()->map(fn (ProjectBudgetLine $budgetLine): array => [
            $budgetLine->title,
            (string) ($budgetLine->reference_id ?? ''),
            Money::format($budgetLine->amount_allocated, $currency),
            Money::format($budgetLine->amount_utilized, $currency),
            Money::format($budgetLine->remaining_amount, $currency),
            $budgetLine->percent_remaining.'%',
        ]);

        return $this->pdfReportHelper->download(
            rows: $tableRows,
            headings: ['Title', 'Reference ID', 'Amount Allocated', 'Amount Utilized', 'Remaining Amount', '% Remaining'],
            title: 'Budget lines',
            filename: 'budget-lines-'.now()->format('Y-m-d-His').'.pdf',
            orientation: 'portrait',
            generatedAt: now((string) config('app.timezone')),
            truncated: $truncated,
            includedRows: $tableRows->count(),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function respondExpendituresCsv(string $uuid, array $filters): StreamedResponse
    {
        [$rows, , $currency] = $this->projectService->exportExpenditures($uuid, $filters);

        $filename = 'expenditures-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows, $currency): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Title', 'Budget Line', 'Amount', 'Transaction Date', 'Reference ID', 'Created At']);

            /** @var ProjectExpenditure $expenditure */
            foreach ($rows as $expenditure) {
                fputcsv($out, [
                    $expenditure->title,
                    $expenditure->budgetLine?->title ?? '',
                    Money::format($expenditure->amount, $currency),
                    $expenditure->transaction_date?->toDateString() ?? '',
                    $expenditure->reference_id,
                    $expenditure->created_at?->toIso8601String() ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function respondExpendituresPdf(string $uuid, array $filters)
    {
        [$rows, $truncated, $currency] = $this->projectService->exportExpenditures($uuid, $filters);

        $tableRows = $rows->values()->map(fn (ProjectExpenditure $expenditure): array => [
            $expenditure->title,
            $expenditure->budgetLine?->title ?? '',
            Money::format($expenditure->amount, $currency),
            $expenditure->transaction_date?->toDateString() ?? '',
        ]);

        return $this->pdfReportHelper->download(
            rows: $tableRows,
            headings: ['Title', 'Budget Line', 'Amount', 'Transaction Date'],
            title: 'Expenditures',
            filename: 'expenditures-'.now()->format('Y-m-d-His').'.pdf',
            orientation: 'portrait',
            generatedAt: now((string) config('app.timezone')),
            truncated: $truncated,
            includedRows: $tableRows->count(),
        );
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
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

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
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Subgroup;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('Admin Projects')]
#[Authenticated]
class ProjectController extends Controller
{
    private const EXAMPLE_PROJECT_UUID = '57254ecc-34db-4179-a902-3c45fb711dd4';

    private const EXAMPLE_MILESTONE_UUID = '3974b0ef-785f-4183-ac9b-d49e08c388c1';

    private const EXAMPLE_DELIVERABLE_UUID = 'b713fe2e-220b-49b7-993b-4d7993a09731';

    private const EXAMPLE_BUDGET_LINE_UUID = '2fa55601-3a5f-4e9d-999a-fc646e2ee171';

    private const EXAMPLE_EXPENDITURE_UUID = 'a282966d-4eef-4fc6-8e4b-abb2102d6bad';

    private const EXAMPLE_IMPACT_REPORT_UUID = 'a2221fb6-784e-4b09-9b5d-a1243b973ae8';

    private const EXAMPLE_DOCUMENT_UUID = '9e60ac32-44ee-404e-a47d-f8952ea7ce82';

    private const EXAMPLE_OBJECTIVE_UUID = '681cc9bc-3b36-40df-828c-4ebde625fbf5';

    private const EXAMPLE_BROADCAST_UUID = 'd41d8cd9-8f00-3204-a980-0998ecf8427e';

    private const EXAMPLE_RISK_UUID = '098f6bcd-4621-3373-8ade-4e832627b4f6';

    public function __construct(
        private readonly ProjectService $projectService,
        private readonly AuditTrailQueryService $auditTrailQuery,
        private readonly PDFReportHelper $pdfReportHelper,
    ) {}

    #[Subgroup('Projects')]
    #[Endpoint('Get project metadata', 'Valid project statuses, project categories, and document categories, for populating dropdowns.')]
    #[Response(content: [
        'error' => false,
        'message' => 'Project metadata retrieved.',
        'data' => [
            'statuses' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'on_hold', 'label' => 'On Hold'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'archived', 'label' => 'Archived'],
            ],
            'categories' => [
                ['value' => 'finance', 'label' => 'Finance'],
                ['value' => 'research', 'label' => 'Research'],
            ],
            'document_categories' => [
                ['value' => 'finance', 'label' => 'Finance'],
                ['value' => 'general_knowledge', 'label' => 'General Knowledge'],
            ],
            'broadcast_delivery_options' => [
                ['value' => 'email', 'label' => 'Email'],
                ['value' => 'push_notification', 'label' => 'Push Notification'],
            ],
            'risk_severities' => [
                ['value' => 'low', 'label' => 'Low Risk'],
                ['value' => 'medium', 'label' => 'Medium Risk'],
                ['value' => 'high', 'label' => 'High Risk'],
            ],
            'risk_statuses' => [
                ['value' => 'open', 'label' => 'Open'],
                ['value' => 'closed', 'label' => 'Closed'],
            ],
        ],
    ], status: 200)]
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

    #[Subgroup('Projects')]
    #[Endpoint('List projects', 'Paginated, filterable by status/category, searchable by title.')]
    #[QueryParam('search', 'string', 'Search by title.', required: false, example: 'Literacy')]
    #[QueryParam('period', 'string', 'One of the standard period presets (e.g. "30days", "quarter", "custom"). Cannot be combined with start_date/end_date.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Required when period=custom.', required: false, example: '2026-06-30')]
    #[QueryParam('sort_by', 'string', 'One of: name, value.', required: false, example: 'name')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('filters[status]', 'string', 'One of the project status values.', required: false, example: 'active')]
    #[QueryParam('filters[category]', 'string', 'One of the project category values.', required: false, example: 'education')]
    #[Response(content: [
        'error' => false,
        'message' => 'Projects retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'total' => 0],
    ], status: 200)]
    public function index(ProjectListRequest $request)
    {
        try {
            $paginator = $this->projectService->paginate($request->validated());

            return JsonResponser::send(false, 'Projects retrieved.', $this->paginatedPayload($paginator, ProjectResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@index');
        }
    }

    #[Subgroup('Projects')]
    #[Endpoint('Get project overview', 'Org-wide status counts and budget snapshot across every project. "at_risk" is the count of distinct projects with at least one open (unresolved) risk raised against them; see the /projects/issues endpoint for the underlying list. Optionally scoped to a period/date range: status counts and approved_budget/funding_received are scoped by project creation date, "allocated" by budget line creation date, "utilized" by expenditure transaction date, and "at_risk" by when the risk was raised. Omit period/dates for all-time totals.')]
    #[QueryParam('period', 'string', 'One of the standard period presets (e.g. "30days", "quarter", "custom").', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Required when period=custom.', required: false, example: '2026-06-30')]
    #[Response(content: [
        'error' => false,
        'message' => 'Project overview retrieved.',
        'data' => [
            'status' => ['all' => 30, 'draft' => 2, 'active' => 8, 'on_hold' => 12, 'completed' => 22, 'archived' => 0, 'at_risk' => 8],
            'budget' => ['approved_budget' => '10000000.00', 'funding_received' => '0.00', 'allocated' => '902000000.00', 'utilized' => '702000000.00', 'remaining' => '200000000.00'],
        ],
    ], status: 200)]
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

    #[Subgroup('Projects')]
    #[Endpoint('List open issues across every project', '"Issue: Action Required" panel: every open (unresolved) risk across all projects, most recent first, with the raising project\'s name and manager attached. This surfaces risks raised via the per-project Risk tab; it is not a separate automated anomaly detector.')]
    #[QueryParam('search', 'string', 'Search by risk title.', required: false, example: 'Funding')]
    #[QueryParam('period', 'string', 'One of the standard period presets (e.g. "30days", "quarter", "custom"). Cannot be combined with start_date/end_date.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Required when period=custom.', required: false, example: '2026-06-30')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Rows per page (max 100). Defaults to 10.', required: false, example: 10)]
    #[QueryParam('filters[severity]', 'string', 'One of: low, medium, high.', required: false, example: 'high')]
    #[Response(content: [
        'error' => false,
        'message' => 'Project issues retrieved.',
        'data' => [
            'current_page' => 1,
            'data' => [
                [
                    'project_uuid' => '57254ecc-34db-4179-a902-3c45fb711dd4',
                    'project_name' => 'Research Grant 2026',
                    'issue_type' => 'Funding is been delayed by the SBSC UK Capital LLC',
                    'project_manager' => 'Kunle Dairo',
                    'severity' => 'high',
                    'severity_label' => 'High Risk',
                    'raised_by' => 'Kunle Dairo',
                    'created_at' => '2026-06-01T09:00:00+00:00',
                ],
            ],
            'total' => 1,
        ],
    ], status: 200)]
    public function issues(ProjectIssueListRequest $request)
    {
        try {
            $paginator = $this->projectService->issuesOverview($request->validated());

            return JsonResponser::send(false, 'Project issues retrieved.', $paginator->toArray());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@issues');
        }
    }

    #[Subgroup('Projects')]
    #[Endpoint('Create a project', 'Created in draft status; activate it separately once ready.')]
    #[Response(content: [
        'error' => false,
        'message' => 'Project created.',
        'data' => [
            'uuid' => '57254ecc-34db-4179-a902-3c45fb711dd4',
            'title' => 'HTTP Smoke Test Project 2',
            'status' => 'draft',
            'status_label' => 'Draft',
            'approved_budget' => '5000000.00',
            'approved_budget_formatted' => 'NGN 5,000,000.00',
            'currency' => 'NGN',
        ],
    ], status: 201)]
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

    #[Subgroup('Projects')]
    #[Endpoint('Get a project', 'Full detail including manager, team members, and budget totals.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
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

    #[Subgroup('Projects')]
    #[Endpoint('Update a project', 'Partial update; only send the fields that changed.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
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

    #[Subgroup('Projects')]
    #[Endpoint('Activate a project', 'Moves a draft project to active, or resumes one that was on hold.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
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

    #[Subgroup('Projects')]
    #[Endpoint('Put a project on hold', 'Only an active project can be put on hold; all data is preserved and it can be activated again.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
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

    #[Subgroup('Projects')]
    #[Endpoint('Archive a project', 'Removes it from active workflows while retaining all records for reference; not reversible via this endpoint.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
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

    #[Subgroup('Objectives')]
    #[Endpoint('List project objectives', 'Paginated, filterable by completion status, searchable by title.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[QueryParam('search', 'string', 'Search by title.', required: false, example: 'training')]
    #[QueryParam('period', 'string', 'One of the standard period presets (e.g. "30days", "quarter", "custom"). Cannot be combined with start_date/end_date.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Required when period=custom.', required: false, example: '2026-06-30')]
    #[QueryParam('sort_by', 'string', 'Only: name.', required: false, example: 'name')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('filters[is_completed]', 'boolean', 'Filter by completion status.', required: false, example: 1)]
    public function objectives(ProjectObjectiveListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->objectives($uuid, $request->validated());

            return JsonResponser::send(false, 'Project objectives retrieved.', $this->paginatedPayload($paginator, ProjectObjectiveResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@objectives');
        }
    }

    #[Subgroup('Objectives')]
    #[Endpoint('Add a project objective')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[Response(content: [
        'error' => false,
        'message' => 'Project objective added.',
        'data' => [
            'uuid' => '681cc9bc-3b36-40df-828c-4ebde625fbf5',
            'title' => "Women's Economic Empowerment",
            'is_completed' => false,
            'assignees' => [['uuid' => 'a5ef115f-3dd3-4748-93de-3315a4c9a127', 'name' => 'Super Admin']],
        ],
    ], status: 201)]
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

    #[Subgroup('Objectives')]
    #[Endpoint('Update a project objective', "Also toggles 'is_completed' when included in the body.")]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('objectiveUuid', 'string', 'The objective UUID.', example: self::EXAMPLE_OBJECTIVE_UUID)]
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

    #[Subgroup('Milestones')]
    #[Endpoint('List project milestones', "Status ('completed', 'in_progress', 'overdue') is derived from due date, not stored.")]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[QueryParam('search', 'string', 'Search by title.', required: false, example: 'Phase 1')]
    #[QueryParam('period', 'string', 'One of the standard period presets (e.g. "30days", "quarter", "custom"). Cannot be combined with start_date/end_date.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Required when period=custom.', required: false, example: '2026-06-30')]
    #[QueryParam('sort_by', 'string', 'Only: name.', required: false, example: 'name')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('filters[status]', 'string', 'One of: in_progress, overdue, completed.', required: false, example: 'overdue')]
    public function milestones(ProjectMilestoneListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->milestones($uuid, $request->validated());

            return JsonResponser::send(false, 'Project milestones retrieved.', $this->paginatedPayload($paginator, ProjectMilestoneResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@milestones');
        }
    }

    #[Subgroup('Milestones')]
    #[Endpoint('Add a project milestone')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[Response(content: [
        'error' => false,
        'message' => 'Project milestone added.',
        'data' => [
            'uuid' => '3974b0ef-785f-4183-ac9b-d49e08c388c1',
            'title' => 'Phase 1: Kickoff',
            'due_at' => '2026-12-01',
            'is_completed' => false,
            'status' => 'in_progress',
            'assignees' => [],
        ],
    ], status: 201)]
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

    #[Subgroup('Milestones')]
    #[Endpoint('Get a single project milestone')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('milestoneUuid', 'string', 'The milestone UUID.', example: self::EXAMPLE_MILESTONE_UUID)]
    public function showMilestone(string $uuid, string $milestoneUuid)
    {
        try {
            $milestone = $this->projectService->showMilestone($uuid, $milestoneUuid);

            return JsonResponser::send(false, 'Project milestone retrieved.', ProjectMilestoneResource::make($milestone)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showMilestone');
        }
    }

    #[Subgroup('Milestones')]
    #[Endpoint('Update a project milestone')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('milestoneUuid', 'string', 'The milestone UUID.', example: self::EXAMPLE_MILESTONE_UUID)]
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

    #[Subgroup('Milestones')]
    #[Endpoint('Mark a project milestone complete')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('milestoneUuid', 'string', 'The milestone UUID.', example: self::EXAMPLE_MILESTONE_UUID)]
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

    #[Subgroup('Milestones')]
    #[Endpoint('Delete a project milestone')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('milestoneUuid', 'string', 'The milestone UUID.', example: self::EXAMPLE_MILESTONE_UUID)]
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

    #[Subgroup('Deliverables')]
    #[Endpoint('List project deliverables', "Status ('completed', 'pending', 'overdue') is derived from due date, not stored. Filterable by milestone_uuid.")]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[QueryParam('search', 'string', 'Search by title.', required: false, example: 'Training Manual')]
    #[QueryParam('period', 'string', 'One of the standard period presets (e.g. "30days", "quarter", "custom"). Cannot be combined with start_date/end_date.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Required when period=custom.', required: false, example: '2026-06-30')]
    #[QueryParam('sort_by', 'string', 'Only: name.', required: false, example: 'name')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('filters[milestone_uuid]', 'string', 'Only deliverables linked to this milestone.', required: false, example: self::EXAMPLE_MILESTONE_UUID)]
    #[QueryParam('filters[status]', 'string', 'One of: pending, overdue, completed.', required: false, example: 'overdue')]
    public function deliverables(ProjectDeliverableListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->deliverables($uuid, $request->validated());

            return JsonResponser::send(false, 'Project deliverables retrieved.', $this->paginatedPayload($paginator, ProjectDeliverableResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@deliverables');
        }
    }

    #[Subgroup('Deliverables')]
    #[Endpoint('Add a project deliverable', 'milestone_uuid is optional; a deliverable can stand alone.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[Response(content: [
        'error' => false,
        'message' => 'Project deliverable added.',
        'data' => [
            'uuid' => 'b713fe2e-220b-49b7-993b-4d7993a09731',
            'title' => 'Create a Training Manual',
            'due_at' => '2026-12-04',
            'is_completed' => false,
            'status' => 'pending',
            'milestone' => ['uuid' => '3974b0ef-785f-4183-ac9b-d49e08c388c1', 'title' => 'Phase 1: Kickoff'],
            'assignees' => [],
        ],
    ], status: 201)]
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

    #[Subgroup('Deliverables')]
    #[Endpoint('Get a single project deliverable')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('deliverableUuid', 'string', 'The deliverable UUID.', example: self::EXAMPLE_DELIVERABLE_UUID)]
    public function showDeliverable(string $uuid, string $deliverableUuid)
    {
        try {
            $deliverable = $this->projectService->showDeliverable($uuid, $deliverableUuid);

            return JsonResponser::send(false, 'Project deliverable retrieved.', ProjectDeliverableResource::make($deliverable)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showDeliverable');
        }
    }

    #[Subgroup('Deliverables')]
    #[Endpoint('Update a project deliverable')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('deliverableUuid', 'string', 'The deliverable UUID.', example: self::EXAMPLE_DELIVERABLE_UUID)]
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

    #[Subgroup('Deliverables')]
    #[Endpoint('Mark a project deliverable complete')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('deliverableUuid', 'string', 'The deliverable UUID.', example: self::EXAMPLE_DELIVERABLE_UUID)]
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

    #[Subgroup('Deliverables')]
    #[Endpoint('Delete a project deliverable')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('deliverableUuid', 'string', 'The deliverable UUID.', example: self::EXAMPLE_DELIVERABLE_UUID)]
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

    #[Subgroup('Budget Lines')]
    #[Endpoint('Get a single project\'s budget overview', 'Approved Budget, Funding Received, Total Utilized, and Remaining Budget cards for the "Budget & Funding" tab. percent_utilized/percent_remaining and is_over_budget are computed from current totals; there is no "vs last 30 days" trend field since no historical snapshot is kept.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[Response(content: [
        'error' => false,
        'message' => 'Project budget overview retrieved.',
        'data' => [
            'approved_budget' => '60392319.00',
            'approved_budget_formatted' => 'NGN 60,392,319.00',
            'funding_received' => '42930893.33',
            'funding_received_formatted' => 'NGN 42,930,893.33',
            'total_utilized' => '32089777.00',
            'total_utilized_formatted' => 'NGN 32,089,777.00',
            'remaining_budget' => '9299839.44',
            'remaining_budget_formatted' => 'NGN 9,299,839.44',
            'percent_utilized' => 72.0,
            'percent_remaining' => 18.0,
            'is_over_budget' => false,
        ],
    ], status: 200)]
    public function budgetOverview(string $uuid)
    {
        try {
            return JsonResponser::send(false, 'Project budget overview retrieved.', $this->projectService->budgetOverviewForProject($uuid));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@budgetOverview');
        }
    }

    #[Subgroup('Budget Lines')]
    #[Endpoint('List project budget lines', 'amount_utilized, remaining_amount, and percent_remaining are computed live from recorded expenditures.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[QueryParam('search', 'string', 'Search by title.', required: false, example: 'Personnel')]
    #[QueryParam('period', 'string', 'One of the standard period presets (e.g. "30days", "quarter", "custom"). Cannot be combined with start_date/end_date.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Required when period=custom.', required: false, example: '2026-06-30')]
    #[QueryParam('sort_by', 'string', 'One of: name, value.', required: false, example: 'name')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'asc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('export', 'string', 'Set to "csv" or "pdf" to download instead of a paginated JSON list.', required: false, example: 'csv')]
    #[Response(content: [
        'error' => false,
        'message' => 'Project budget lines retrieved.',
        'data' => [[
            'uuid' => '2fa55601-3a5f-4e9d-999a-fc646e2ee171',
            'title' => 'Personnel Budget',
            'amount_allocated' => '1000000.00',
            'amount_utilized' => '250000.00',
            'remaining_amount' => '750000.00',
            'percent_remaining' => 75.0,
        ]],
    ], status: 200)]
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

    #[Subgroup('Budget Lines')]
    #[Endpoint('Add a project budget line')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
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

    #[Subgroup('Budget Lines')]
    #[Endpoint('Get a single project budget line')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('budgetLineUuid', 'string', 'The budget line UUID.', example: self::EXAMPLE_BUDGET_LINE_UUID)]
    public function showBudgetLine(string $uuid, string $budgetLineUuid)
    {
        try {
            $budgetLine = $this->projectService->showBudgetLine($uuid, $budgetLineUuid);

            return JsonResponser::send(false, 'Project budget line retrieved.', ProjectBudgetLineResource::make($budgetLine)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showBudgetLine');
        }
    }

    #[Subgroup('Budget Lines')]
    #[Endpoint('Update a project budget line')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('budgetLineUuid', 'string', 'The budget line UUID.', example: self::EXAMPLE_BUDGET_LINE_UUID)]
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

    #[Subgroup('Expenditures')]
    #[Endpoint('List project expenditures', 'Filterable by budget_line_uuid.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[QueryParam('search', 'string', 'Search by title.', required: false, example: 'Consulting')]
    #[QueryParam('period', 'string', 'One of the standard period presets (e.g. "30days", "quarter", "custom"). Cannot be combined with start_date/end_date.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Required when period=custom.', required: false, example: '2026-06-30')]
    #[QueryParam('sort_by', 'string', 'One of: name, value.', required: false, example: 'value')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('filters[budget_line_uuid]', 'string', 'Only expenditures against this budget line.', required: false, example: self::EXAMPLE_BUDGET_LINE_UUID)]
    #[QueryParam('export', 'string', 'Set to "csv" or "pdf" to download instead of a paginated JSON list.', required: false, example: 'csv')]
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

    #[Subgroup('Expenditures')]
    #[Endpoint('Record a project expenditure', 'evidence_url accepts a multipart file upload, an existing http(s) URL, or a base64/data-URI string; an uploaded file is stored on Cloudinary and the resulting URL is what gets saved.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[BodyParam('evidence_url', 'file', 'Receipt/evidence file. A URL or base64 string also works for non-Postman clients, but Postman can only build a file picker here.', required: false)]
    #[Response(content: [
        'error' => false,
        'message' => 'Project expenditure recorded.',
        'data' => [
            'uuid' => 'a282966d-4eef-4fc6-8e4b-abb2102d6bad',
            'title' => 'Consulting fee',
            'amount' => '250000.00',
            'amount_formatted' => 'NGN 250,000.00',
            'transaction_date' => '2026-09-10',
            'reference_id' => 'VEHN7BSSPE',
            'budget_line' => ['uuid' => '2fa55601-3a5f-4e9d-999a-fc646e2ee171', 'title' => 'Personnel Budget'],
        ],
    ], status: 201)]
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

    #[Subgroup('Expenditures')]
    #[Endpoint('Get a single project expenditure', 'For the "View" link on each row of the Recent Expenses table.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('expenditureUuid', 'string', 'The expenditure UUID.', example: self::EXAMPLE_EXPENDITURE_UUID)]
    public function showExpenditure(string $uuid, string $expenditureUuid)
    {
        try {
            $expenditure = $this->projectService->showExpenditure($uuid, $expenditureUuid);

            return JsonResponser::send(false, 'Project expenditure retrieved.', ProjectExpenditureResource::make($expenditure)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showExpenditure');
        }
    }

    #[Subgroup('Expenditures')]
    #[Endpoint('Update a project expenditure', 'evidence_url accepts a multipart file upload, an existing http(s) URL, or a base64/data-URI string; an uploaded file is stored on Cloudinary and the resulting URL is what gets saved. Leaving it blank keeps the existing evidence unchanged.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('expenditureUuid', 'string', 'The expenditure UUID.', example: self::EXAMPLE_EXPENDITURE_UUID)]
    #[BodyParam('evidence_url', 'file', 'Receipt/evidence file. A URL or base64 string also works for non-Postman clients, but Postman can only build a file picker here.', required: false)]
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

    #[Subgroup('Expenditures')]
    #[Endpoint('Remove a project expenditure')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('expenditureUuid', 'string', 'The expenditure UUID.', example: self::EXAMPLE_EXPENDITURE_UUID)]
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

    #[Subgroup('Impact Reports')]
    #[Endpoint('List project impact reports', 'Filterable by deliverable_uuid and budget_line_uuid.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[QueryParam('search', 'string', 'Search by title.', required: false, example: 'June 2026')]
    #[QueryParam('period', 'string', 'One of the standard period presets (e.g. "30days", "quarter", "custom"). Cannot be combined with start_date/end_date.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Required when period=custom.', required: false, example: '2026-06-30')]
    #[QueryParam('sort_by', 'string', 'Only: name.', required: false, example: 'name')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('filters[deliverable_uuid]', 'string', 'Only reports linked to this deliverable.', required: false, example: self::EXAMPLE_DELIVERABLE_UUID)]
    #[QueryParam('filters[budget_line_uuid]', 'string', 'Only reports linked to this budget line.', required: false, example: self::EXAMPLE_BUDGET_LINE_UUID)]
    public function impactReports(ProjectImpactReportListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->impactReports($uuid, $request->validated());

            return JsonResponser::send(false, 'Project impact reports retrieved.', $this->paginatedPayload($paginator, ProjectImpactReportResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@impactReports');
        }
    }

    #[Subgroup('Impact Reports')]
    #[Endpoint('Add a project impact report', 'deliverable_uuid and budget_line_uuid are both optional. Each entry in evidence_urls accepts a multipart file upload, an existing http(s) URL, or a base64/data-URI string; uploaded files are stored on Cloudinary. Up to 3 evidence files per report.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[BodyParam('evidence_urls', 'file[]', 'Up to 3 supporting evidence files. A URL or base64 string also works per entry for non-Postman clients, but Postman can only build a file picker here.', required: false)]
    #[Response(content: [
        'error' => false,
        'message' => 'Project impact report added.',
        'data' => [
            'uuid' => 'a2221fb6-784e-4b09-9b5d-a1243b973ae8',
            'title' => 'Project Impact Report as at June 2026',
            'report_date' => '2026-06-12',
            'description' => 'Phase 2 training progressing well.',
            'deliverable' => ['uuid' => 'b713fe2e-220b-49b7-993b-4d7993a09731', 'title' => 'Create a Training Manual'],
            'budget_line' => ['uuid' => '2fa55601-3a5f-4e9d-999a-fc646e2ee171', 'title' => 'Personnel Budget'],
        ],
    ], status: 201)]
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

    #[Subgroup('Impact Reports')]
    #[Endpoint('Get a single project impact report')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('reportUuid', 'string', 'The impact report UUID.', example: self::EXAMPLE_IMPACT_REPORT_UUID)]
    public function showImpactReport(string $uuid, string $reportUuid)
    {
        try {
            $report = $this->projectService->showImpactReport($uuid, $reportUuid);

            return JsonResponser::send(false, 'Project impact report retrieved.', ProjectImpactReportResource::make($report)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showImpactReport');
        }
    }

    #[Subgroup('Impact Reports')]
    #[Endpoint('Update a project impact report', 'Each entry in evidence_urls accepts a multipart file upload, an existing http(s) URL, or a base64/data-URI string; uploaded files are stored on Cloudinary. Sending evidence_urls replaces the full evidence list; omit it to leave existing evidence untouched.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('reportUuid', 'string', 'The impact report UUID.', example: self::EXAMPLE_IMPACT_REPORT_UUID)]
    #[BodyParam('evidence_urls', 'file[]', 'Up to 3 files. Replaces every existing evidence file. A URL or base64 string also works per entry for non-Postman clients.', required: false)]
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

    #[Subgroup('Impact Reports')]
    #[Endpoint('Delete a project impact report')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('reportUuid', 'string', 'The impact report UUID.', example: self::EXAMPLE_IMPACT_REPORT_UUID)]
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

    #[Subgroup('Documents')]
    #[Endpoint('List project documents', 'Filterable by category.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[QueryParam('search', 'string', 'Search by document name.', required: false, example: 'Budget')]
    #[QueryParam('period', 'string', 'One of the standard period presets (e.g. "30days", "quarter", "custom"). Cannot be combined with start_date/end_date.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Required when period=custom.', required: false, example: '2026-06-30')]
    #[QueryParam('sort_by', 'string', 'Only: name.', required: false, example: 'name')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('filters[category]', 'string', 'One of the project document category values.', required: false, example: 'finance')]
    public function documents(ProjectDocumentListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->documents($uuid, $request->validated());

            return JsonResponser::send(false, 'Project documents retrieved.', $this->paginatedPayload($paginator, ProjectDocumentResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@documents');
        }
    }

    #[Subgroup('Documents')]
    #[Endpoint('Upload project documents', 'Each entry in file_urls accepts a multipart file upload, an existing http(s) URL, or a base64/data-URI string; uploaded files are stored on Cloudinary. Each file becomes its own separate document record (up to 3 per request), named after its own filename; there is no name input.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[BodyParam('file_urls', 'file[]', 'Up to 3 files. A URL or base64 string also works per entry for non-Postman clients, but Postman can only build a file picker here.', required: true)]
    #[Response(content: [
        'error' => false,
        'message' => 'Project documents uploaded.',
        'data' => [
            [
                'uuid' => '9e60ac32-44ee-404e-a47d-f8952ea7ce82',
                'name' => 'Budget Allocation Sheet',
                'category' => 'finance',
                'category_label' => 'Finance',
                'file_url' => 'https://example.com/budget-allocation-sheet.pdf',
            ],
        ],
    ], status: 201)]
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

    #[Subgroup('Documents')]
    #[Endpoint('Delete a project document')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('documentUuid', 'string', 'The document UUID.', example: self::EXAMPLE_DOCUMENT_UUID)]
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

    #[Subgroup('Communication')]
    #[Endpoint('List project broadcasts', 'Every broadcast sent on the project, most recent first. Filterable by delivery_via.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[QueryParam('search', 'string', 'Search by message title.', required: false, example: 'Impact Report')]
    #[QueryParam('period', 'string', 'One of the standard period presets (e.g. "30days", "quarter", "custom"). Cannot be combined with start_date/end_date.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Required when period=custom.', required: false, example: '2026-06-30')]
    #[QueryParam('sort_by', 'string', 'Only: title.', required: false, example: 'title')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('filters[delivery_via]', 'string', 'One of: email, push_notification.', required: false, example: 'email')]
    public function broadcasts(ProjectBroadcastListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->broadcasts($uuid, $request->validated());

            return JsonResponser::send(false, 'Project broadcasts retrieved.', $this->paginatedPayload($paginator, ProjectBroadcastResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@broadcasts');
        }
    }

    #[Subgroup('Communication')]
    #[Endpoint('Send a project broadcast', 'Delivers a database (in-app) notification to every resolved recipient; also sends mail when delivery_via is "email". When all_team_members is true (the default), the broadcast targets every current project team member and recipient_admin_uuids is ignored. Each entry in attachment_urls accepts a multipart file upload, an existing http(s) URL, or a base64/data-URI string; uploaded files are stored on Cloudinary.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[BodyParam('attachment_urls', 'file[]', 'Up to 3 files. A URL or base64 string also works per entry for non-Postman clients, but Postman can only build a file picker here.', required: false)]
    #[Response(content: [
        'error' => false,
        'message' => 'Broadcast sent.',
        'data' => [
            'uuid' => 'd41d8cd9-8f00-3204-a980-0998ecf8427e',
            'title' => 'Impact Report Update',
            'send_date' => '2026-06-20',
            'delivery_via' => 'email',
            'delivery_via_label' => 'Email',
            'message' => 'Kindly find attach our lovely supporter this email regarding the new impact support.',
            'all_team_members' => true,
            'attachment_urls' => null,
            'number_of_reach' => 4,
        ],
    ], status: 201)]
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

    #[Subgroup('Communication')]
    #[Endpoint('Get a single project broadcast')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('broadcastUuid', 'string', 'The broadcast UUID.', example: self::EXAMPLE_BROADCAST_UUID)]
    public function showBroadcast(string $uuid, string $broadcastUuid)
    {
        try {
            $broadcast = $this->projectService->showBroadcast($uuid, $broadcastUuid);

            return JsonResponser::send(false, 'Project broadcast retrieved.', ProjectBroadcastResource::make($broadcast)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showBroadcast');
        }
    }

    #[Subgroup('Communication')]
    #[Endpoint('Update a project broadcast', 'Record-only update; does not re-trigger delivery. Each entry in attachment_urls accepts a multipart file upload, an existing http(s) URL, or a base64/data-URI string; sending attachment_urls replaces the full attachment list.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('broadcastUuid', 'string', 'The broadcast UUID.', example: self::EXAMPLE_BROADCAST_UUID)]
    #[BodyParam('attachment_urls', 'file[]', 'Up to 3 files. Replaces every existing attachment. A URL or base64 string also works per entry for non-Postman clients.', required: false)]
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

    #[Subgroup('Risks')]
    #[Endpoint('List project risks', 'Filterable by severity/status/milestone_uuid, searchable by title.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[QueryParam('search', 'string', 'Search by title.', required: false, example: 'Funding')]
    #[QueryParam('period', 'string', 'One of the standard period presets (e.g. "30days", "quarter", "custom"). Cannot be combined with start_date/end_date.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Required when period=custom.', required: false, example: '2026-06-30')]
    #[QueryParam('sort_by', 'string', 'Only: title.', required: false, example: 'title')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Rows per page (max 100).', required: false, example: 15)]
    #[QueryParam('filters[severity]', 'string', 'One of: low, medium, high.', required: false, example: 'high')]
    #[QueryParam('filters[status]', 'string', 'One of: open, closed.', required: false, example: 'open')]
    #[QueryParam('filters[milestone_uuid]', 'string', 'Only risks linked to this milestone.', required: false, example: self::EXAMPLE_MILESTONE_UUID)]
    public function risks(ProjectRiskListRequest $request, string $uuid)
    {
        try {
            $paginator = $this->projectService->risks($uuid, $request->validated());

            return JsonResponser::send(false, 'Project risks retrieved.', $this->paginatedPayload($paginator, ProjectRiskResource::class));
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@risks');
        }
    }

    #[Subgroup('Risks')]
    #[Endpoint('Get a single project risk')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('riskUuid', 'string', 'The risk UUID.', example: self::EXAMPLE_RISK_UUID)]
    #[Response(content: [
        'error' => false,
        'message' => 'Project risk retrieved.',
        'data' => [
            'uuid' => '098f6bcd-4621-3373-8ade-4e832627b4f6',
            'title' => 'Funding is been delayed by the SBSC UK Capital LLC',
            'description' => 'We need to collect our money.',
            'severity' => 'high',
            'severity_label' => 'High Risk',
            'status' => 'open',
            'status_label' => 'Open',
            'milestone' => ['uuid' => '3974b0ef-785f-4183-ac9b-d49e08c388c1', 'title' => 'Phase 1: Curriculum Development & Venue Setup'],
            'all_team_members' => false,
            'recipients' => [['uuid' => 'fb61aa22-86d9-47f3-a963-812a5b3de069', 'name' => 'Admin']],
            'raised_by' => ['uuid' => 'a5ef115f-3dd3-4748-93de-3315a4c9a127', 'name' => 'Super Admin'],
            'resolved_by' => null,
            'resolved_at' => null,
            'created_at' => '2026-09-12T11:51:04+00:00',
        ],
    ], status: 200)]
    public function showRisk(string $uuid, string $riskUuid)
    {
        try {
            $risk = $this->projectService->showRisk($uuid, $riskUuid);

            return JsonResponser::send(false, 'Project risk retrieved.', ProjectRiskResource::make($risk)->resolve());
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@showRisk');
        }
    }

    #[Subgroup('Risks')]
    #[Endpoint('Raise a project risk', 'Dispatches a database (in-app) notification to every resolved recipient. When all_team_members is true (the default), the risk notifies every current project team member and recipient_admin_uuids is ignored.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[Response(content: [
        'error' => false,
        'message' => 'Risk raised.',
        'data' => [
            'uuid' => '098f6bcd-4621-3373-8ade-4e832627b4f6',
            'title' => 'Funding is been delayed by the SBSC UK Capital LLC',
            'description' => 'We need to collect our money.',
            'severity' => 'high',
            'severity_label' => 'High Risk',
            'status' => 'open',
            'status_label' => 'Open',
            'milestone' => null,
            'all_team_members' => true,
            'recipients' => [],
            'raised_by' => ['uuid' => 'a5ef115f-3dd3-4748-93de-3315a4c9a127', 'name' => 'Super Admin'],
            'resolved_by' => null,
            'resolved_at' => null,
            'created_at' => '2026-09-12T11:51:04+00:00',
        ],
    ], status: 201)]
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

    #[Subgroup('Risks')]
    #[Endpoint('Update a project risk', 'Record-only update; does not re-trigger notification and does not change status. Use the resolve endpoint to close a risk.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('riskUuid', 'string', 'The risk UUID.', example: self::EXAMPLE_RISK_UUID)]
    #[Response(content: [
        'error' => false,
        'message' => 'Risk updated.',
        'data' => [
            'uuid' => '098f6bcd-4621-3373-8ade-4e832627b4f6',
            'title' => 'Funding is been delayed by the SBSC UK Capital LLC',
            'description' => 'We need to collect our money.',
            'severity' => 'high',
            'severity_label' => 'High Risk',
            'status' => 'open',
            'status_label' => 'Open',
            'milestone' => ['uuid' => '3974b0ef-785f-4183-ac9b-d49e08c388c1', 'title' => 'Phase 1: Curriculum Development & Venue Setup'],
            'all_team_members' => false,
            'recipients' => [['uuid' => 'fb61aa22-86d9-47f3-a963-812a5b3de069', 'name' => 'Admin']],
            'raised_by' => ['uuid' => 'a5ef115f-3dd3-4748-93de-3315a4c9a127', 'name' => 'Super Admin'],
            'resolved_by' => null,
            'resolved_at' => null,
            'created_at' => '2026-09-12T11:51:04+00:00',
        ],
    ], status: 200)]
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

    #[Subgroup('Risks')]
    #[Endpoint('Mark a project risk as resolved')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[UrlParam('riskUuid', 'string', 'The risk UUID.', example: self::EXAMPLE_RISK_UUID)]
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

    #[Subgroup('Projects')]
    #[Endpoint('Generate a project summary report', 'Downloads a portrait PDF covering the project overview, budget, team, objectives, and every milestone/deliverable/expenditure/risk falling within the given date range. Omit both dates for an all-time report.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[QueryParam('start_date', 'string', 'Restricts milestones/deliverables/expenditures/risks to this date onward. Optional; must be given together with end_date.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Restricts milestones/deliverables/expenditures/risks up to this date. Optional; must be given together with start_date.', required: false, example: '2026-06-30')]
    public function generateReport(GenerateProjectReportRequest $request, string $uuid)
    {
        try {
            $validated = $request->validated();

            return $this->projectService->generateSummaryReport($uuid, $validated['start_date'] ?? null, $validated['end_date'] ?? null);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\Projects\ProjectController@generateReport');
        }
    }

    #[Subgroup('Projects')]
    #[Endpoint('Get upcoming deadlines', 'Not-yet-completed milestones and deliverables for the project, soonest due first. "active" means due within the next 7 days; anything further out is "upcoming".')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[QueryParam('limit', 'int', 'Max rows per list. Defaults to 10.', required: false, example: 10)]
    #[Response(content: [
        'error' => false,
        'message' => 'Upcoming deadlines retrieved.',
        'data' => [
            'milestones' => [
                ['uuid' => '3974b0ef-785f-4183-ac9b-d49e08c388c1', 'title' => 'Milestone One', 'due_at' => '2026-06-08', 'days_until_due' => -6, 'status' => 'overdue', 'due_label' => 'Overdue by 6 days'],
                ['uuid' => 'b713fe2e-220b-49b7-993b-4d7993a09731', 'title' => 'Milestone Five', 'due_at' => '2026-06-12', 'days_until_due' => 4, 'status' => 'active', 'due_label' => 'Due in 4 days'],
            ],
            'deliverables' => [
                ['uuid' => 'b713fe2e-220b-49b7-993b-4d7993a09731', 'title' => 'Deliverables One', 'due_at' => '2026-06-08', 'days_until_due' => -6, 'status' => 'overdue', 'due_label' => 'Overdue by 6 days', 'milestone' => ['uuid' => '3974b0ef-785f-4183-ac9b-d49e08c388c1', 'title' => 'Milestone One']],
            ],
        ],
    ], status: 200)]
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

    #[Subgroup('Projects')]
    #[Endpoint('List a project\'s audit log', 'Entries are drawn from the general admin audit trail, scoped to this project (its own record changes plus every objective/milestone/deliverable/budget line/expenditure/impact report/document/broadcast/risk activity logged against it). Use per_page=5 or 6 for a "Recent Activities" widget, or a larger per_page for the full Audit log tab.')]
    #[UrlParam('uuid', 'string', 'The project UUID.', example: self::EXAMPLE_PROJECT_UUID)]
    #[QueryParam('search', 'string', 'Search by description, actor name/email, action, or model.', required: false, example: 'milestone')]
    #[QueryParam('period', 'string', 'One of the standard period presets (e.g. "30days", "quarter", "custom"). Cannot be combined with start_date/end_date.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Required when period=custom.', required: false, example: '2026-01-01')]
    #[QueryParam('end_date', 'string', 'Required when period=custom.', required: false, example: '2026-06-30')]
    #[QueryParam('sort_by', 'string', 'One of: id, uuid, created_at, action, action_module, user_type, http_status.', required: false, example: 'created_at')]
    #[QueryParam('sort_direction', 'string', 'asc or desc.', required: false, example: 'desc')]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Rows per page (max 100).', required: false, example: 6)]
    #[QueryParam('filters[action]', 'string', 'One of the project-related audit action values (see the metadata endpoint\'s audit_actions list).', required: false, example: 'PROJECT_MILESTONE_CREATED')]
    #[Response(content: [
        'error' => false,
        'message' => 'Project audit log retrieved.',
        'data' => ['current_page' => 1, 'data' => [], 'total' => 0],
    ], status: 200)]
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

<?php

namespace App\Http\Controllers\v1\Admin\AuditTrail;

use App\Enums\UserTypeEnum;
use App\Helpers\GeneralHelper;
use App\Helpers\PDFReportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditTrail\AuditTrailListingRequest;
use App\Http\Resources\Admin\AuditLogResource;
use App\Models\AuditLog;
use App\Responser\JsonResponser;
use App\Services\Audit\AuditTrailQueryService;
use App\Support\ListingQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Admin / Audit Trail', 'Search, filter, and export the immutable log of admin/customer actions. Requires the `audit_trail.read` permission.')]
class AuditTrailController extends Controller
{
    public function __construct(
        private readonly AuditTrailQueryService $auditTrailQuery,
        private readonly PDFReportHelper $pdfReportHelper,
    ) {}

    #[Endpoint('List / search / export audit logs', 'Returns paginated JSON by default. Set export=csv or export=pdf to instead stream a file download of the same filtered result set (capped at 5000 rows; the CSV response carries an X-Export-Truncated header when the real total exceeds that).')]
    #[Authenticated]
    #[QueryParam('search', 'string', 'Matches description, UUID, IP, user agent, action, module, model, model ID, actor email/name/phone.', required: false, example: 'campaign')]
    #[QueryParam('period', 'string', 'Filters by date. One of: 1day, 3days, 7days, 14days, 30days, 3months, 6months, 1year, lastyear, custom.', required: false, example: '30days')]
    #[QueryParam('start_date', 'string', 'Start date; required when period=custom.', required: false, example: '2026-06-01')]
    #[QueryParam('end_date', 'string', 'End date; required when period=custom.', required: false, example: '2026-06-30')]
    #[QueryParam('sort_by', 'string', 'One of: id, uuid, created_at, action, action_module, user_type, http_status.', required: false, example: 'created_at')]
    #[QueryParam('sort_direction', 'string', 'asc or desc (default).', required: false, example: 'desc')]
    #[QueryParam('export', 'string', 'csv or pdf to download instead of a JSON page.', required: false, example: 'csv')]
    #[QueryParam('filters[user_type]', 'string', 'One of: ADMIN, CUSTOMER.', required: false, example: 'ADMIN')]
    #[QueryParam('filters[action_module]', 'string', 'One of: dashboard, alumni, constituent_management, user_management, audit_trail, settings, authentication, fundraising, donation, communications, crm, events, reporting, mentorship, networking, custom_field, system_configuration.', required: false, example: 'fundraising')]
    #[QueryParam('filters[action]', 'string', 'One of the AuditActionEnum values, e.g. CAMPAIGN_CREATED, LOGIN_SUCCESS, ADMIN_DELETED.', required: false, example: 'CAMPAIGN_CREATED')]
    #[QueryParam('filters[model]', 'string', 'Matches the affected model class name.', required: false, example: 'Campaign')]
    #[QueryParam('filters[http_status]', 'int', 'Exact HTTP status code of the originating request (100-599).', required: false, example: 200)]
    #[QueryParam('page', 'int', 'Page number.', required: false, example: 1)]
    #[QueryParam('per_page', 'int', 'Results per page (max 100).', required: false, example: 15)]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Audit logs retrieved.',
        'data' => [
            'current_page' => 1,
            'data' => [
                [
                    'uuid' => 'b2c3d4e5-f6a7-48b9-90c1-d2e3f4a5b6c7',
                    'user_type' => 'ADMIN',
                    'action_module' => 'fundraising',
                    'action' => 'CAMPAIGN_CREATED',
                    'description' => 'Adeola Craig created a new Campaign.',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0',
                    'http_outcome' => 'success',
                    'created_at' => '2026-06-16T05:20:00+00:00',
                    'admin' => ['uuid' => 'a1b2c3d4-e5f6-4789-a0b1-c2d3e4f5a6b7', 'name' => 'Adeola Craig', 'email' => 'adeola@nhef.org'],
                ],
            ],
            'per_page' => 15,
            'total' => 1,
        ],
    ], description: 'JSON mode only (export not set); each row groups naturally by created_at date on the client if a "grouped by day" view is needed - the API itself returns a flat, sorted list.')]
    public function index(AuditTrailListingRequest $request)
    {
        try {
            $listing = ListingQuery::fromValidated($request->validated());
            $export = $request->validated('export');

            return match ($export) {
                'csv' => $this->respondCsv($listing),
                'pdf' => $this->respondPdf($listing),
                default => $this->respondPaginated($listing),
            };
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Admin\AuditTrail\AuditTrailController@index');
        }
    }

    private function respondPaginated(ListingQuery $listing)
    {
        $paginator = $this->auditTrailQuery
            ->queryForListing($listing)
            ->paginate(perPage: $listing->perPage, page: $listing->page);

        return JsonResponser::send(
            false,
            'Audit logs retrieved.',
            $this->paginatedPayload($paginator)
        );
    }

    private function respondCsv(ListingQuery $listing): StreamedResponse
    {
        /** @var Collection<int, AuditLog> $collection */
        [$collection, $truncated] = $this->auditTrailQuery->exportCollection($listing);

        $filename = 'audit-trail-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($collection): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID',
                'Created at',
                'User type',
                'Actor email',
                'Actor name',
                'Action module',
                'Action',
                'Model',
                'Model ID',
                'Description',
                'IP address',
                'HTTP status',
            ]);

            $rowNumber = 0;
            foreach ($collection as $log) {
                $rowNumber++;
                fputcsv($out, $this->tabularRow($log, $rowNumber));
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Truncated' => $truncated ? '1' : '0',
        ]);
    }

    private function respondPdf(ListingQuery $listing)
    {
        /** @var Collection<int, AuditLog> $collection */
        [$collection, $truncated] = $this->auditTrailQuery->exportCollection($listing);

        $filename = 'audit-trail-'.now()->format('Y-m-d-His').'.pdf';

        $periodStart = $listing->startDate?->toDateString() ?? 'All dates';
        $periodEnd = $listing->endDate?->toDateString() ?? 'All dates';

        $headings = [
            'ID',
            'Created',
            'User type',
            'Actor',
            'Module',
            'Action',
            'Model',
            'Description',
            'IP',
            'HTTP',
        ];

        $rows = $collection->values()->map(fn (AuditLog $log, int $index): array => [
            (string) ($index + 1),
            $log->created_at?->format('Y-m-d H:i') ?? '',
            $log->user_type->value,
            $this->actorSummary($log),
            $log->action_module->value,
            $log->action->value,
            $log->model !== null ? class_basename((string) $log->model) : '',
            $this->truncatePdfCell((string) ($log->description ?? '')),
            (string) ($log->ip_address ?? ''),
            $log->http_status !== null ? (string) $log->http_status : '',
        ]);

        return $this->pdfReportHelper->download(
            rows: $rows,
            headings: $headings,
            title: 'Audit trail',
            filename: $filename,
            orientation: 'landscape',
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            generatedAt: now((string) config('app.timezone')),
            truncated: $truncated,
            includedRows: $rows->count(),
        );
    }

    /**
     * @return list<int|string|null>
     */
    private function tabularRow(AuditLog $log, int $rowNumber): array
    {
        [$email, $name] = $this->actorEmailAndName($log);

        return [
            $rowNumber,
            $log->created_at?->toIso8601String() ?? '',
            $log->user_type->value,
            $email,
            $name,
            $log->action_module->value,
            $log->action->value,
            $log->model ?? '',
            $log->model_id ?? '',
            $log->description ?? '',
            $log->ip_address ?? '',
            $log->http_status,
        ];
    }

    private function actorSummary(AuditLog $log): string
    {
        [$email, $name] = $this->actorEmailAndName($log);

        if ($email !== '' && $name !== '') {
            return $name.' <'.$email.'>';
        }

        return $email !== '' ? $email : $name;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function actorEmailAndName(AuditLog $log): array
    {
        if ($log->user_type === UserTypeEnum::CUSTOMER && $log->relationLoaded('customerUser') && $log->customerUser !== null) {
            $user = $log->customerUser;
            $name = trim(implode(' ', array_filter([$user->firstname ?? '', $user->lastname ?? ''])));

            return [$user->email ?? '', $name];
        }

        if ($log->user_type === UserTypeEnum::ADMIN && $log->relationLoaded('adminUser') && $log->adminUser !== null) {
            $admin = $log->adminUser;

            return [$admin->email ?? '', $admin->name ?? ''];
        }

        return ['', ''];
    }

    private function truncatePdfCell(string $text, int $max = 120): string
    {
        if (strlen($text) <= $max) {
            return $text;
        }

        return substr($text, 0, $max).'…';
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedPayload(LengthAwarePaginator $paginator): array
    {
        $payload = $paginator->toArray();
        $payload['data'] = AuditLogResource::collection($paginator)->resolve();

        return $payload;
    }
}

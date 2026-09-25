<?php

namespace App\Http\Controllers\v1\Admin\Concerns;

use App\Http\Resources\Admin\AuditLogResource;
use App\Models\AuditLog;
use App\Responser\JsonResponser;
use App\Services\Audit\AuditTrailQueryService;
use App\Support\ListingQuery;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** The day-grouped audit feed shared by the Audit Log screen and the dashboard's Live Activity card. */
trait RespondsWithAuditTimeline
{
    private function auditTimelineResponse(AuditTrailQueryService $query, ListingQuery $listing, string $message)
    {
        [$paginator, $dayCounts] = $query->timelinePage($listing);

        $days = $paginator->getCollection()
            ->groupBy(fn (AuditLog $log): string => $log->created_at->toDateString())
            ->map(fn (Collection $logs, string $day): array => [
                'date' => $day,
                'label' => Carbon::parse($day)->format('F j, Y'),
                'events_count' => $dayCounts[$day] ?? $logs->count(),
                'events' => AuditLogResource::collection($logs)->resolve(),
            ])
            ->values()
            ->all();

        $payload = $paginator->toArray();
        $payload['data'] = $days;

        return JsonResponser::send(false, $message, $payload);
    }
}

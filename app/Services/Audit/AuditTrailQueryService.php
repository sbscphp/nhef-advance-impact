<?php

namespace App\Services\Audit;

use App\Enums\AuditActionEnum;
use App\Enums\UserTypeEnum;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\Project;
use App\Support\ListingQuery;
use App\Support\ViewerVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuditTrailQueryService
{
    private const MAX_EXPORT_ROWS = 5000;

    /** @var list<string> */
    public const SORTABLE_COLUMNS = ['id', 'uuid', 'created_at', 'action', 'action_module', 'user_type', 'http_status'];

    /**
     * @return Builder<AuditLog>
     */
    public function baseQuery(): Builder
    {
        // metadata is a JSON blob the list never shows; selecting it makes MySQL run out of sort memory on large tables.
        return AuditLog::query()
            ->select([
                'audit_logs.id', 'audit_logs.uuid', 'audit_logs.institution_id', 'audit_logs.user_type', 'audit_logs.user_id',
                'audit_logs.action_module', 'audit_logs.action', 'audit_logs.model', 'audit_logs.model_id',
                'audit_logs.description', 'audit_logs.ip_address', 'audit_logs.user_agent', 'audit_logs.http_status',
                'audit_logs.created_at', 'audit_logs.updated_at',
            ])
            ->with([
                'customerUser:uuid,firstname,lastname,email,phone_number',
                'adminUser:uuid,name,email',
                'institution:id,uuid,name',
            ]);
    }

    /**
     * Events for one page, plus the true per-day totals (under the same filters) for the days on it.
     *
     * @return array{0: LengthAwarePaginator<int, AuditLog>, 1: array<string, int>}
     */
    public function timelinePage(ListingQuery $listing): array
    {
        $paginator = $this->queryForListing($listing)
            ->paginate(perPage: $listing->perPage, page: $listing->page);

        $days = $paginator->getCollection()
            ->map(fn (AuditLog $log): string => $log->created_at->toDateString())
            ->unique()
            ->values();

        if ($days->isEmpty()) {
            return [$paginator, []];
        }

        $counts = $this->applyListing($this->baseQuery(), $listing)
            ->reorder()
            ->select(DB::raw('DATE(audit_logs.created_at) as day'), DB::raw('count(*) as total'))
            ->whereIn(DB::raw('DATE(audit_logs.created_at)'), $days->all())
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        return [$paginator, $counts];
    }

    /**
     * @return Builder<AuditLog>
     */
    public function applyListing(Builder $query, ListingQuery $listing): Builder
    {
        $this->applyFilters($query, $listing);
        $this->applySearch($query, $listing);

        $column = match ($listing->sortBy) {
            'id' => 'audit_logs.id',
            'uuid' => 'audit_logs.uuid',
            'created_at' => 'audit_logs.created_at',
            'action' => 'audit_logs.action',
            'action_module' => 'audit_logs.action_module',
            'user_type' => 'audit_logs.user_type',
            'http_status' => 'audit_logs.http_status',
            default => 'audit_logs.created_at',
        };

        return $query->orderBy($column, $listing->sortDirection === 'asc' ? 'asc' : 'desc');
    }

    /**
     * @return Builder<AuditLog>
     */
    public function queryForListing(ListingQuery $listing): Builder
    {
        return $this->applyListing($this->baseQuery(), $listing);
    }

    /**
     * @return array{0: Collection<int, AuditLog>, 1: bool}
     */
    public function exportCollection(ListingQuery $listing): array
    {
        $query = $this->applyListing($this->baseQuery(), $listing);
        $total = (clone $query)->count();
        $truncated = $total > self::MAX_EXPORT_ROWS;
        $rows = $query->limit(self::MAX_EXPORT_ROWS)->get();

        return [$rows, $truncated];
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    private function applyFilters(Builder $query, ListingQuery $listing): void
    {
        $filters = $listing->filters;

        if (! ViewerVisibility::canSeeIndividualRecords()) {
            $query->where('audit_logs.user_type', UserTypeEnum::ADMIN->value)
                ->whereNotIn('audit_logs.action', AuditActionEnum::individualLevelValues());
        }

        $userType = $filters['user_type'] ?? null;
        if (is_string($userType) && $userType !== '') {
            $query->where('audit_logs.user_type', $userType);
        }

        $actionModule = $filters['action_module'] ?? null;
        if (is_string($actionModule) && $actionModule !== '') {
            $query->where('audit_logs.action_module', $actionModule);
        }

        $action = $filters['action'] ?? null;
        if (is_string($action) && $action !== '') {
            $query->where('audit_logs.action', $action);
        }

        $institutionUuid = $filters['institution_uuid'] ?? null;
        if (is_string($institutionUuid) && $institutionUuid !== '') {
            $query->whereIn('audit_logs.institution_id', Institution::query()->where('uuid', $institutionUuid)->select('id'));
        }

        $model = $filters['model'] ?? null;
        if (is_string($model) && $model !== '') {
            $query->where('audit_logs.model', 'like', '%'.$this->escapeLike($model).'%');
        }

        if ($listing->startDate !== null) {
            $query->where('audit_logs.created_at', '>=', $listing->startDate);
        }

        if ($listing->endDate !== null) {
            $query->where('audit_logs.created_at', '<=', $listing->endDate);
        }

        $httpStatus = $filters['http_status'] ?? null;
        if (is_numeric($httpStatus)) {
            $query->where('audit_logs.http_status', (int) $httpStatus);
        }

        $projectUuid = $filters['project_uuid'] ?? null;
        if (is_string($projectUuid) && $projectUuid !== '') {
            $query->where(function (Builder $w) use ($projectUuid): void {
                $w->where('audit_logs.metadata->data->project_uuid', $projectUuid)
                    ->orWhere(function (Builder $w2) use ($projectUuid): void {
                        $w2->where('audit_logs.model', Project::class)
                            ->where('audit_logs.model_id', $projectUuid);
                    });
            });
        }
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    private function applySearch(Builder $query, ListingQuery $listing): void
    {
        $tokens = $listing->searchTokens();
        if ($tokens === []) {
            return;
        }

        foreach ($tokens as $token) {
            $like = '%'.$this->escapeLike($token).'%';
            $query->where(function (Builder $w) use ($like): void {
                $w->where('audit_logs.description', 'like', $like)
                    ->orWhere('audit_logs.uuid', 'like', $like)
                    ->orWhere('audit_logs.ip_address', 'like', $like)
                    ->orWhere('audit_logs.user_agent', 'like', $like)
                    ->orWhere('audit_logs.action', 'like', $like)
                    ->orWhere('audit_logs.action_module', 'like', $like)
                    ->orWhere('audit_logs.model', 'like', $like)
                    ->orWhere('audit_logs.model_id', 'like', $like)
                    ->orWhere('audit_logs.user_id', 'like', $like)
                    ->orWhereHas('customerUser', function (Builder $q) use ($like): void {
                        $q->where('email', 'like', $like)
                            ->orWhere('firstname', 'like', $like)
                            ->orWhere('lastname', 'like', $like)
                            ->orWhere('phone_number', 'like', $like);
                    })
                    ->orWhereHas('adminUser', function (Builder $q) use ($like): void {
                        $q->where('email', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    });
            });
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    public static function maxExportRows(): int
    {
        return self::MAX_EXPORT_ROWS;
    }
}

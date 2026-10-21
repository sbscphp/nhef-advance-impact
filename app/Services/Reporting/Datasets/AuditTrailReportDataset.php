<?php

namespace App\Services\Reporting\Datasets;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Helpers\PermissionModuleMapper;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use App\Support\ViewerVisibility;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Same viewer scoping as the live Audit Trail list ({@see \App\Services\Audit\AuditTrailQueryService}):
 * unlike every other dataset, one row here can belong to any module, so module-restriction and
 * individual-level filtering have to be applied inside the dataset itself, not left to
 * ReportDatasetEnum::availableToViewer()'s whole-dataset on/off gate.
 */
class AuditTrailReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Audit Trail';
    }

    public function nativeFields(): array
    {
        return [
            'Event' => [
                ['key' => 'action', 'label' => 'Action', 'type' => 'string'],
                ['key' => 'action_module', 'label' => 'Module', 'type' => 'string'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'string'],
                ['key' => 'model', 'label' => 'Record Type', 'type' => 'string'],
                ['key' => 'model_id', 'label' => 'Record ID', 'type' => 'string'],
            ],
            'Actor' => [
                ['key' => 'user_type', 'label' => 'User Type', 'type' => 'string'],
                ['key' => 'actor_name', 'label' => 'Actor Name', 'type' => 'string'],
                ['key' => 'actor_email', 'label' => 'Actor Email', 'type' => 'string'],
                ['key' => 'institution', 'label' => 'Institution', 'type' => 'string'],
            ],
            'Request' => [
                ['key' => 'ip_address', 'label' => 'IP Address', 'type' => 'string'],
                ['key' => 'http_status', 'label' => 'HTTP Status', 'type' => 'number'],
            ],
            'Dates' => [
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return $this->applyViewerScope(
            AuditLog::query()
                ->with(['customerUser:uuid,firstname,lastname,email', 'adminUser:uuid,name,email', 'institution:id,uuid,name'])
                ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
                ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
                ->when(filled($search), function ($query) use ($search): void {
                    $query->where(function ($inner) use ($search): void {
                        $inner->where('description', 'like', '%'.$search.'%')
                            ->orWhere('action', 'like', '%'.$search.'%')
                            ->orWhere('action_module', 'like', '%'.$search.'%')
                            ->orWhere('model', 'like', '%'.$search.'%')
                            ->orWhereHas('adminUser', fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                            ->orWhereHas('customerUser', fn ($q) => $q->where('firstname', 'like', '%'.$search.'%')->orWhere('lastname', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
                    });
                })
        );
    }

    /**
     * @param  AuditLog  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $actionEnum = $record->action instanceof AuditActionEnum ? $record->action : null;
        $moduleEnum = $record->action_module instanceof ModuleEnums ? $record->action_module : null;
        $actor = $record->user_type === UserTypeEnum::ADMIN ? $record->adminUser : $record->customerUser;

        $all = [
            'action' => $actionEnum?->humanised() ?? (string) $record->action,
            'action_module' => $moduleEnum?->label() ?? (string) $record->action_module,
            'description' => $record->description,
            'model' => $record->model,
            'model_id' => $record->model_id,
            'user_type' => $record->user_type?->value,
            'actor_name' => $actor instanceof Admin ? $actor->displayName() : trim((string) $actor?->firstname.' '.(string) $actor?->lastname),
            'actor_email' => $actor?->email,
            'institution' => $record->institution?->name,
            'ip_address' => $record->ip_address,
            'http_status' => $record->http_status,
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new AuditLog)->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'action_module', 'label' => 'Module', 'type' => 'string'],
            ['key' => 'user_type', 'label' => 'User Type', 'type' => 'string'],
            ['key' => 'http_status', 'label' => 'HTTP Status', 'type' => 'number'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'action_module' => 'action_module',
            'user_type' => 'user_type',
            'http_status' => 'http_status',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->applyViewerScope($this->simpleAggregateQuery(AuditLog::class, 'created_at', $start, $end));
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    private function applyViewerScope(Builder $query): Builder
    {
        if (! ViewerVisibility::canSeeIndividualRecords()) {
            $query->where('user_type', UserTypeEnum::ADMIN->value)
                ->whereNotIn('action', AuditActionEnum::individualLevelValues());
        }

        $actor = request()->user();
        if ($actor instanceof Admin) {
            $restricted = PermissionModuleMapper::restrictedModuleKeys(
                $actor->getAllPermissions()->pluck('name')->all()
            );

            if ($restricted !== []) {
                $query->where(fn ($q) => $q
                    ->whereNotIn('action_module', $restricted)
                    ->orWhereNull('action_module'));
            }
        }

        return $query;
    }
}

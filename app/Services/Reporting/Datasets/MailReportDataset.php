<?php

namespace App\Services\Reporting\Datasets;

use App\Models\Mail;
use App\Services\Reporting\Datasets\Concerns\BuildsSimpleAggregateQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MailReportDataset implements ReportDatasetInterface
{
    use BuildsSimpleAggregateQuery;

    public function label(): string
    {
        return 'Mail Campaigns';
    }

    public function nativeFields(): array
    {
        return [
            'Campaign' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'string'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                ['key' => 'recipients_count', 'label' => 'Recipients', 'type' => 'number'],
                ['key' => 'opened_count', 'label' => 'Opened', 'type' => 'number'],
            ],
            'Ownership' => [
                ['key' => 'created_by', 'label' => 'Created By', 'type' => 'string'],
            ],
            'Dates' => [
                ['key' => 'send_at', 'label' => 'Scheduled Send At', 'type' => 'date'],
                ['key' => 'sent_at', 'label' => 'Sent At', 'type' => 'date'],
                ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ],
        ];
    }

    public function query(?CarbonInterface $start, ?CarbonInterface $end, ?string $search): Builder
    {
        return Mail::query()
            ->with('creator')
            ->withCount('recipients')
            ->withCount(['recipients as opened_count' => fn ($query) => $query->whereNotNull('opened_at')])
            ->when($start !== null, fn ($query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('created_at', '<=', $end))
            ->when(filled($search), fn ($query) => $query->where('title', 'like', '%'.$search.'%'));
    }

    /**
     * @param  Mail  $record
     */
    public function mapRow(Model $record, array $nativeFieldKeys): array
    {
        $all = [
            'title' => $record->title,
            'status' => $record->status,
            'recipients_count' => $record->recipients_count,
            'opened_count' => $record->opened_count,
            'created_by' => $record->creator?->displayName(),
            'send_at' => $record->send_at?->toIso8601String(),
            'sent_at' => $record->sent_at?->toIso8601String(),
            'created_at' => $record->created_at?->toIso8601String(),
        ];

        return array_intersect_key($all, array_flip($nativeFieldKeys));
    }

    public function fieldableMorphClass(): string
    {
        return (new Mail())->getMorphClass();
    }

    public function fieldableId(Model $record): int
    {
        return $record->getKey();
    }

    public function groupableFields(): array
    {
        return [
            ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
        ];
    }

    public function groupableColumn(string $key): string
    {
        return $this->resolveGroupableColumn([
            'status' => 'status',
        ], $key);
    }

    public function aggregateQuery(?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $this->simpleAggregateQuery(Mail::class, 'created_at', $start, $end);
    }
}

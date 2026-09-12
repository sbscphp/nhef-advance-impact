<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectImpactReport extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'expenditure_value' => 'decimal:2',
            'evidence_urls' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(ProjectDeliverable::class, 'deliverable_id');
    }

    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(ProjectBudgetLine::class, 'budget_line_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'uuid');
    }
}

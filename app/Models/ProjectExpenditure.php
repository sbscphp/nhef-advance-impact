<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectExpenditure extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(ProjectBudgetLine::class, 'budget_line_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by', 'uuid');
    }
}

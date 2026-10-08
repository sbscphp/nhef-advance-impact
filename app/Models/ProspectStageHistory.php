<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectStageHistory extends Model
{
    use BelongsToTenant;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->whereIn('prospect_stage_histories.prospect_id', Prospect::query()->select('prospects.id'));
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}

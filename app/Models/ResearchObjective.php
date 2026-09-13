<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ResearchObjective extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'all_team_members' => 'boolean',
            'is_completed' => 'boolean',
        ];
    }

    public function research(): BelongsTo
    {
        return $this->belongsTo(Research::class);
    }

    public function assignments(): MorphMany
    {
        return $this->morphMany(ResearchAssignment::class, 'assignable');
    }
}

<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignProject extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'goal_amount' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}

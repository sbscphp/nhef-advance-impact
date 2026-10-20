<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignInstitutionProjectGoal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'goal_amount' => 'decimal:2',
        ];
    }

    public function campaignInstitution(): BelongsTo
    {
        return $this->belongsTo(CampaignInstitution::class);
    }

    public function campaignProject(): BelongsTo
    {
        return $this->belongsTo(CampaignProject::class);
    }
}

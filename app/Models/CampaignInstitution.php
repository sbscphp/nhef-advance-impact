<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignInstitution extends Model
{
    use BelongsToTenant, HasUuid;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->where('campaign_institutions.institution_id', $tenant->id);
    }

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

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * This institution's goal, broken down per project. Its own `goal_amount` column is always
     * the sum of these - see CampaignService::resolveProjectGoals().
     */
    public function projectGoals(): HasMany
    {
        return $this->hasMany(CampaignInstitutionProjectGoal::class);
    }

    public function progressPercentage(string $raisedAmount): int
    {
        if ((float) $this->goal_amount <= 0) {
            return 0;
        }

        return (int) min(100, round(((float) $raisedAmount / (float) $this->goal_amount) * 100));
    }
}

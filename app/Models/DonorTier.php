<?php

namespace App\Models;

use App\Services\DonorTier\DonorTierService;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Donor recognition tiers (BRD REC-02), with admin CRUD via {@see DonorTierService}. Tier
 * resolution is purely by minimum_amount brackets; `maximum_amount` is display/validation only.
 * Institution-scoped; `institution_id` null means a platform-wide default, used when an
 * institution has no tiers of its own.
 */
class DonorTier extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** Presentational only, derived from the UUID (same pattern as {@see User::code()}); no persisted code column. */
    public function code(): string
    {
        return 'NHEF-AD-'.strtoupper(substr($this->uuid, 0, 6));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'uuid')->withTrashed();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}

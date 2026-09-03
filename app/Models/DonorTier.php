<?php

namespace App\Models;

use App\Services\DonorTier\DonorTierService;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Donor recognition tiers (BRD REC-02). A donor's tier is the highest minimum_amount
 * threshold their lifetime NGN total meets or exceeds; seeded by DonorTierSeeder, now with
 * admin CRUD ({@see DonorTierService}). `maximum_amount` is a display/
 * validation field only, not used for tier resolution, which stays governed purely by
 * minimum_amount brackets to avoid two conflicting definitions of tier membership.
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
        return $this->belongsTo(Admin::class, 'created_by', 'uuid');
    }
}

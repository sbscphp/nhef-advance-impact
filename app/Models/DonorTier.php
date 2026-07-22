<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Donor recognition tiers (BRD REC-02: configurable per institution). Ranked by
 * minimum_amount: a donor's tier is the highest one whose threshold their lifetime NGN
 * donation total meets or exceeds. Seeded by DonorTierSeeder; no admin CRUD yet.
 */
class DonorTier extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'minimum_amount' => 'decimal:2',
        ];
    }
}

<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Donor recognition tiers (BRD REC-02). A donor's tier is the highest minimum_amount
 * threshold their lifetime NGN total meets or exceeds; seeded by DonorTierSeeder, no admin CRUD yet.
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

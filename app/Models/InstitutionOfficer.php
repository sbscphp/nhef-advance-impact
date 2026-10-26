<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A custom key-contact officer on an institution, beyond the three fixed ones (VC, Advancement
 * Director, Advancement Database Manager) that live directly on `institutions` - `role` is
 * free-typed, not an enum, since the FE defines it per entry. Contact info only, no login.
 */
class InstitutionOfficer extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}

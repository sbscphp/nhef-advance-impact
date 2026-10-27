<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One module (a ModuleEnums value) an institution's admins are allowed to access - the
 * "System Access Allowable" checklist on the AdvanceImpact Invite Institution screen.
 */
class InstitutionModule extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}

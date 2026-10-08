<?php

namespace App\Models;

use App\Enums\ConstituentTypeEnum;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A free-form tag (e.g. "Foundation Partner") an institution admin manually confers on a
 * constituent; shared platform-wide, not scoped to the conferring institution. A user may hold
 * several at once. Distinct from {@see ConstituentTypeEnum} (alumni/non_alumni/
 * organization), which is a single required classification, not an admin-conferred tag.
 */
class ConstituencyType extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'uuid')->withTrashed();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'constituency_type_user')
            ->withPivot(['conferred_by', 'conferred_at'])
            ->withTimestamps();
    }
}

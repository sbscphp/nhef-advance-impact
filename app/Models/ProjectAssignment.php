<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProjectAssignment extends Model
{
    protected $guarded = ['id'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }
}

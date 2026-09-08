<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomFieldValue extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'custom_field_definition_id');
    }

    public function fieldable(): MorphTo
    {
        return $this->morphTo();
    }
}

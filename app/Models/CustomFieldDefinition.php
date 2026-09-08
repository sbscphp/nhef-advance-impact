<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldDefinition extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'applicable_modules' => 'array',
            'options' => 'array',
            'is_required' => 'boolean',
            'is_searchable' => 'boolean',
            'is_filterable' => 'boolean',
            'show_in_reports' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'uuid');
    }
}

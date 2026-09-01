<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mail extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'segment_criteria' => 'array',
            'send_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'uuid');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sent_by', 'uuid');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class);
    }
}

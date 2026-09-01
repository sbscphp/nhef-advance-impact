<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationCallLog extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'call_date' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }

    public function logger(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'logged_by', 'uuid');
    }

    public function followUpTasks(): HasMany
    {
        return $this->hasMany(CommunicationTask::class, 'call_log_id');
    }
}

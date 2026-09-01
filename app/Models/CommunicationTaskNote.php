<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationTaskNote extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id', 'uuid'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(CommunicationTask::class, 'task_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'author_id', 'uuid');
    }
}

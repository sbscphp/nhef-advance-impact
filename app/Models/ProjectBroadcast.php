<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectBroadcast extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'send_date' => 'date',
            'all_team_members' => 'boolean',
            'attachment_urls' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'project_broadcast_recipients', 'broadcast_id', 'admin_id')->withTimestamps();
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'uuid');
    }
}

<?php

namespace App\Models;

use App\Casts\LenientEnum;
use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Models\Concerns\OwnedByInstitution;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuid, OwnedByInstitution;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'user_type' => UserTypeEnum::class,
            'action_module' => LenientEnum::class.':'.ModuleEnums::class,
            'action' => LenientEnum::class.':'.AuditActionEnum::class,
            'metadata' => 'array',
            'http_status' => 'integer',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function customerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'user_id', 'uuid')->withTrashed();
    }
}

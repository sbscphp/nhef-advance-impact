<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory, HasRoles, HasUuid, Notifiable;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->where('admins.institution_id', $tenant->id);
    }

    protected $guard_name = 'api';

    protected $guarded = ['id', 'uuid'];

    protected $hidden = [
        'id',
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            '2fa' => 'boolean',
            'email_notifications_enabled' => 'boolean',
            'push_notifications_enabled' => 'boolean',
            'is_active' => 'boolean',
            'can_login' => 'boolean',
            'must_reset_password' => 'boolean',
            'last_login_at' => 'datetime',
            'login_attempts' => 'integer',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    public function scopeNhefStaff(Builder $query): Builder
    {
        return $query->whereNull('admins.institution_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function isInstitutionAdmin(): bool
    {
        return $this->institution_id !== null;
    }

    public function displayName(): string
    {
        $name = trim((string) ($this->name ?? ''));

        return $name !== '' ? $name : (string) $this->email;
    }
}

<?php

namespace App\Models;

use App\Enums\AdminScopeEnum;
use App\Models\Concerns\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory, HasRoles, HasUuid, Notifiable, SoftDeletes {
        HasRoles::assignRole as private assignRoleUnchecked;
        HasRoles::syncRoles as private syncRolesUnchecked;
    }

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
            'onboarded_at' => 'datetime',
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

    public function scope(): AdminScopeEnum
    {
        return AdminScopeEnum::of($this);
    }

    public function isInstitutionAdmin(): bool
    {
        return $this->scope() === AdminScopeEnum::INSTITUTION;
    }

    public function assignRole(...$roles)
    {
        $this->assertRolesFitScope($roles);

        return $this->assignRoleUnchecked(...$roles);
    }

    public function syncRoles(...$roles)
    {
        $this->assertRolesFitScope($roles);

        return $this->syncRolesUnchecked(...$roles);
    }

    /** Backstop for every code path (seeders, tinker, services): an account can only hold roles that match its scope. */
    private function assertRolesFitScope(array $roles): void
    {
        foreach (collect($roles)->flatten()->filter() as $role) {
            $name = $this->getStoredRole($role)->name;

            if (! $this->scope()->allowsRole($name)) {
                throw new InvalidArgumentException("The {$name} role does not fit an admin account in {$this->scope()->value} scope.");
            }
        }
    }

    /** Presentational only, derived from the UUID; no persisted code column. */
    public function code(): string
    {
        return 'NHF-USR-'.strtoupper(substr(str_replace('-', '', $this->uuid), 0, 4));
    }

    public function displayName(): string
    {
        $name = trim((string) ($this->name ?? ''));

        return $name !== '' ? $name : (string) $this->email;
    }
}

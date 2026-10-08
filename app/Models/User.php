<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Notifications\Auth\ResetPasswordMail;
use App\Traits\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, HasRoles, HasUuid, Notifiable, TwoFactorAuthenticatable;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $tenant->tertiary_institution_id === null
            ? $query->whereRaw('1 = 0')
            : $query->where('users.tertiary_institution_id', $tenant->tertiary_institution_id);
    }

    protected $guard_name = 'api';

    protected $guarded = ['uuid', 'id'];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        '2fa_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            '2fa' => 'boolean',
            'password' => 'hashed',
            'email_notifications_enabled' => 'boolean',
            'push_notifications_enabled' => 'boolean',
            'biometrics_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'login_attempts' => 'integer',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'last_active_at' => 'datetime',
            'invited_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'date_of_birth' => 'date',
            'date_of_incorporation' => 'date',
            'area_of_interest' => 'array',
            'engagement_preference' => 'array',
        ];
    }

    public function tertiaryInstitution(): BelongsTo
    {
        return $this->belongsTo(TertiaryInstitution::class);
    }

    public function countryOfResidence(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_of_residence_id');
    }

    public function countryOfOperation(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_of_operation_id');
    }

    public function constituencyTypes(): BelongsToMany
    {
        return $this->belongsToMany(ConstituencyType::class, 'constituency_type_user')
            ->withPivot(['conferred_by', 'conferred_at'])
            ->withTimestamps();
    }

    /** An Organization account has no personal name, so it falls back to its own organisation_name. */
    public function displayName(): string
    {
        $name = trim(implode(' ', array_filter([
            (string) ($this->firstname ?? ''),
            (string) ($this->lastname ?? ''),
        ])));

        if ($name !== '') {
            return $name;
        }

        return filled($this->organisation_name) ? (string) $this->organisation_name : (string) $this->email;
    }

    /** Presentational only, derived from the UUID (see EventTicketSaleResource for the same pattern); no persisted code column. */
    public function code(): string
    {
        return 'NHEF-AD-'.strtoupper(substr($this->uuid, 0, 6));
    }

    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = config('app.frontend_url')
            .'/reset-password?token='.$token
            .'&email='.urlencode($this->email);

        $this->notify(new ResetPasswordMail($token, $resetUrl));
    }
}

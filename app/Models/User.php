<?php

namespace App\Models;

use App\Notifications\Auth\ResetPasswordMail;
use App\Traits\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuid, Notifiable, TwoFactorAuthenticatable;

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
        ];
    }

    public function tertiaryInstitution(): BelongsTo
    {
        return $this->belongsTo(TertiaryInstitution::class);
    }

    public function displayName(): string
    {
        $name = trim(implode(' ', array_filter([
            (string) ($this->firstname ?? ''),
            (string) ($this->lastname ?? ''),
        ])));

        return $name !== '' ? $name : (string) $this->email;
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

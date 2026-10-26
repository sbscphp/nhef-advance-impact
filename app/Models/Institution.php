<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Multitenancy\Models\Tenant;

class Institution extends Tenant
{
    use BelongsToTenant, HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'invited_at' => 'datetime',
            'onboarded_at' => 'datetime',
        ];
    }

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->where('institutions.id', $tenant->id);
    }

    public function campaignInstitutions(): HasMany
    {
        return $this->hasMany(CampaignInstitution::class);
    }

    public function tertiaryInstitution(): BelongsTo
    {
        return $this->belongsTo(TertiaryInstitution::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class);
    }

    public function officers(): HasMany
    {
        return $this->hasMany(InstitutionOfficer::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Presentational only, derived from the UUID (see EventTicketSaleResource for the same pattern); no persisted code column. */
    public function code(): string
    {
        return 'NHEF-IN-'.strtoupper(substr($this->uuid, 0, 6));
    }

    /**
     * Headline counts for the institution list and detail, present only when the service attached them.
     *
     * @return array<string, int>
     */
    public function statsPayload(): array
    {
        if (! $this->hasAttachedStats()) {
            return [];
        }

        return [
            'alumni_count' => (int) $this->attributes['alumni_count'],
            'non_alumni_count' => (int) $this->attributes['non_alumni_count'],
            'organisation_count' => (int) $this->attributes['organisation_count'],
            'campaigns_count' => (int) $this->attributes['campaigns_count'],
            'donors_count' => (int) $this->attributes['donors_count'],
        ];
    }

    /**
     * The money side of the same numbers, kept apart so it can be hidden from viewers without monetary access.
     *
     * @return array<string, string>
     */
    public function moneyStatsPayload(): array
    {
        if (! $this->hasAttachedStats()) {
            return [];
        }

        return [
            'total_donations' => (string) $this->attributes['total_donations'],
            'total_donations_formatted' => Money::format($this->attributes['total_donations'], 'NGN'),
            'total_pledges' => (string) $this->attributes['total_pledges'],
            'total_pledges_formatted' => Money::format($this->attributes['total_pledges'], 'NGN'),
        ];
    }

    private function hasAttachedStats(): bool
    {
        return array_key_exists('alumni_count', $this->attributes);
    }

    /** No logo of its own (2026-10-23) - always the linked tertiary institution's. */
    public function logoUrl(): ?string
    {
        return $this->tertiaryInstitution?->logoUrl();
    }

    /**
     * Public branding payload a client caches before login (landing page colours, logo).
     *
     * @return array<string, mixed>
     */
    public function workspaceData(): array
    {
        $theme = $this->theme ?? Theme::query()->where('is_default', true)->first();

        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'name' => $this->name,
            'logo_url' => $this->logoUrl(),
            'is_active' => (bool) $this->is_active,
            'theme' => $theme === null ? null : [
                'name' => $theme->name,
                'primary_color' => $theme->primary_color,
                'secondary_color' => $theme->secondary_color,
                'accent_color' => $theme->accentColor(),
                'link_color' => $theme->linkColor(),
                'background_color' => $theme->background_color,
                'surface_color' => $theme->surface_color,
                'text_color' => $theme->text_color,
                'header_background_color' => $theme->header_background_color,
                'font_family' => $theme->font_family,
            ],
        ];
    }
}

<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reference list of Nigerian universities, polytechnics, colleges of education, and other
 * tertiary institutions. `users.tertiary_institution_id` and `institutions.tertiary_institution_id`
 * both link here (see TertiaryInstitutionRepository::findOrCreateByName() for how a free-typed
 * name gets resolved to, or registered as, a row here). Seeded from a merged snapshot of public
 * datasets, and grows organically from unmatched user input.
 */
class TertiaryInstitution extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    /**
     * An onboarded Institution has no logo of its own (2026-10-23) - it resolves through here via
     * its own tertiaryInstitution relation instead. See Institution::logoUrl().
     */
    public function logoUrl(): ?string
    {
        $path = $this->logo_file_path;

        if (empty($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk((string) config('filesystems.public_disk', 'public'))->url($path);
    }
}

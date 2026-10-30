<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reference list of Nigerian tertiary institutions. `users.tertiary_institution_id` and
 * `institutions.tertiary_institution_id` both link here; see TertiaryInstitutionRepository::findOrCreateByName().
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

    /** An onboarded Institution has no logo of its own; it resolves through here. See Institution::logoUrl(). */
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

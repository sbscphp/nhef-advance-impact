<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Reference list of Nigerian universities, polytechnics, colleges of education, and other
 * tertiary institutions, used as a search-and-select source for the free-text `users.university`
 * field (never a foreign key; see TertiaryInstitutionRepository::findOrCreateByName()). Seeded
 * from a merged snapshot of public datasets, and grows organically from unmatched user input.
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
}

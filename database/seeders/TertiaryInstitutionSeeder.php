<?php

namespace Database\Seeders;

use App\Models\TertiaryInstitution;
use Illuminate\Database\Seeder;

/**
 * Seeds a merged snapshot of Nigerian tertiary institutions (universities, polytechnics,
 * colleges of education, and other colleges), built once from three public datasets; see
 * database/seeders/data/tertiary_institutions.json. Not a live sync, no official source exists.
 */
class TertiaryInstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $path = __DIR__.'/data/tertiary_institutions.json';
        $institutions = json_decode(file_get_contents($path), true);

        foreach ($institutions as $institution) {
            TertiaryInstitution::updateOrCreate(
                ['name' => $institution['name']],
                [
                    'type' => $institution['type'] ?? null,
                    'state' => $institution['state'] ?? null,
                    'city' => $institution['city'] ?? null,
                    'abbreviation' => $institution['abbreviation'] ?? null,
                    'website' => $institution['website'] ?? null,
                    'is_verified' => true,
                ]
            );
        }
    }
}

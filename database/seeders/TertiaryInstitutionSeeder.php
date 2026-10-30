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
            $website = $institution['website'] ?? null;

            TertiaryInstitution::updateOrCreate(
                ['name' => $institution['name']],
                [
                    'type' => $institution['type'] ?? null,
                    'state' => $institution['state'] ?? null,
                    'city' => $institution['city'] ?? null,
                    'abbreviation' => $institution['abbreviation'] ?? null,
                    'website' => $website,
                    'logo_file_path' => $this->faviconUrlFor($website),
                    'is_verified' => true,
                ]
            );
        }
    }

    /**
     * No dataset of real institution logos exists, so this derives a stand-in from each site's
     * favicon via Google's public favicon service; swap in real ones later if sourced.
     */
    private function faviconUrlFor(?string $website): ?string
    {
        if (empty($website)) {
            return null;
        }

        $host = parse_url($website, PHP_URL_HOST);
        if (empty($host)) {
            return null;
        }

        return 'https://www.google.com/s2/favicons?sz=128&domain='.$host;
    }
}

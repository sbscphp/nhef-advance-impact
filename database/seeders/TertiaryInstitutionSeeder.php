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
     * 2026-10-23: no dataset of real institution logos exists, so this derives a stand-in from
     * each institution's own site favicon via Google's public favicon service - no download, no
     * hosting, and the same domain always resolves to the same URL (safe to re-seed). Only ~145
     * of 625 seeded rows have a `website` at all; the rest stay null, same as before this field
     * existed. It's a small icon, not an official high-res logo - swap in real ones later if the
     * client sources them.
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

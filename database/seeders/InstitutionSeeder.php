<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Repositories\Contracts\TertiaryInstitution\TertiaryInstitutionRepositoryInterface;
use Illuminate\Database\Seeder;

/**
 * Lookup list for the "Institution" dropdown on the National Giving Day campaign wizard. Each
 * name here is a bare/shorter form than its canonical tertiary_institutions entry (e.g.
 * "University of Lagos" vs "University of Lagos, Lagos"), so KNOWN_MAPPINGS resolves it to the
 * correct row rather than registering it as a brand-new, separate institution - matching the
 * mapping used in the 2026_09_03_103203_add_tertiary_institution_id_to_institutions_table
 * migration this seeder's data was originally backfilled against.
 *
 * php artisan db:seed --class=InstitutionSeeder
 */
class InstitutionSeeder extends Seeder
{
    /** @var array<string, string> */
    private const KNOWN_MAPPINGS = [
        'University of Lagos' => 'University of Lagos, Lagos',
        'University of Ibadan' => 'University of Ibadan, Ibadan',
        'Ahmadu Bello University' => 'Ahmadu Bello University, Zaria',
        'Obafemi Awolowo University' => 'Obafemi Awolowo University, Ile-Ife',
        'University of Abuja' => 'University of Abuja, Abuja',
        'Covenant University' => 'Covenant University, Ota',
        'Lagos State University' => 'Lagos State University, Ojo',
        'University of Benin' => 'University of Benin, Benin City',
        'Bayero University Kano' => 'Bayero University, Kano',
    ];

    public function run(): void
    {
        $institutionRepository = app(TertiaryInstitutionRepositoryInterface::class);

        $institutions = [
            'University of Lagos',
            'University of Ibadan',
            'University of Nigeria, Nsukka',
            'Ahmadu Bello University',
            'Obafemi Awolowo University',
            'University of Abuja',
            'Covenant University',
            'Lagos State University',
            'University of Benin',
            'Bayero University Kano',
        ];

        foreach ($institutions as $name) {
            $canonicalName = self::KNOWN_MAPPINGS[$name] ?? $name;
            $tertiaryInstitution = $institutionRepository->findOrCreateByName($canonicalName);

            Institution::firstOrCreate(
                ['name' => $name],
                ['is_active' => true, 'tertiary_institution_id' => $tertiaryInstitution->id]
            );
        }
    }
}

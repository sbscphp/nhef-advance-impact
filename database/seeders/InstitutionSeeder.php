<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Repositories\Contracts\TertiaryInstitution\TertiaryInstitutionRepositoryInterface;
use Illuminate\Database\Seeder;

/**
 * Lookup list for the "Institution" dropdown. KNOWN_MAPPINGS resolves each bare/shorter name
 * to its canonical tertiary_institutions row instead of registering a new, separate one.
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

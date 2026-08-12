<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;

/**
 * Lookup list for the "Institution" dropdown on the National Giving Day campaign wizard.
 *
 * php artisan db:seed --class=InstitutionSeeder
 */
class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
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
            Institution::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}

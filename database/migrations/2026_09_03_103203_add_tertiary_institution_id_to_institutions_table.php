<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const KNOWN_MAPPINGS = [
        'University of Lagos' => 'University of Lagos, Lagos',
        'University of Ibadan' => 'University of Ibadan, Ibadan',
        'Ahmadu Bello University' => 'Ahmadu Bello University, Zaria',
        'Obafemi Awolowo University' => 'Obafemi Awolowo University, Ile-Ife',
        // Two campuses exist ("Abuja" and "Gwagwalada"); "Abuja" has the richer/more complete
        // record (state, abbreviation, website), so it's the one picked here - matches the same
        // choice made in the users table backfill.
        'University of Abuja' => 'University of Abuja, Abuja',
        'Covenant University' => 'Covenant University, Ota',
        'Lagos State University' => 'Lagos State University, Ojo',
        'University of Benin' => 'University of Benin, Benin City',
        'Bayero University Kano' => 'Bayero University, Kano',
    ];

    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->foreignId('tertiary_institution_id')->nullable()->after('name')
                ->constrained('tertiary_institutions')->nullOnDelete();
        });

        $rows = DB::table('institutions')->select('id', 'name')->get();

        foreach ($rows as $row) {
            $canonicalName = self::KNOWN_MAPPINGS[$row->name] ?? $row->name;

            $institutionId = DB::table('tertiary_institutions')->whereRaw('LOWER(name) = ?', [strtolower($canonicalName)])->value('id');

            if ($institutionId === null) {
                $institutionId = DB::table('tertiary_institutions')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'name' => $row->name,
                    'is_verified' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('institutions')->where('id', $row->id)->update(['tertiary_institution_id' => $institutionId]);
        }
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tertiary_institution_id');
        });
    }
};

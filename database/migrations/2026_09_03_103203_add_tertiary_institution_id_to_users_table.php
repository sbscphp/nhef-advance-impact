<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Known raw values already in `users.university` at the time this migration was written,
     * mapped to their canonical tertiary_institutions.name. Anything not listed here (including
     * anything added after this migration was written) falls back to an exact name match, or
     * gets registered as a new, unverified tertiary_institutions row so no data is silently lost.
     *
     * @var array<string, string>
     */
    private const KNOWN_MAPPINGS = [
        'University of Lagos' => 'University of Lagos, Lagos',
        'University of Ibadan' => 'University of Ibadan, Ibadan',
        'Ahmadu Bello University' => 'Ahmadu Bello University, Zaria',
        // Two campuses exist ("Abuja" and "Gwagwalada"); "Abuja" has the richer/more complete
        // record (state, abbreviation, website), so it's the one picked here.
        'University of Abuja' => 'University of Abuja, Abuja',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tertiary_institution_id')->nullable()->after('university')
                ->constrained('tertiary_institutions')->nullOnDelete();
        });

        $rawValues = DB::table('users')->whereNotNull('university')->where('university', '!=', '')->distinct()->pluck('university');

        foreach ($rawValues as $raw) {
            $canonicalName = self::KNOWN_MAPPINGS[$raw] ?? $raw;

            $institutionId = DB::table('tertiary_institutions')->whereRaw('LOWER(name) = ?', [strtolower($canonicalName)])->value('id');

            if ($institutionId === null) {
                $institutionId = DB::table('tertiary_institutions')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'name' => $raw,
                    'is_verified' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('users')->where('university', $raw)->update(['tertiary_institution_id' => $institutionId]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('university');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('university')->nullable()->after('matric_no');
        });

        DB::table('users')
            ->join('tertiary_institutions', 'tertiary_institutions.id', '=', 'users.tertiary_institution_id')
            ->update(['users.university' => DB::raw('tertiary_institutions.name')]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tertiary_institution_id');
        });
    }
};

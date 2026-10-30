<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The three fixed offices should live in the same `officers` array as any custom contact, not
 * as separate flat columns. Backfills any institution with values in those columns into
 * `institution_officers` first, then drops the columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('institutions')
            ->select([
                'id',
                'vc_name', 'vc_email', 'vc_phone_number',
                'advancement_director_name', 'advancement_director_email', 'advancement_director_phone_number',
                'advancement_database_manager_name', 'advancement_database_manager_email', 'advancement_database_manager_phone_number',
            ])
            ->orderBy('id')
            ->chunk(100, function ($institutions) use ($now) {
                $rows = [];

                foreach ($institutions as $institution) {
                    foreach ([
                        'vc' => ['vc_name', 'vc_email', 'vc_phone_number'],
                        'advancement_director' => ['advancement_director_name', 'advancement_director_email', 'advancement_director_phone_number'],
                        'advancement_database_manager' => ['advancement_database_manager_name', 'advancement_database_manager_email', 'advancement_database_manager_phone_number'],
                    ] as $role => [$nameCol, $emailCol, $phoneCol]) {
                        if (empty($institution->$nameCol) && empty($institution->$emailCol)) {
                            continue;
                        }

                        $rows[] = [
                            'uuid' => (string) Str::uuid(),
                            'institution_id' => $institution->id,
                            'role' => $role,
                            'name' => $institution->$nameCol ?? '',
                            'email' => $institution->$emailCol ?? '',
                            'phone_number' => $institution->$phoneCol ?? '',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('institution_officers')->insert($rows);
                }
            });

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn([
                'vc_name', 'vc_email', 'vc_phone_number',
                'advancement_director_name', 'advancement_director_email', 'advancement_director_phone_number',
                'advancement_database_manager_name', 'advancement_database_manager_email', 'advancement_database_manager_phone_number',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('vc_name')->nullable()->after('address');
            $table->string('vc_email')->nullable()->after('vc_name');
            $table->string('vc_phone_number')->nullable()->after('vc_email');
            $table->string('advancement_director_name')->nullable()->after('vc_phone_number');
            $table->string('advancement_director_email')->nullable()->after('advancement_director_name');
            $table->string('advancement_director_phone_number')->nullable()->after('advancement_director_email');
            $table->string('advancement_database_manager_name')->nullable()->after('advancement_director_phone_number');
            $table->string('advancement_database_manager_email')->nullable()->after('advancement_database_manager_name');
            $table->string('advancement_database_manager_phone_number')->nullable()->after('advancement_database_manager_email');
        });

        // Not backfilled on rollback - rows already migrated into institution_officers stay there.
    }
};

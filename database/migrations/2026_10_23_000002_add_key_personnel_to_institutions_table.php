<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inviting an institution now also captures its three key offices. Contact info only, except
 * the Advancement Database Manager, who becomes the institution's own admin account.
 */
return new class extends Migration
{
    public function up(): void
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
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn([
                'vc_name',
                'vc_email',
                'vc_phone_number',
                'advancement_director_name',
                'advancement_director_email',
                'advancement_director_phone_number',
                'advancement_database_manager_name',
                'advancement_database_manager_email',
                'advancement_database_manager_phone_number',
            ]);
        });
    }
};

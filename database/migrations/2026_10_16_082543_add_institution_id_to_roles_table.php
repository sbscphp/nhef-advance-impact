<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Null = a global/system role (Super Admin, Admin, Institution Admin, Ministry of
            // Education, Customer). Non-null = a custom role created by that institution's own
            // admin, visible and manageable only within that institution (plus Super Admin, for
            // oversight).
            $table->foreignId('institution_id')->nullable()->after('guard_name')
                ->constrained('institutions')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
        });
    }
};

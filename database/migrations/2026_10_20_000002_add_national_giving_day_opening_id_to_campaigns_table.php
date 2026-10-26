<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Only ever set for a national_giving_day campaign; null for standard campaigns and
            // for pre-existing NGD campaigns created before this concept existed.
            $table->foreignId('national_giving_day_opening_id')->nullable()->after('type')
                ->constrained('national_giving_day_openings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('national_giving_day_opening_id');
        });
    }
};

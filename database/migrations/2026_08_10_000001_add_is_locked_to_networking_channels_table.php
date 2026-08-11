<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('networking_channels', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('is_archived')->index();
            $table->timestamp('locked_at')->nullable()->after('is_locked');
        });
    }

    public function down(): void
    {
        Schema::table('networking_channels', function (Blueprint $table) {
            $table->dropColumn(['is_locked', 'locked_at']);
        });
    }
};

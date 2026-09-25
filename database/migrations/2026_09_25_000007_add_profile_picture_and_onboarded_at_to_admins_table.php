<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('profile_picture_url')->nullable()->after('job_title');
            $table->timestamp('onboarded_at')->nullable()->after('must_reset_password');
        });

        // Seeded or already-active admins never went through the invite flow, so account creation is their onboarding.
        DB::table('admins')->where('must_reset_password', false)->update(['onboarded_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['profile_picture_url', 'onboarded_at']);
        });
    }
};

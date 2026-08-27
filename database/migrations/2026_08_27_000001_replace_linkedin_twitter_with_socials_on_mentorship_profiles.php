<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentee_profiles', function (Blueprint $table) {
            $table->json('socials')->nullable()->after('why_mentor_needed');
            $table->dropColumn(['linkedin_url', 'twitter_url']);
        });

        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->json('socials')->nullable()->after('major_achievements');
            $table->dropColumn(['linkedin_url', 'twitter_url']);
        });
    }

    public function down(): void
    {
        Schema::table('mentee_profiles', function (Blueprint $table) {
            $table->string('linkedin_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->dropColumn('socials');
        });

        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->string('linkedin_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->dropColumn('socials');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('event_type')->default('physical')->after('description');
            $table->string('virtual_link')->nullable()->after('venue_address');
            $table->boolean('waitlist_enabled')->default(false)->after('registration_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['event_type', 'virtual_link', 'waitlist_enabled']);
        });
    }
};

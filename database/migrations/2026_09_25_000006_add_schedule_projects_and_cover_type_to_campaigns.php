<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->change();
            $table->dateTime('ends_at')->nullable()->change();
            $table->unsignedInteger('timer_lead_hours')->nullable()->after('ends_at');
            $table->string('cover_media_type', 10)->default('image')->after('cover_image_url');
        });

        // Date-only end dates meant "through the end of that day"; keep that now that the column carries a time.
        DB::table('campaigns')->whereNotNull('ends_at')->update(['ends_at' => DB::raw("ends_at + INTERVAL '23:59:59' HOUR_SECOND")]);

        Schema::create('campaign_projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('goal_amount', 15, 2);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_projects');

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['timer_lead_hours', 'cover_media_type']);
            $table->date('starts_at')->nullable()->change();
            $table->date('ends_at')->nullable()->change();
        });
    }
};

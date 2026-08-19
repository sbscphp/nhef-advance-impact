<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_ticket_types', function (Blueprint $table) {
            $table->decimal('discount_percentage', 5, 2)->nullable()->after('quantity_sold');
            $table->dateTime('discount_starts_at')->nullable()->after('discount_percentage');
            $table->dateTime('discount_ends_at')->nullable()->after('discount_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_ticket_types', function (Blueprint $table) {
            $table->dropColumn(['discount_percentage', 'discount_starts_at', 'discount_ends_at']);
        });
    }
};

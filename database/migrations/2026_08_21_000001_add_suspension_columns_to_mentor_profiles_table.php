<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->foreignId('suspended_by')->nullable()->after('rejection_reason')->constrained('admins')->nullOnDelete();
            $table->timestamp('suspended_at')->nullable()->after('suspended_by');
            $table->text('suspension_reason')->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};

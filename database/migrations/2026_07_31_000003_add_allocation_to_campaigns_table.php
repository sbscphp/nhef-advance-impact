<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('allocated_admin_id')->nullable()->after('created_by')->constrained('admins')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->after('allocated_admin_id')->constrained('bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('allocated_admin_id');
            $table->dropConstrainedForeignId('bank_account_id');
        });
    }
};

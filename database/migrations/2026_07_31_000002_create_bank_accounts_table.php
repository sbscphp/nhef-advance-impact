<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();
            $table->string('account_number');
            $table->string('account_name');
            $table->uuid('created_by')->nullable()->comment('Admin uuid who added this account.');
            $table->timestamps();

            $table->unique(['bank_id', 'account_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};

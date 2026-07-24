<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pledge_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pledge_id')->constrained('pledges')->cascadeOnDelete();
            $table->foreignId('pledge_installment_id')->nullable()->constrained('pledge_installments')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->string('method')->nullable();
            $table->string('gateway')->nullable();
            $table->string('gateway_reference')->unique();
            $table->string('status')->default('pending')->index();
            $table->string('card_last_four', 4)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pledge_payments');
    }
};

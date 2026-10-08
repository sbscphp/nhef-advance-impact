<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'events',
        'mails',
        'communication_tasks',
        'communication_call_logs',
        'prospects',
        'generated_reports',
        'networking_channels',
        'audit_logs',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('institution_id')->nullable()->constrained('institutions')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('institution_id');
            });
        }
    }
};

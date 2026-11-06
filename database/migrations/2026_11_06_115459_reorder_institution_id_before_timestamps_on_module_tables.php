<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `institution_id` was appended to each of these tables by
     * 2026_09_25_000002_add_institution_owner_to_module_tables.php with no `->after(...)`, which
     * in MySQL puts it after whatever the table's last column was at the time - always
     * `updated_at`. Repositions it to sit with the table's other data columns, timestamps last.
     *
     * @var array<string, string> table => column `institution_id` should sit after
     */
    private const TABLE_AFTER_COLUMN = [
        'events' => 'created_by',
        'mails' => 'created_by',
        'communication_tasks' => 'created_by',
        'communication_call_logs' => 'call_date',
        'prospects' => 'created_by',
        'generated_reports' => 'created_by',
        'networking_channels' => 'locked_at',
        'audit_logs' => 'metadata',
    ];

    public function up(): void
    {
        foreach (self::TABLE_AFTER_COLUMN as $table => $afterColumn) {
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `institution_id` BIGINT UNSIGNED NULL AFTER `{$afterColumn}`");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLE_AFTER_COLUMN) as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `institution_id` BIGINT UNSIGNED NULL AFTER `updated_at`");
        }
    }
};

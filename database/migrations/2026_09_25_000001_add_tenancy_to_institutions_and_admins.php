<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->foreignId('theme_id')->nullable()->after('tertiary_institution_id')
                ->constrained('themes')->nullOnDelete();
            $table->string('logo_file_path')->nullable()->after('theme_id');
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('email')
                ->constrained('institutions')->nullOnDelete();
        });

        $taken = [];
        foreach (DB::table('institutions')->select('id', 'name')->orderBy('id')->get() as $row) {
            $base = Str::slug((string) $row->name) ?: 'institution';
            $slug = $base;
            $suffix = 2;
            while (in_array($slug, $taken, true)) {
                $slug = $base.'-'.$suffix++;
            }
            $taken[] = $slug;

            DB::table('institutions')->where('id', $row->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
        });

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('theme_id');
            $table->dropColumn(['slug', 'logo_file_path']);
        });
    }
};

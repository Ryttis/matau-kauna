<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('data_retention_rules') || ! Schema::hasColumn('data_retention_rules', 'archive_after_days')) {
            return;
        }

        DB::statement('UPDATE data_retention_rules SET delete_after_days = COALESCE(delete_after_days, archive_after_days)');

        Schema::table('data_retention_rules', function (Blueprint $table) {
            $table->dropColumn('archive_after_days');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('data_retention_rules') || Schema::hasColumn('data_retention_rules', 'archive_after_days')) {
            return;
        }

        Schema::table('data_retention_rules', function (Blueprint $table) {
            $table->unsignedInteger('archive_after_days')->nullable()->after('entity_type');
        });
    }
};

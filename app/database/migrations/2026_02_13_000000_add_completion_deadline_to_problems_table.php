<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            if (! Schema::hasColumn('problems', 'completion_deadline')) {
                $table->date('completion_deadline')->nullable()->after('assigned_employee_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            if (Schema::hasColumn('problems', 'completion_deadline')) {
                $table->dropColumn('completion_deadline');
            }
        });
    }
};

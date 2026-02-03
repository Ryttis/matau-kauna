<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            if (! Schema::hasColumn('problems', 'assigned_employee_id')) {
                $table->unsignedBigInteger('assigned_employee_id')
                    ->nullable()
                    ->after('institution_id')
                    ->index();
            }
        });

        $rogueConstraint = DB::selectOne("SELECT 1 FROM pg_constraint WHERE conname = '1' AND conrelid = 'problems'::regclass");
        if ($rogueConstraint) {
            DB::statement('ALTER TABLE problems DROP CONSTRAINT IF EXISTS "1"');
        }

        $hasConstraint = DB::selectOne("SELECT 1 FROM pg_constraint WHERE conname = 'problems_assigned_employee_id_foreign'");
        if (! $hasConstraint) {
            DB::statement(
                'ALTER TABLE problems ADD CONSTRAINT problems_assigned_employee_id_foreign ' .
                'FOREIGN KEY (assigned_employee_id) REFERENCES employees(id) ON DELETE SET NULL'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            if (Schema::hasColumn('problems', 'assigned_employee_id')) {
                DB::statement('ALTER TABLE problems DROP CONSTRAINT IF EXISTS problems_assigned_employee_id_foreign');
                DB::statement('ALTER TABLE problems DROP CONSTRAINT IF EXISTS "1"');
                $table->dropColumn('assigned_employee_id');
            }
        });
    }
};

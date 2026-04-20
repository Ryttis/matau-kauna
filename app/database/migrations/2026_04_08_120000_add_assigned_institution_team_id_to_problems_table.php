<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->foreignId('assigned_institution_team_id')
                ->nullable()
                ->after('assigned_employee_id')
                ->constrained('institution_teams')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_institution_team_id');
        });
    }
};

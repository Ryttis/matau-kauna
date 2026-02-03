<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('institution_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employee_institution_team', function (Blueprint $table) {
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_team_id')->constrained('institution_teams')->cascadeOnDelete();
            $table->primary(['employee_id', 'institution_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_institution_team');
        Schema::dropIfExists('institution_teams');
    }
};

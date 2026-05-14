<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_dvs_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('token_url', 512)->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('gaunami_dokumentai_url', 512)->nullable();
            $table->string('pilieciu_laiskai_url', 512)->nullable();
            $table->string('rusis_url', 512)->nullable();
            $table->string('rastines_id')->nullable();
            $table->string('registras_id')->nullable();
            $table->string('darbuotojo_kodas')->nullable();
            $table->string('sudarytojas_kodas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_dvs_configs');
    }
};

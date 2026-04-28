<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('problem_gis_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->unique()->constrained('problems')->cascadeOnDelete();
            $table->foreignId('institution_gis_config_id')->constrained('institution_gis_configs')->cascadeOnDelete();
            $table->string('external_object_id', 64)->nullable();
            $table->string('send_status', 64)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['institution_gis_config_id', 'send_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_gis_transfers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('problem_dvs_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->unique()->constrained('problems')->cascadeOnDelete();
            $table->foreignId('institution_dvs_config_id')->constrained('institution_dvs_configs')->cascadeOnDelete();
            $table->string('complaint_api', 64);
            $table->string('external_object_id', 64)->nullable();
            $table->string('external_reg_nr', 255)->nullable();
            $table->string('send_status', 64)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('answer_date')->nullable();
            $table->text('answer_notes')->nullable();
            $table->json('last_response_json')->nullable();
            $table->timestamps();

            $table->index(['institution_dvs_config_id', 'send_status']);
            $table->index(['complaint_api', 'send_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_dvs_transfers');
    }
};

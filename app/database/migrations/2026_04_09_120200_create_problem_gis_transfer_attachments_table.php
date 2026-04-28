<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('problem_gis_transfer_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_gis_transfer_id')->constrained('problem_gis_transfers')->cascadeOnDelete();
            $table->foreignId('problem_attachment_id')->constrained('problem_attachments')->cascadeOnDelete();
            $table->string('external_attachment_id', 64)->nullable();
            $table->string('status', 64)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['problem_gis_transfer_id', 'problem_attachment_id'], 'problem_gis_transfer_attachment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_gis_transfer_attachments');
    }
};

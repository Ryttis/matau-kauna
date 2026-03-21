<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('problem_review_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_review_history_id')->constrained('problem_review_history')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');
            $table->string('kind', 16);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_review_attachments');
    }
};

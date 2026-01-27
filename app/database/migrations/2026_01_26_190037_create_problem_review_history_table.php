<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('problem_review_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->constrained('problems')->onDelete('cascade');
            $table->string('status', 50);
            $table->string('responsible', 255)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            
            $table->index('problem_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('problem_review_history');
    }
};

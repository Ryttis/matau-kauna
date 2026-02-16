<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('problem_resolution_term_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('days');
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->string('category', 100)->nullable();
            $table->unsignedBigInteger('problem_type_id')->nullable();
            $table->unsignedBigInteger('problem_nature_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('problem_type_id')->references('id')->on('classifier_values')->nullOnDelete();
            $table->foreign('problem_nature_id')->references('id')->on('classifier_values')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_resolution_term_rules');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('problem_auto_assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('category')->nullable();
            $table->string('keyword', 500)->nullable();
            $table->unsignedBigInteger('institution_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('problem_auto_assignment_rules');
    }
};

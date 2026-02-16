<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_retention_rules', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64)->unique();
            $table->unsignedInteger('archive_after_days')->nullable();
            $table->unsignedInteger('delete_after_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_retention_rules');
    }
};

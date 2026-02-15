<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classifier_values', function (Blueprint $table) {
            $table->dropForeign(['classifier_category_id']);
            $table->dropIndex(['classifier_category_id', 'is_active']);
            $table->dropColumn('classifier_category_id');
        });
        Schema::dropIfExists('classifier_categories');
    }

    public function down(): void
    {
        Schema::create('classifier_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('classifier_values', function (Blueprint $table) {
            $table->foreignId('classifier_category_id')->after('id')->constrained('classifier_categories')->cascadeOnDelete();
            $table->index(['classifier_category_id', 'is_active']);
        });
    }
};

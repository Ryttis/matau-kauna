<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classifier_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('classifier_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classifier_category_id')->constrained('classifier_categories')->cascadeOnDelete();
            $table->string('name', 255);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['classifier_category_id', 'is_active']);
        });

        Schema::table('problems', function (Blueprint $table) {
            if (!Schema::hasColumn('problems', 'problem_type_id')) {
                $table->foreignId('problem_type_id')->nullable()->after('category')->constrained('classifier_values')->nullOnDelete();
            }
            if (!Schema::hasColumn('problems', 'problem_nature_id')) {
                $table->foreignId('problem_nature_id')->nullable()->after('problem_type_id')->constrained('classifier_values')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->dropForeign(['problem_type_id']);
            $table->dropForeign(['problem_nature_id']);
            $table->dropColumn(['problem_type_id', 'problem_nature_id']);
        });
        Schema::dropIfExists('classifier_values');
        Schema::dropIfExists('classifier_categories');
    }
};

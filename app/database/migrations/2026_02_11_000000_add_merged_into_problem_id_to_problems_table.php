<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->foreignId('merged_into_problem_id')->nullable()->after('reported_by_user_id')->constrained('problems')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->dropForeign(['merged_into_problem_id']);
            $table->dropColumn('merged_into_problem_id');
        });
    }
};

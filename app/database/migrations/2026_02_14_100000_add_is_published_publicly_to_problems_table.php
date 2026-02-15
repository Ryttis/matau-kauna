<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->boolean('is_published_publicly')->default(false)->after('merged_into_problem_id');
        });
    }

    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->dropColumn('is_published_publicly');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('problem_review_history', function (Blueprint $table) {
            $table->boolean('status_changed')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('problem_review_history', function (Blueprint $table) {
            $table->dropColumn('status_changed');
        });
    }
};

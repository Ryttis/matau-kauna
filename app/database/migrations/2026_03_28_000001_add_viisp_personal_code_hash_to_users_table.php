<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'viisp_personal_code_hash')) {
                $table->string('viisp_personal_code_hash', 32)->nullable()->after('email');
                $table->unique('viisp_personal_code_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'viisp_personal_code_hash')) {
                $table->dropUnique(['viisp_personal_code_hash']);
                $table->dropColumn('viisp_personal_code_hash');
            }
        });
    }
};

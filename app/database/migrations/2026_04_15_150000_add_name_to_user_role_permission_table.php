<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_role_permission', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        DB::table('user_role_permission')
            ->whereNull('name')
            ->update([
                'name' => DB::raw('permission'),
            ]);

    }

    public function down(): void
    {
        Schema::table('user_role_permission', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};

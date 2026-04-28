<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_gis_configs', function (Blueprint $table) {
            $table->unsignedInteger('wkid')->default(3346)->after('attachment_url');
        });
    }

    public function down(): void
    {
        Schema::table('institution_gis_configs', function (Blueprint $table) {
            $table->dropColumn('wkid');
        });
    }
};

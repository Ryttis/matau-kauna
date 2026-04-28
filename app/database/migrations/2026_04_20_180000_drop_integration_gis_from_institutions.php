<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            if (Schema::hasColumn('institutions', 'integration_gis')) {
                $table->dropColumn('integration_gis');
            }
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            if (! Schema::hasColumn('institutions', 'integration_gis')) {
                $table->string('integration_gis', 255)->nullable();
            }
        });
    }
};
